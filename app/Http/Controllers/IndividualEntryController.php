<?php

namespace App\Http\Controllers;

use App\Models\Entry;
use App\Models\Meet;
use App\Models\User;
use App\Payments\PaymentGateways;
use App\Rules\AthleteRules;
use App\Services\SheetEntryService;
use App\Support\RaceTime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * 個人申し込み：アカウント登録なしで、フォームに入力して申し込み、そのまま支払いへ進む。
 * 申込データはWebエントリー・エクセル添付と同じ形で保存され、運営の申込一覧・Excel出力に入る。
 */
class IndividualEntryController extends Controller
{
    private const DRAFT = 'individual_entry_draft';

    public function __construct(private SheetEntryService $sheets, private PaymentGateways $payments) {}

    public function create(Request $request): View
    {
        $meets = $this->openMeets();
        $draft = $request->session()->get(self::DRAFT, []);
        $meetId = (int) ($request->query('meet') ?: old('meet_id', $draft['meet_id'] ?? 0));
        $meet = $meets->firstWhere('id', $meetId) ?? $meets->first();

        return view('individual-entry.create', [
            'meets' => $meets,
            'meet' => $meet,
            'events' => $meet?->events()->orderBy('sort_order')->get() ?? collect(),
            'draft' => $meet && ($draft['meet_id'] ?? null) === $meet->id ? $draft : [],
        ]);
    }

    /**
     * 入力内容を確かめて確認画面へ。
     */
    public function review(Request $request): RedirectResponse
    {
        $meet = Meet::find($request->integer('meet_id'));
        if (! $meet || ! $meet->is_published || ! $meet->isAcceptingEntries()) {
            return back()->withInput()->withErrors(['meet_id' => '受付中の大会を選んでください。']);
        }

        $athlete = AthleteRules::normalize($request->only(['last_name', 'first_name', 'kana', 'gender', 'category', 'grade', 'association']));
        $contact = $request->validate([
            'team_name' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'pr' => ['nullable', 'string', 'max:100'],
            'agree' => ['accepted'],
        ], [], ['team_name' => '所属', 'email' => 'メールアドレス', 'phone' => '電話番号', 'agree' => '個人情報の取り扱いへの同意']);
        $rules = AthleteRules::rules();
        $rules['category'][0] = 'required'; // 参加料が種別で決まるので必須
        Validator::make($athlete, $rules, [], ['last_name' => '姓', 'first_name' => '名', 'kana' => 'フリガナ', 'gender' => '性別', 'category' => '種別', 'grade' => '学年', 'association' => '所属陸協'])->validate();

        $existing = User::where('email', $contact['email'])->first();
        if ($existing && ! $existing->isSheetOnly()) {
            return back()->withInput()->withErrors(['email' => 'このメールアドレスは団体のWebエントリーに登録されています。別のメールアドレスを入力するか、団体申し込みからお申し込みください。']);
        }

        $rows = $this->rows($meet, $athlete, (array) $request->input('events', []), $contact['pr'] ?? null);

        $request->session()->put(self::DRAFT, [
            'meet_id' => $meet->id,
            'athlete' => $athlete,
            'events' => collect($rows)->map(fn ($r) => ['event_id' => $r['event']->id, 'record' => $r['record']])->all(),
        ] + $contact);

        return redirect()->route('individual-entry.confirm');
    }

