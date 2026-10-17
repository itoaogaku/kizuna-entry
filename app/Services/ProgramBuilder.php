<?php

namespace App\Services;

use App\Models\Entry;
use App\Models\EntryItem;
use App\Models\Meet;
use App\Models\Race;
use App\Models\StartListEntry;
use App\Support\EntryOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * タイムテーブルとスタートリスト（組分け）を作る。
 * 入金済みの申込を、レース（種目×男女）ごとに申請タイム順（同タイムは自己ベスト順）に並べて組に分ける。
 */
class ProgramBuilder
{
    /** 種目ごとの1組の人数の初期値 */
    private const DEFAULT_HEAT_SIZE = ['800m' => 10, '1500m' => 15, '3000mSC' => 15, '3000m' => 25, '5000m' => 30, '10000m' => 30];

    /**
     * 入金済みの申込があるレースを用意する（設定済みのレースはそのまま、申込がなくなったレースは消す）。
     */
    public function syncRaces(Meet $meet): Collection
    {
        $items = $this->paidItems($meet);
        $needed = $items->map(fn (EntryItem $i) => $this->raceKey($i))->unique();
        $existing = $meet->races()->get()->keyBy(fn (Race $r) => $r->event_id.'-'.$r->gender.'-'.$r->division);

        foreach ($existing as $key => $race) {
            if (! $needed->contains($key)) {
                $race->delete();
            }
        }

        $events = $meet->events()->get();
        $order = 0;
        foreach ($events as $event) {
            foreach (['M', 'F'] as $gender) {
                // 部門ごとに別のレース（高校生・大学生・一般の部 → 中学生の部の順）
                foreach (['open', 'junior'] as $division) {
                    $key = $event->id.'-'.$gender.'-'.$division;
                    $order += 10;
                    if (! $needed->contains($key) || $existing->has($key)) {
                        continue;
                    }
                    $meet->races()->create([
                        'event_id' => $event->id,
                        'gender' => $gender,
                        'division' => $division,
                        'heat_size' => self::DEFAULT_HEAT_SIZE[$event->name] ?? 20,
                        'heat_interval' => 10,
                        'fastest_last' => true,
                        'sort_order' => $order,
                    ]);
                }
            }
        }

        return $meet->races()->with('event')->get();
    }

    /**
     * 組分けを作り直す（手で動かした組は元に戻る）。
     */
    public function generate(Meet $meet): void
    {
        DB::transaction(function () use ($meet) {
            $races = $this->syncRaces($meet);
            $items = $this->paidItems($meet);

            foreach ($races as $race) {
                $race->startListEntries()->delete();
                $runners = EntryOrder::sort($items->filter(
                    fn (EntryItem $i) => $this->raceKey($i) === $race->event_id.'-'.$race->gender.'-'.$race->division
                ));
                foreach ($this->split($runners, $race) as $heat => $group) {
                    foreach ($group->values() as $position => $item) {
                        $race->startListEntries()->create([
                            'entry_item_id' => $item->id,
                            'heat' => $heat,
                            'position' => $position + 1,
                        ]);
                    }
                }
            }
        });
    }

    /**
     * 選手を別の組へ動かす（組変更希望など）。組の中の順番は申請タイム順に並べ直す。
     *
     * @param  array<int|string, int|string>  $heats  スタートリストのID → 組
     */
    public function moveHeats(Meet $meet, array $heats): int
    {
        $moved = 0;
        DB::transaction(function () use ($meet, $heats, &$moved) {
            $entries = StartListEntry::whereIn('race_id', $meet->races()->pluck('id'))->get()->keyBy('id');
            $touched = [];
            foreach ($heats as $id => $heat) {
                $entry = $entries->get((int) $id);
                $heat = (int) $heat;
                if (! $entry || $heat < 1 || $heat === $entry->heat) {
                    continue;
                }
                $entry->update(['heat' => $heat]);
                $touched[$entry->race_id] = true;
                $moved++;
            }
            foreach (array_keys($touched) as $raceId) {
                $this->renumber(Race::with('event')->find($raceId));
            }
        });

        return $moved;
    }

    /**
     * 入金済みなのに、スタートリストに入っていない申込（組分け後に入金された人など）。
     *
     * @return Collection<int, EntryItem>
     */
    public function unassigned(Meet $meet): Collection
    {
        $assigned = StartListEntry::whereIn('race_id', $meet->races()->pluck('id'))->pluck('entry_item_id');

        return $this->paidItems($meet)->reject(fn (EntryItem $i) => $assigned->contains($i->id))->values();
    }

    /**
     * スタートリストにいるのに、取り消された申込の数（組分け後の取消）。
     */
    public function withdrawnCount(Meet $meet): int
    {
        return StartListEntry::whereIn('race_id', $meet->races()->pluck('id'))
            ->whereHas('entryItem.entry', fn ($q) => $q->where('status', '!=', Entry::STATUS_PAID))
            ->count();
    }

    /**
     * 選手を組に分ける。人数がなるべくそろうように分け、速いグループを最後の組（または最初の組）にする。
     *
     * @param  Collection<int, EntryItem>  $runners  速い順
     * @return array<int, Collection<int, EntryItem>> 組番号 → 選手（組の中は速い順）
     */
    private function split(Collection $runners, Race $race): array
    {
        $count = $runners->count();
        if ($count === 0) {
            return [];
        }
        $heatCount = (int) ceil($count / max(1, $race->heat_size));
        $base = intdiv($count, $heatCount);
        $extra = $count % $heatCount;

        $groups = [];
        $offset = 0;
        for ($g = 0; $g < $heatCount; $g++) {
            $size = $base + ($g < $extra ? 1 : 0); // 速いグループから人数を多めに
            $groups[] = $runners->slice($offset, $size)->values();
            $offset += $size;
        }
        if ($race->fastest_last) {
            $groups = array_reverse($groups);
        }

        $result = [];
        foreach ($groups as $i => $group) {
            $result[$i + 1] = $group;
        }

        return $result;
    }

    /**
     * 申込がどのレースに入るか（種目-性別-部門）。
     */
    private function raceKey(EntryItem $item): string
    {
        return $item->event_id.'-'.$item->athlete->gender.'-'.Meet::divisionOf($item->athlete->category);
    }

    private function renumber(Race $race): void
    {
        $race->load('startListEntries.entryItem.athlete', 'startListEntries.entryItem.event');
        foreach ($race->startListEntries->groupBy('heat') as $entries) {
            $sorted = EntryOrder::sort($entries->map->entryItem);
            foreach ($sorted->values() as $position => $item) {
                $entries->firstWhere('entry_item_id', $item->id)->update(['position' => $position + 1]);
            }
        }
    }

    /**
     * @return Collection<int, EntryItem>
     */
    private function paidItems(Meet $meet): Collection
    {
        return EntryItem::query()
            ->with('athlete', 'event', 'entry.user')
            ->whereHas('entry', fn ($q) => $q->where('meet_id', $meet->id)->where('status', Entry::STATUS_PAID))
            ->get();
    }
}
