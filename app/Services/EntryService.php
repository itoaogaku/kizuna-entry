<?php

namespace App\Services;

use App\Mail\EntryPaidMail;
use App\Models\Athlete;
use App\Models\Entry;
use App\Models\EntryItem;
use App\Models\Event;
use App\Models\Meet;
use App\Models\User;
use App\Payments\PaymentGateway;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class EntryService
{
    public function __construct(private PaymentGateway $payments) {}

    /**
     * フォームの入力（選手ごとの種目選択）を検証し、明細の一覧に変換する。
     *
     * @param  array<int|string, array{selected?: mixed, events?: array<int, array{event_id?: mixed, record?: mixed}>}>  $input
     * @return Collection<int, array{athlete: Athlete, event: Event, record: ?string}>
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
                $record = trim((string) ($e['record'] ?? ''));
                if (mb_strlen($record) > 20) {
                    $errors[$key] = $athlete->fullName().'：申告記録は20文字以内で入力してください。';

                    continue;
                }

                $selections->push([
                    'athlete' => $athlete,
                    'event' => $event,
                    'record' => $record === '' ? null : mb_convert_kana($record, 'as'),
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
     * @param  Collection<int, array{athlete: Athlete, event: Event, record: ?string}>  $selections
     */
    public function createPendingEntry(User $user, Meet $meet, Collection $selections): Entry
    {
        return DB::transaction(function () use ($user, $meet, $selections) {
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
                'total_amount' => $selections->sum(fn ($s) => $events[$s['event']->id]->fee),
            ]);

            foreach ($selections as $s) {
                $entry->items()->create([
                    'athlete_id' => $s['athlete']->id,
                    'event_id' => $s['event']->id,
                    'declared_record' => $s['record'],
                    'fee' => $events[$s['event']->id]->fee,
                ]);
            }

            return $entry;
        });
    }

    /**
     * 入金済みにする。すでに処理済みなら何もしない（Webhookは複数回届くことがある）。
     */
    public function markPaid(Entry $entry, ?string $paymentIntentId): void
    {
        $changed = DB::transaction(function () use ($entry, $paymentIntentId) {
            $fresh = Entry::whereKey($entry->id)->lockForUpdate()->first();
            if ($fresh->isPaid()) {
                return false;
            }
            $fresh->update([
                'status' => Entry::STATUS_PAID,
                'stripe_payment_intent_id' => $paymentIntentId,
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
     * 決済待ちのエントリーを取り消し、確保していた枠を空ける。
     */
    public function cancelPending(Entry $entry): bool
    {
        if ($entry->status !== Entry::STATUS_PENDING) {
            return false;
        }
        if ($entry->stripe_session_id) {
            $session = $this->payments->retrieveCheckout($entry->stripe_session_id);
            if ($session->isPaid()) {
                $this->markPaid($entry, $session->paymentIntentId);

                return false;
            }
            if (! $this->payments->expireCheckout($entry->stripe_session_id)) {
                // コンビニ払いの番号発行後などは取り消さない
                return false;
            }
        }

        return Entry::whereKey($entry->id)
            ->where('status', Entry::STATUS_PENDING)
            ->update(['status' => Entry::STATUS_CANCELLED]) === 1;
    }

    /**
     * Stripeの決済ページが期限切れ・失敗になったときの取消（Webhook用）。
     */
    public function cancelByWebhook(Entry $entry): void
    {
        Entry::whereKey($entry->id)
            ->where('status', Entry::STATUS_PENDING)
            ->update(['status' => Entry::STATUS_CANCELLED]);
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