    public function confirm(Request $request): View|RedirectResponse
    {
        [$meet, $draft, $rows] = $this->loadDraft($request);
        if (! $meet) {
            return redirect()->route('individual-entry.create');
        }

        return view('individual-entry.confirm', [
            'meet' => $meet,
            'draft' => $draft,
            'rows' => $rows,
            'total' => collect($rows)->sum('fee'),
            'methods' => PaymentGateways::available(),
            'receiptDefault' => $draft['athlete']['last_name'].' '.$draft['athlete']['first_name'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$meet, $draft, $rows] = $this->loadDraft($request);
        if (! $meet) {
            return redirect()->route('individual-entry.create');
        }
        if (! $meet->isAcceptingEntries()) {
            return redirect()->route('individual-entry.create')->withErrors(['meet_id' => $meet->name.'は現在エントリーを受け付けていません。']);
        }
        if (! $request->boolean('terms')) {
            return redirect()->route('individual-entry.confirm')->withErrors(['terms' => '競技者登録と参加料についての確認にチェックを入れてください。']);
        }
        $method = (string) $request->input('payment_method', Entry::METHOD_CARD);
        if (! array_key_exists($method, PaymentGateways::available())) {
            return redirect()->route('individual-entry.confirm')->withErrors(['payment_method' => 'お支払い方法を選んでください。']);
        }

        try {
            $entry = $this->sheets->submit($this->guestUser($draft), $meet, $rows, $method, null, Entry::SOURCE_FORM);
        } catch (ValidationException $e) {
            return redirect()->route('individual-entry.create', ['meet' => $meet->id])->withErrors($e->errors());
        }
        $entry->update(['receipt_name' => mb_substr(trim((string) $request->input('receipt_name')), 0, 100) ?: null]);
        $request->session()->forget(self::DRAFT);

        try {
            $session = $this->payments->forEntry($entry)->createCheckout(
                $entry,
                URL::signedRoute('sheet.status', $entry),
                URL::signedRoute('sheet.cancelled', $entry),
            );
        } catch (\Throwable $e) {
            report($e);
            $entry->update(['status' => Entry::STATUS_CANCELLED]);

            return redirect()->route('individual-entry.create', ['meet' => $meet->id])->withErrors(['entry' => '決済ページを開けませんでした。時間をおいてもう一度お試しください。']);
        }
        $entry->update(['payment_session_id' => $session->id]);

        return redirect()->away($session->url);
    }

    /**
     * 種目と申請タイムを確かめ、エクセル添付と同じ形の行にする。
     *
     * @param  array<int, array{event_id?: mixed, record?: mixed}>  $input
     * @return list<array<string, mixed>>
     */
    private function rows(Meet $meet, array $athlete, array $input, ?string $pr): array
    {
        $events = $meet->events()->get()->keyBy('id');
        $rows = [];
        $errors = [];
        foreach (array_values($input) as $i => $slot) {
            $eventId = (int) ($slot['event_id'] ?? 0);
            $record = RaceTime::normalize($slot['record'] ?? null);
            if (! $eventId && $record === null) {
                continue;
            }
            $key = 'events.'.$i;
            $event = $events->get($eventId);
            if (! $event) {
                $errors[$key.'.event_id'] = '申込み種目を選んでください。';

                continue;
            }
            if (! $event->allowsGender((string) ($athlete['gender'] ?? ''))) {
                $errors[$key.'.event_id'] = $event->name.'には出場できません（性別）。';
            }
            if (isset($rows[$event->id])) {
                $errors[$key.'.event_id'] = $event->name.'が重複しています。';
            }
            if ($record === null || ! RaceTime::isValid($record)) {
                $errors[$key.'.record'] = $event->name.'の申請タイムを 14\'30 の形（数字だけでも可）で入れてください。';
            }
            $rows[$event->id] = [
                'line' => $i + 1,
                'athlete' => $athlete,
                'event' => $event,
                'eventName' => $event->name,
                'record' => $record,
                'best' => null,
                'pr' => $pr === null || trim($pr) === '' ? null : trim($pr),
                'fee' => $meet->feeFor($athlete['category'] ?? null, $event),
                'errors' => [],
            ];
        }
        if ($rows === [] && $errors === []) {
            $errors['events'] = '申込み種目を1つ以上選んでください。';
        }
        if (count($rows) > $meet->max_events_per_athlete) {
            $errors['events'] = '1人'.$meet->max_events_per_athlete.'種目までです。';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return array_values($rows);
    }

    /**
     * @return array{0: ?Meet, 1: array<string, mixed>, 2: list<array<string, mixed>>}
     */
    private function loadDraft(Request $request): array
    {
        $draft = $request->session()->get(self::DRAFT);
        $meet = $draft ? Meet::find($draft['meet_id']) : null;
        if (! $meet) {
            return [null, [], []];
        }

        return [$meet, $draft, $this->rows($meet, $draft['athlete'], $draft['events'], $draft['pr'] ?? null)];
    }

    /**
     * ログインしない申込者の記録（同じメールアドレスなら前回のものを使う）。
     */
    private function guestUser(array $draft): User
    {
        $user = User::where('email', $draft['email'])->whereIn('type', [User::TYPE_GUEST, User::TYPE_SHEET])->first()
            ?? new User(['type' => User::TYPE_GUEST, 'email' => $draft['email'], 'password' => Str::random(40)]);
        $user->fill([
            'name' => $draft['athlete']['last_name'].' '.$draft['athlete']['first_name'],
            'team_name' => $draft['team_name'] ?? null,
            'phone' => $draft['phone'],
        ])->save();

        return $user;
    }

    /**
     * @return Collection<int, Meet>
     */
    private function openMeets(): Collection
    {
        return Meet::where('is_published', true)->orderBy('held_on')->get()->filter->isAcceptingEntries()->values();
    }
}
