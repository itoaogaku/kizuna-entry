<?php

namespace App\Http\Controllers;

use App\Models\Entry;
use App\Models\Meet;
use App\Payments\PaymentGateway;
use App\Services\EntryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EntryController extends Controller
{
    public function __construct(private EntryService $entries, private PaymentGateway $payments) {}

    public function index(Request $request): View
    {
        $entries = $request->user()->entries()
            ->with('meet', 'items')
            ->latest()
            ->get();

        return view('entries.index', compact('entries'));
    }

    /**
     * 選手一覧から出場する選手をチェックして種目を選ぶ画面。
     */
    public function create(Request $request, Meet $meet): View|RedirectResponse
    {
        if ($redirect = $this->rejectIfClosed($meet)) {
            return $redirect;
        }

        $athletes = $request->user()->athletes()
            ->where('is_active', true)
            ->orderBy('gender')
            ->orderBy('kana')
            ->get();

        $entered = $this->entries->enteredPairs($meet);
        $events = $meet->events()->get();

        return view('entries.create', [
            'meet' => $meet,
            'athletes' => $athletes,
            'events' => $events,
            'remaining' => $events->mapWithKeys(fn ($e) => [
                $e->id => $e->capacity === null ? null : max(0, $e->capacity - $e->reservedCount()),
            ]),
            'enteredByAthlete' => $entered
                ->map(fn ($pair) => explode('-', $pair))
                ->groupBy(0)
                ->map(fn ($pairs) => $pairs->pluck(1)->map(fn ($id) => (int) $id)),
            'old' => session("entry_draft.{$meet->id}", []),
        ]);
    }

    public function review(Request $request, Meet $meet): RedirectResponse
    {
        if ($redirect = $this->rejectIfClosed($meet)) {
            return $redirect;
        }

        $input = (array) $request->input('items', []);
        $request->session()->put("entry_draft.{$meet->id}", $input);
        $this->entries->buildSelections($request->user(), $meet, $input);

        return redirect()->route('entries.confirm', $meet);
    }

    public function confirm(Request $request, Meet $meet): View|RedirectResponse
    {
        if ($redirect = $this->rejectIfClosed($meet)) {
            return $redirect;
        }
        $input = $request->session()->get("entry_draft.{$meet->id}");
        if (! $input) {
            return redirect()->route('entries.create', $meet);
        }

        try {
            $selections = $this->entries->buildSelections($request->user(), $meet, $input);
        } catch (ValidationException $e) {
            return redirect()->route('entries.create', $meet)->withErrors($e->errors());
        }

        return view('entries.confirm', [
            'meet' => $meet,
            'selections' => $selections,
            'total' => $selections->sum(fn ($s) => $s['event']->fee),
        ]);
    }

    /**
     * エントリーを確定し、Stripeの決済ページへ移動する。
     */
    public function store(Request $request, Meet $meet): RedirectResponse
    {
        if ($redirect = $this->rejectIfClosed($meet)) {
            return $redirect;
        }
        $input = $request->session()->get("entry_draft.{$meet->id}");
        if (! $input) {
            return redirect()->route('entries.create', $meet);
        }

        try {
            $selections = $this->entries->buildSelections($request->user(), $meet, $input);
            $entry = $this->entries->createPendingEntry($request->user(), $meet, $selections);
        } catch (ValidationException $e) {
            return redirect()->route('entries.create', $meet)->withErrors($e->errors());
        }
        $request->session()->forget("entry_draft.{$meet->id}");

        return $this->redirectToCheckout($entry);
    }

    public function show(Request $request, Entry $entry): View
    {
        $this->authorizeOwner($request, $entry);
        $entry->load('meet', 'items.athlete', 'items.event');

        return view('entries.show', compact('entry'));
    }

    /**
     * Stripeでの支払い後に戻ってくる画面。Webhookより先に着いた場合もここで入金を確認する。
     */
    public function complete(Request $request, Entry $entry): View
    {
        $this->authorizeOwner($request, $entry);

        if (! $entry->isPaid() && $entry->stripe_session_id
            && $request->query('session_id') === $entry->stripe_session_id) {
            $session = $this->payments->retrieveCheckout($entry->stripe_session_id);
            if ($session->isPaid()) {
                $this->entries->markPaid($entry, $session->paymentIntentId);
                $entry->refresh();
            }
        }

        return view('entries.complete', ['entry' => $entry->load('meet')]);
    }

    /**
     * Stripeの決済ページで「戻る」を押したとき。枠を空けて選択画面に戻す。
     */
    public function checkoutCancelled(Request $request, Entry $entry): RedirectResponse
    {
        $this->authorizeOwner($request, $entry);
        $entry->load('items');

        if ($this->entries->cancelPending($entry)) {
            // 選び直しやすいよう、取り消した内容を入力欄に戻す
            $draft = [];
            foreach ($entry->items as $item) {
                $draft[$item->athlete_id]['selected'] = '1';
                $draft[$item->athlete_id]['pr'] = $item->pr_comment;
                $draft[$item->athlete_id]['events'][] = [
                    'event_id' => $item->event_id,
                    'record' => $item->declared_record,
                    'best' => $item->personal_best,
                ];
            }
            $request->session()->put("entry_draft.{$entry->meet_id}", $draft);

            return redirect()->route('entries.create', $entry->meet_id)
                ->with('status', 'お支払いを中止しました。内容を確認して、もう一度お申し込みください。');
        }

        return redirect()->route('entries.show', $entry);
    }

    /**
     * 決済待ちのエントリーの支払いを再開する。
     */
    public function pay(Request $request, Entry $entry): RedirectResponse
    {
        $this->authorizeOwner($request, $entry);
        abort_unless($entry->status === Entry::STATUS_PENDING, 404);

        if ($entry->stripe_session_id) {
            $session = $this->payments->retrieveCheckout($entry->stripe_session_id);
            if ($session->isPaid()) {
                $this->entries->markPaid($entry, $session->paymentIntentId);

                return redirect()->route('entries.show', $entry);
            }
            if ($session->url) {
                return redirect()->away($session->url);
            }
        }

        return redirect()->route('entries.show', $entry)
            ->withErrors(['entry' => '決済ページの有効期限が切れました。このエントリーを取り消して、もう一度お申し込みください。']);
    }

    public function cancel(Request $request, Entry $entry): RedirectResponse
    {
        $this->authorizeOwner($request, $entry);

        if (! $this->entries->cancelPending($entry)) {
            return redirect()->route('entries.show', $entry)
                ->withErrors(['entry' => 'このエントリーは取り消せません。入金済みの場合は運営までご連絡ください。']);
        }

        return redirect()->route('entries.index')->with('status', 'エントリーを取り消しました。');
    }

    private function redirectToCheckout(Entry $entry): RedirectResponse
    {
        try {
            $session = $this->payments->createCheckout(
                $entry,
                route('entries.complete', $entry),
                route('entries.checkout-cancelled', $entry),
            );
        } catch (\Throwable $e) {
            // 決済ページを作れなかったときは、確保した枠をすぐに空ける
            report($e);
            $entry->update(['status' => Entry::STATUS_CANCELLED]);

            return redirect()->route('entries.create', $entry->meet_id)
                ->withErrors(['entry' => '決済ページを開けませんでした。時間をおいてもう一度お試しください。']);
        }
        $entry->update(['stripe_session_id' => $session->id]);

        return redirect()->away($session->url);
    }

    private function rejectIfClosed(Meet $meet): ?RedirectResponse
    {
        if ($meet->isAcceptingEntries()) {
            return null;
        }

        return redirect()->route('home')->withErrors(['meet' => $meet->name.'は現在エントリーを受け付けていません。']);
    }

    private function authorizeOwner(Request $request, Entry $entry): void
    {
        abort_unless($entry->user_id === $request->user()->id, 404);
    }
}
