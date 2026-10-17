<?php

namespace App\Http\Controllers;

use App\Models\Entry;
use App\Models\Meet;
use App\Models\User;
use App\Payments\PaymentGateways;
use App\Services\EntryService;
use App\Services\SheetEntryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * エクセル添付でのエントリー（ログインなしでも使える）。
 * 今までのエントリーシートを添付 → 読み込んだ内容を確認 → 支払い、の流れ。
 * 読み込んだ内容は Webエントリーと同じ申込データになり、運営の申込一覧・Excel出力にそのまま入る。
 */
class SheetEntryController extends Controller
{
    private const DRAFT = 'sheet_entry_draft';

    public function __construct(
        private SheetEntryService $sheets,
        private EntryService $entries,
        private PaymentGateways $payments,
    ) {}

    public function create(Request $request): View
    {
        $user = $request->user();

        return view('sheet.create', [
            'meets' => Meet::where('is_published', true)->orderBy('held_on')->get()->filter->isAcceptingEntries()->values(),
            'user' => $user,
            'old' => $request->session()->get(self::DRAFT, []),
        ]);
    }

    public function upload(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'meet_id' => ['required', 'integer', 'exists:meets,id'],
            'team_name' => ['nullable', 'string', 'max:100'],
            'name' => [$user ? 'nullable' : 'required', 'string', 'max:100'],
            'email' => [$user ? 'nullable' : 'required', 'email', 'max:255'],
            'phone' => [$user ? 'nullable' : 'required', 'string', 'max:20'],
            'file' => ['required', 'file', 'max:5120', 'extensions:xlsx,xls'],
            'agree' => [$user ? 'nullable' : 'accepted'],
        ]);
        $meet = Meet::findOrFail($data['meet_id']);
        if (! $meet->isAcceptingEntries()) {
            return back()->withInput()->withErrors(['meet_id' => $meet->name.'は現在エントリーを受け付けていません。']);
        }
        if (! $user && ($existing = User::where('email', $data['email'])->first()) && ! $existing->isSheetOnly()) {
            return back()->withInput()->withErrors(['email' => 'このメールアドレスはWebエントリーに登録済みです。ログインしてから、この画面でエクセルを添付してください。']);
        }

        $file = $request->file('file');
        $path = $file->storeAs('sheets/uploads', Str::uuid().'.'.strtolower($file->getClientOriginalExtension()));

        try {
            $parsed = $this->sheets->parse($meet, Storage::path($path), $file->getClientOriginalExtension());
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['file' => $e->getMessage()]);
        } catch (\Throwable) {
            return back()->withInput()->withErrors(['file' => 'エクセルを読み込めませんでした。エントリーシート（.xlsx）を添付してください。']);
        }
        if ($parsed['rows'] === []) {
            return back()->withInput()->withErrors(['file' => '申込み欄に選手が書かれていませんでした。']);
        }

        $request->session()->put(self::DRAFT, [
            'meet_id' => $meet->id,
            'team_name' => ($data['team_name'] ?? null) ?: $parsed['teamName'],
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'path' => $path,
            'extension' => $file->getClientOriginalExtension(),
            'filename' => $file->getClientOriginalName(),
        ]);

        return redirect()->route('sheet.preview');
    }

    /**
     * 読み込んだ内容の確認画面。エラーがあればエクセルを直して添付し直してもらう。
     */
    public function preview(Request $request): View|RedirectResponse
    {
        $draft = $request->session()->get(self::DRAFT);
        if (! $draft || ! Storage::exists($draft['path'])) {
            return redirect()->route('sheet.create');
        }
        $meet = Meet::findOrFail($draft['meet_id']);
        $parsed = $this->sheets->parse($meet, Storage::path($draft['path']), $draft['extension']);
        $valid = collect($parsed['rows'])->every(fn ($r) => $r['errors'] === []);

        return view('sheet.preview', [
            'meet' => $meet,
            'draft' => $draft,
            'rows' => $parsed['rows'],
            'valid' => $valid,
            'total' => collect($parsed['rows'])->sum('fee'),
            'methods' => PaymentGateways::available(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $draft = $request->session()->get(self::DRAFT);
        if (! $draft || ! Storage::exists($draft['path'])) {
            return redirect()->route('sheet.create');
        }
        $meet = Meet::findOrFail($draft['meet_id']);
        if (! $meet->isAcceptingEntries()) {
            return redirect()->route('sheet.create')->withErrors(['meet_id' => $meet->name.'は現在エントリーを受け付けていません。']);
        }
        if (! $request->boolean('terms')) {
            return redirect()->route('sheet.preview')->withErrors(['terms' => '競技者登録と参加料についての確認にチェックを入れてください。']);
        }
        $method = (string) $request->input('payment_method', Entry::METHOD_CARD);
        if (! array_key_exists($method, PaymentGateways::available())) {
            return redirect()->route('sheet.preview')->withErrors(['payment_method' => 'お支払い方法を選んでください。']);
        }

        $parsed = $this->sheets->parse($meet, Storage::path($draft['path']), $draft['extension']);
        if (collect($parsed['rows'])->contains(fn ($r) => $r['errors'] !== [])) {
            return redirect()->route('sheet.preview');
        }

        $user = $request->user() ?? $this->sheetUser($draft);
        try {
            $entry = $this->sheets->submit($user, $meet, $parsed['rows'], $method, null);
        } catch (ValidationException $e) {
            return redirect()->route('sheet.preview')->withErrors($e->errors());
        }

        // 添付されたエクセルは運営が後から見られるように残す
        $saved = 'sheets/entry-'.$entry->id.'.'.strtolower($draft['extension']);
        Storage::move($draft['path'], $saved);
        $entry->update(['sheet_path' => $saved]);
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

            return redirect()->route('sheet.create')->withErrors(['entry' => '決済ページを開けませんでした。時間をおいてもう一度お試しください。']);
        }
        $entry->update(['payment_session_id' => $session->id]);

        return redirect()->away($session->url);
    }

    /**
     * 申込の状態（支払い後の戻り先・メールのリンク）。ログインなしで見られるよう署名付きURLにしている。
     */
    public function status(Request $request, Entry $entry): View
    {
        $this->checkSignature($request);
        if ($entry->status === Entry::STATUS_PENDING) {
            $this->entries->syncPayment($entry);
        }

        return view('sheet.status', ['entry' => $entry->load('meet', 'user', 'items.athlete', 'items.event')]);
    }

    /**
     * 支払いを続ける（カードの決済ページ、またはコンビニの払込票へ）。
     */
    public function pay(Request $request, Entry $entry): RedirectResponse
    {
        $this->checkSignature($request);
        $session = $this->entries->syncPayment($entry);
        if ($session?->isAwaiting() && $session->instructionsUrl) {
            return redirect()->away($session->instructionsUrl);
        }
        if ($entry->status === Entry::STATUS_PENDING && $session?->url && ! $session->closed) {
            return redirect()->away($session->url);
        }

        return redirect()->to(URL::signedRoute('sheet.status', $entry))
            ->withErrors(['entry' => $entry->isPaid() ? '入金済みです。' : '決済ページの有効期限が切れました。お手数ですが、もう一度エクセルを添付してお申し込みください。']);
    }

    /**
     * 決済ページで「戻る」を押したとき。
     */
    public function cancelled(Request $request, Entry $entry): RedirectResponse
    {
        $this->checkSignature($request);
        if ($this->entries->cancelPending($entry)) {
            return redirect()->route('sheet.create')
                ->with('status', 'お支払いを中止したため、申込みを取り消しました。もう一度エクセルを添付してお申し込みください。');
        }

        return redirect()->to(URL::signedRoute('sheet.status', $entry));
    }

    /**
     * 決済サービスが戻り先URLに付ける値（session_id など）は、署名の確認から外す。
     */
    private function checkSignature(Request $request): void
    {
        abort_unless($request->hasValidSignatureWhileIgnoring(['session_id', 'hmac']), 403);
    }

    /**
     * ログインしていない人のための、申込者の記録（同じメールアドレスなら前回のものを使う）。
     */
    private function sheetUser(array $draft): User
    {
        $user = User::where('email', $draft['email'])->where('type', User::TYPE_SHEET)->first()
            ?? new User(['type' => User::TYPE_SHEET, 'email' => $draft['email'], 'password' => Str::random(40)]);
        $user->fill([
            'name' => $draft['name'],
            'team_name' => $draft['team_name'],
            'phone' => $draft['phone'],
        ])->save();

        return $user;
    }
}
