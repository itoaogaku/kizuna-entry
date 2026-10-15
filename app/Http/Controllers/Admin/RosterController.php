<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Entry;
use App\Models\EntryItem;
use App\Models\Meet;
use App\Models\User;
use App\Support\EntryOrder;
use App\Support\RaceTime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 全団体・個人の申込を、種目ごとに目標タイム順（同タイムは自己ベスト順）で一覧する。
 */
class RosterController extends Controller
{
    private const STATUS_FILTERS = [
        'paid' => '入金済み',
        'active' => '入金済み＋決済待ち',
        'all' => 'すべて（取消を含む）',
    ];

    public function index(Request $request, Meet $meet): View
    {
        $status = array_key_exists($request->query('status'), self::STATUS_FILTERS) ? $request->query('status') : 'paid';
        $eventId = $request->integer('event') ?: null;
        $teamId = $request->integer('team') ?: null;

        $items = EntryOrder::sort(EntryItem::query()
            ->with('entry.user', 'athlete', 'event')
            ->whereHas('entry', fn ($q) => $q->where('meet_id', $meet->id)
                ->when($status === 'paid', fn ($q) => $q->where('status', Entry::STATUS_PAID))
                ->when($status === 'active', fn ($q) => $q->whereIn('status', [Entry::STATUS_PAID, Entry::STATUS_PENDING]))
                ->when($teamId, fn ($q) => $q->where('user_id', $teamId)))
            ->when($eventId, fn ($q) => $q->where('event_id', $eventId))
            ->get());

        $teams = User::whereHas('entries', fn ($q) => $q->where('meet_id', $meet->id))
            ->orderBy('type')->orderBy('team_kana')->orderBy('team_name')->orderBy('name')
            ->get();

        return view('admin.roster.index', [
            'meet' => $meet,
            'events' => $meet->events()->get(),
            'teams' => $teams,
            'groups' => $items->groupBy('event_id'),
            'tied' => EntryOrder::tiedIds($items),
            'status' => $status,
            'statusFilters' => self::STATUS_FILTERS,
            'eventId' => $eventId,
            'teamId' => $teamId,
            'total' => $items->count(),
            'missingBest' => $items->whereNull('personal_best')->count(),
        ]);
    }

    /**
     * 自己ベストを運営が入力する（リクマガなどで確認した記録）。
     */
    public function updateBest(Request $request, EntryItem $item): RedirectResponse
    {
        $best = RaceTime::normalize($request->input('personal_best'));
        if ($best !== null && ! RaceTime::isValid($best)) {
            return back()->withErrors(['personal_best' => $item->athlete->fullName().'：自己ベスト「'.$best.'」の形が正しくありません（例：14\'45）。']);
        }
        $item->update(['personal_best' => $best]);

        return back()->with('status', $item->athlete->fullName().'の自己ベストを'.($best ? '「'.$best.'」にしました。' : '消しました。'));
    }
}
