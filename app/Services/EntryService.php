<?php

namespace App\Services;

use App\Mail\EntryPaidMail;
use App\Models\Athlete;
use App\Models\Entry;
use App\Models\EntryItem;
use App\Models\Event;
use App\Models\Meet;
use App\Models\User;
use App\Payments\CheckoutSession;
use App\Payments\PaymentGateways;
use App\Support\RaceTime;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class EntryService
{
    public function __construct(private PaymentGateways $payments) {}

    /**
     * フォームの入力（選手ごとの種目選択）を検証し、明細の一覧に変換する。
     *
     * @param  array<int|string, array{selected?: mixed, pr?: mixed, events?: array<int, array{event_id?: mixed, record?: mixed}>}>  $input
     * @return Collection<int, array{athlete: Athlete, event: Event, record: string, pr: ?string}>
     */
    public function buildSelections(User $user, Meet $meet, array $input): Collection
    {
        $athletes = $user->athletes()->where('is_active', true)->get()->keyBy('id');
        $events = $meet->events()->get()->keyBy('id');
        $alreadyEntered = $this->enteredPairs($meet);
        $errors = [];
        $selections = collect();

        foreach ($input as $athleteId => $row) {
            if (empty($row['selected'])) {
                continue;
            }
            $athlete = $athletes->get((int) $athleteId);
            if (! $athlete) {
                $errors["items.$athleteId"] = '選択された選手が見つかりません。';

                continue;
            }

            $chosen = collect($row['events'] ?? [])
                ->filter(fn ($e) => ! empty($e['event_id']))
                ->values();

            if ($chosen->isEmpty()) {
                $errors["items.$athleteId"] = $athlete->fullName().'：種目を選んでください。';

                continue;
            }
            if ($chosen->count() > $meet->max_events_per_athlete) {
                $errors["items.$athleteId"] = $athlete->fullName().'：1人'.$meet->max_events_per_athlete.'種目までです。';

                continue;
            }
            if ($chosen->pluck('event_id')->unique()->count() !== $chosen->count()) {
                $errors["items.$athleteId"] = $athlete->fullName().'：同じ種目が重複しています。';

                continue;
            }

            $pr = trim((string) ($row['pr'] ?? ''));

            foreach ($chosen as $i => $e) {
                $event = $events->get((int) $e['event_id']);
                $key = "items.$athleteId.events.$i";
                if (! $event) {
                    $errors[$key] = $athlete->fullName().'：種目が正しくありません。';

                    continue;
                }
                if (! $event->allowsGender($athlete->gender)) {
                    $errors[$key] = $athlete->fullName().'：'.$event->name.'には出場できません（性別）。';

                    continue;
                }
                if ($alreadyEntered->contains($athlete->id.'-'.$event->id)) {
                    $errors[$key] = $athlete->fullName().'：'.$event->name.'はすでにエントリー済みです。';

                    continue;
                }
                $record = RaceTime::normalize($e['record'] ?? null);
                if ($record === null) {
                    $errors[$key] = $athlete->fullName().'：'.$event->name.'の申請タイムを入力してください。';

                    continue;
                }
                if (! RaceTime::isValid($record)) {
                    $errors[$key] = $athlete->fullName().'：申請タイム「'.$record.'」の形が正しくありません（例：14\'30）。';

                    continue;
                }
                if (mb_strlen($pr) > 100) {
                    $errors["items.$athleteId.pr"] = $athlete->fullName().'：PRしたい一言は100文字以内で入力してください。';

                    continue;
                }

                $selections->push([
                    'athlete' => $athlete,
                    'event' => $event,
                    'record' => $record,
                    'pr' => $pr === '' ? null : $pr,
                ]);
            }
        }

        if ($errors === [] && $selections->isEmpty()) {
            $errors['items'] = '出場する選手を1人以上選んでください。';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $selections;
    }

    /**
     * 決済待ちのエントリーを作成する。定員はここで最終確認する。
     *
     * @param  Collection<int, array{athlete: Athlete, event: Event, record: string, pr: ?string}>  $selections
     */
    public function createPendingEntry(User $user, Meet $meet, Collection $selections, string $method = Entry::METHOD_CARD): Entry
    {
        return DB::transaction(function () use ($user, $meet, $selections, $method) {
            $events = Event::whereIn('id', $selections->pluck('event.id')->unique())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // 他チームが同時に申し込んだ場合に備えて、重複と定員をもう一度確認する
            $alreadyEntered = $this->enteredPairs($meet);
            foreach ($selections as $s) {
                if ($alreadyEntered->contains($s['athlete']->id.'-'.$s['event']->id)) {
                    throw ValidationException::withMessages([
                        'items' => $s['athlete']->fullName().'：'.$s['event']->name.'はすでにエントリー済みです。',
                    ]);
                }
            }
            foreach ($selections->groupBy(fn ($s) => $s['event']->id) as $eventId => $group) {
                $event = $events[$eventId];
                if ($event->capacity !== null && $event->reservedCount() + $group->count() > $event->capacity) {
                    $left = max(0, $event->capacity - $event->reservedCount());
                    throw ValidationException::withMessages([
                        'items' => $event->name.'は定員に達しています（残り'.$left.'人）。',
                    ]);
                }
            }

            $entry = $user->entries()->create([
                'meet_id' => $meet->id,
                'status' => Entry::STATUS_PENDING,
                'payment_method' => $method,
                'total_amount' => $selections->sum(fn ($s) => $events[$s['event']->id]->fee),
            ]);

            foreach ($selections as $s) {
                $entry->items()->create([
                    'athlete_id' => $s['athlete']->id,
                    'event_id' => $s['event']->id,
                    'declared_record' => $s['record'],
                    'pr_comment' => $s['pr'] ?? null,
                    'fee' => $events[$s['event']->id]->fee,
                ]);
            }

            return $entry;
        });
    }

    /**
     * 入金済みにする。すでに処理済みなら何もしない（Webhookは複数回届くことがある）。
     */
    public function markPaid(Entry $entry, ?string $paymentId): void
    {
        $changed = DB::transaction(function () use ($entry, $paymentId) {
            $fresh = Entry::whereKey($entry->id)->lockForUpdate()->first();
            if ($fresh->isPaid()) {
                return false;
            }
            $fresh->update([
                'status' => Entry::STATUS_PAID,
                'payment_id' => $paymentId,
                'paid_at' => now(),
            ]);

            return true;
        });

        if ($changed) {
            $entry->refresh();
            $mail = Mail::to($entry->user);
            if ($admin = config('services.kizuna.admin_email')) {
                $mail->bcc($admin);
            }
            $mail->send(new EntryPaidMail($entry));
        }
    }

    /**
     * 決済サービスに問い合わせて、エントリーの状態を合わせる（戻り画面・Webhook・支払い再開で使う）。
     * 支払い済み → 入金済みにしてメール、コンビニの番号発行 → 払込票URLを保存、期限切れ・キャンセル → 取消。
     */
    public function syncPayment(Entry $entry): ?CheckoutSession
    {
        if ($entry->status !== Entry::STATUS_PENDING || ! $entry->payment_session_id) {
            return null;
        }
        $session = $this->payments->forEntry($entry)->retrieveCheckout($entry->payment_session_id);

        if ($session->isPaid()) {
            $this->markPaid($entry, $session->paymentId);
        } elseif ($session->isAwaiting()) {
            $entry->update(['payment_id' => $session->paymentId, 'payment_instructions_url' => $session->instructionsUrl]);
        } elseif ($session->isAbandoned()) {
            $this->cancelByWebhook($entry);
        }
        $entry->refresh();

        return $session;
    }

    /**
     * 決済待ちのエントリーを取り消し、確保していた枠を空ける。
     */
    public function cancelPending(Entry $entry): bool
    {
        if ($entry->status !== Entry::STATUS_PENDING) {
            return false;
        }
        if ($entry->payment_session_id) {
            $gateway = $this->payments->forEntry($entry);
            $session = $gateway->retrieveCheckout($entry->payment_session_id);
            if ($session->isPaid()) {
                $this->markPaid($entry, $session->paymentId);

                return false;
            }
            if (! $gateway->expireCheckout($entry->payment_session_id)) {
                // コンビニ払いの番号発行後などは取り消さない
                return false;
            }
        }

        return Entry::whereKey($entry->id)
            ->where('status', Entry::STATUS_PENDING)
            ->update(['status' => Entry::STATUS_CANCELLED]) === 1;
    }

    /**
     * 決済ページが期限切れ・失敗になったときの取消（Webhook用）。
     */
    public function cancelByWebhook(Entry $entry): void
    {
        Entry::whereKey($entry->id)
            ->where('status', Entry::STATUS_PENDING)
            ->update(['status' => Entry::STATUS_CANCELLED]);
    }

    /**
     * 選手ごとの、過去の大会で入れた申請タイム（種目名ごとに最新のもの）。
     * エントリー画面で種目を選んだときに自動で入れ、入力の手間を減らす。
     *
     * @return array<int, array<string, string>>
     */
    public function previousTargets(User $user): array
    {
        $result = [];
        EntryItem::query()
            ->with('event')
            ->whereNotNull('declared_record')
            ->whereHas('entry', fn ($q) => $q->where('user_id', $user->id)->where('status', Entry::STATUS_PAID))
            ->orderBy('id')
            ->get()
            ->each(function (EntryItem $item) use (&$result) {
                $result[$item->athlete_id][$item->event->name] = $item->declared_record;
            });

        return $result;
    }

    /**
     * 「前回と同じ内容で申し込む」用に、前回の大会（入金済み）の内容をこの大会の入力欄の形にする。
     * 種目は名前が同じものに置き換え、対象外の選手・この大会で申込済みの組み合わせは除く。
     *
     * @return array{0: ?Meet, 1: array<int, array<string, mixed>>} [前回の大会, 入力欄の内容]
     */
    public function previousEntryDraft(User $user, Meet $meet): array
    {
        $previous = Meet::query()
            ->where('id', '!=', $meet->id)
            ->whereHas('entries', fn ($q) => $q->where('user_id', $user->id)->where('status', Entry::STATUS_PAID))
            ->orderByDesc('held_on')
            ->first();
        if (! $previous) {
            return [null, []];
        }

        $events = $meet->events()->get()->keyBy('name');
        $entered = $this->enteredPairs($meet);
        $draft = [];
        $items = EntryItem::query()
            ->with('athlete', 'event')
            ->whereHas('entry', fn ($q) => $q->where('user_id', $user->id)->where('meet_id', $previous->id)->where('status', Entry::STATUS_PAID))
            ->orderBy('id')
            ->get();
        foreach ($items as $item) {
            $event = $events->get($item->event->name);
            if (! $event || ! $item->athlete->is_active || ! $event->allowsGender($item->athlete->gender)
                || $entered->contains($item->athlete_id.'-'.$event->id)
                || count($draft[$item->athlete_id]['events'] ?? []) >= $meet->max_events_per_athlete) {
                continue;
            }
            $draft[$item->athlete_id]['selected'] = '1';
            $draft[$item->athlete_id]['pr'] = $item->pr_comment;
            $draft[$item->athlete_id]['events'][] = ['event_id' => $event->id, 'record' => $item->declared_record];
        }

        return [$previous, $draft];
    }

    /**
     * この大会ですでに申し込まれている「選手ID-種目ID」の組（決済待ち・入金済み）。
     *
     * @return Collection<int, string>
     */
    public function enteredPairs(Meet $meet): Collection
    {
        return EntryItem::query()
            ->whereHas('entry', fn ($q) => $q->where('meet_id', $meet->id)
                ->whereIn('status', [Entry::STATUS_PENDING, Entry::STATUS_PAID]))
            ->get(['athlete_id', 'event_id'])
            ->map(fn ($i) => $i->athlete_id.'-'.$i->event_id);
    }
}
