<?php

namespace App\Http\Controllers;

use App\Models\Entry;
use App\Models\Meet;
use App\Models\Race;
use App\Services\GradeAdvance;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * 公開サイトの「大会開催」ページ。年度ごとの開催予定と、各大会の資料・エントリーへの入口。
 */
class SiteController extends Controller
{
    public function meets(Request $request): View
    {
        $published = Meet::with('events')->where('is_published', true)->orderBy('held_on')->get();
        $years = $published->map->fiscalYear()->unique()->sortDesc()->values();
        $current = GradeAdvance::fiscalYear();
        $year = $request->integer('year') ?: ($years->contains($current) ? $current : ($years->first() ?? $current));

        $meets = $published->filter(fn (Meet $m) => $m->fiscalYear() === $year)->values();

        return view('site.meets', [
            'year' => $year,
            'years' => $years,
            'meets' => $meets,
            // 大会ごとの詳細は新しい順（今のサイトと同じ並び）
            'details' => $meets->sortByDesc('held_on')->values(),
            'next' => $published->first(fn (Meet $m) => $m->stage() !== 'finished'),
            'pastResults' => $this->pastResults($years, $current),
        ]);
    }

    /**
     * タイムテーブル（全レースの各組のスタート時刻）。
     */
    public function timetable(Request $request, Meet $meet): View
    {
        $this->authorizeStartList($request, $meet);

        return view('site.timetable', ['meet' => $meet, 'rows' => $this->timetableRows($meet)]);
    }

    public function startList(Request $request, Meet $meet): View
    {
        $this->authorizeStartList($request, $meet);

        return view('site.startlist', ['meet' => $meet, 'races' => $this->racesWithRunners($meet)]);
    }

    /**
     * 大会プログラム（タイムテーブル＋スタートリスト）。印刷してPDFにもできる。
     */
    public function program(Request $request, Meet $meet): View
    {
        $this->authorizeStartList($request, $meet);

        return view('site.program', [
            'meet' => $meet,
            'rows' => $this->timetableRows($meet),
            'races' => $this->racesWithRunners($meet),
        ]);
    }

    /**
     * 公開前（未公開）は運営だけが見られる。
     */
    private function authorizeStartList(Request $request, Meet $meet): void
    {
        abort_unless($meet->is_published && $meet->hasPublicStartList() || $request->user()?->is_admin, 404);
    }

    private function racesWithRunners(Meet $meet)
    {
        return $meet->races()
            ->with(['event', 'startListEntries' => fn ($q) => $q->whereHas('entryItem.entry', fn ($e) => $e->where('status', Entry::STATUS_PAID)),
                'startListEntries.entryItem.athlete', 'startListEntries.entryItem.entry.user'])
            ->get()
            ->filter(fn (Race $r) => $r->startListEntries->isNotEmpty())
            ->values();
    }

    /**
     * @return Collection<int, array{time: ?string, race: Race, heat: int, count: int}>
     */
    private function timetableRows(Meet $meet)
    {
        return $this->racesWithRunners($meet)
            ->flatMap(fn (Race $race) => $race->heats()->map(fn ($entries, $heat) => [
                'time' => $race->heatStartTime((int) $heat),
                'race' => $race,
                'heat' => (int) $heat,
                'count' => $entries->count(),
            ])->values())
            ->sortBy([
                fn ($a, $b) => ($a['time'] === null) <=> ($b['time'] === null),
                fn ($a, $b) => strcmp(str_pad((string) $a['time'], 5, '0', STR_PAD_LEFT), str_pad((string) $b['time'], 5, '0', STR_PAD_LEFT)),
                fn ($a, $b) => $a['race']->sort_order <=> $b['race']->sort_order,
                fn ($a, $b) => $a['heat'] <=> $b['heat'],
            ])
            ->values();
    }

    /**
     * 過去の大会結果へのリンク（年度の古い順）。設定にURLがあればそれ、なければこのページのその年度。
     *
     * @return array<int, string>
     */
    private function pastResults($years, int $current): array
    {
        $links = [];
        foreach ((array) config('kizuna.past_results') as $y => $url) {
            $links[(int) $y] = $url ?: route('site.meets', ['year' => $y]).'#schedule';
        }
        foreach ($years as $y) {
            if ($y < $current && ! isset($links[$y])) {
                $links[$y] = route('site.meets', ['year' => $y]).'#schedule';
            }
        }
        ksort($links);

        return $links;
    }
}
