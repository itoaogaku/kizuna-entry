<?php

namespace App\Support;

use App\Models\EntryItem;
use Illuminate\Support\Collection;

/**
 * 運営で使う並び順：種目 → 申請タイムの速い順 → （同じ申請タイムなら）自己ベストの速い順 → フリガナ順。
 * 読めないタイム・未入力は後ろに回す。
 */
class EntryOrder
{
    /**
     * @param  Collection<int, EntryItem>  $items  athlete と event を読み込み済みのもの
     * @return Collection<int, EntryItem>
     */
    public static function sort(Collection $items): Collection
    {
        return $items->sortBy([
            fn ($a, $b) => $a->event->sort_order <=> $b->event->sort_order,
            fn ($a, $b) => $a->event_id <=> $b->event_id,
            fn ($a, $b) => (RaceTime::toSeconds($a->declared_record) ?? INF) <=> (RaceTime::toSeconds($b->declared_record) ?? INF),
            fn ($a, $b) => (RaceTime::toSeconds($a->personal_best) ?? INF) <=> (RaceTime::toSeconds($b->personal_best) ?? INF),
            fn ($a, $b) => strcmp((string) $a->athlete->kana, (string) $b->athlete->kana),
        ])->values();
    }

    /**
     * 同じ種目で申請タイムが同じ選手がいる明細のID（自己ベストで順番が決まる選手）。
     *
     * @param  Collection<int, EntryItem>  $items
     * @return Collection<int, int>
     */
    public static function tiedIds(Collection $items): Collection
    {
        return $items
            ->filter(fn ($i) => RaceTime::toSeconds($i->declared_record) !== null)
            ->groupBy(fn ($i) => $i->event_id.'|'.RaceTime::toSeconds($i->declared_record))
            ->filter(fn ($group) => $group->count() > 1)
            ->flatten()
            ->pluck('id');
    }
}
