<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 2026年度の大会（今のサイト acckizuna.com の「2026年度大会開催予定」と同じ内容）を本番に入れる。
 * 同じ名前の大会がすでにあれば入れない。テストでは入れない。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $meets = [
            // [大会名, 開催日, 申込締切, 対象, 中学生の参加料, [[種目, 性別（M/F/null=男女）], ...]]
            ['第17回絆記録挑戦会', '2026-04-04', '2026-03-24', '高校生・大学生・一般', null,
                [['800m', 'M'], ['1500m', null], ['3000m', null], ['3000mSC', 'M'], ['5000m', 'M']]],
            ['第18回絆記録挑戦会', '2026-05-17', '2026-05-06', '中学生・高校生・大学生・一般', 1500,
                [['3000m', null], ['5000m', 'M']]],
            ['第19回絆記録挑戦会', '2026-06-27', '2026-06-16', '中学生・高校生・大学生・一般', 1500,
                [['3000m', null], ['5000m', null]]],
            ['第20回絆記録挑戦会', '2026-09-26', '2026-09-15', '中学生の部、高校生・大学生・一般の部', 1500,
                [['1500m', null], ['3000m', null], ['5000m', null], ['10000m', 'M']]],
        ];

        foreach ($meets as [$name, $heldOn, $closesOn, $eligible, $juniorFee, $events]) {
            if (DB::table('meets')->where('name', $name)->exists()) {
                continue;
            }
            $now = now();
            $meetId = DB::table('meets')->insertGetId([
                'name' => $name,
                'held_on' => $heldOn,
                'venue' => '町田市立陸上競技場',
                'entry_opens_at' => Carbon::parse($heldOn)->subMonths(2)->startOfDay(),
                'entry_closes_at' => Carbon::parse($closesOn)->endOfDay(),
                'max_events_per_athlete' => 2,
                'start_time' => '15:30',
                'fee_junior_high' => $juniorFee,
                'fee_student' => 2200,
                'fee_general' => 3300,
                'is_published' => true,
                'division' => '公認の部',
                'eligible' => $eligible,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            foreach ($events as $i => [$event, $gender]) {
                DB::table('events')->insert([
                    'meet_id' => $meetId,
                    'name' => $event,
                    'gender' => $gender,
                    'fee' => 3300,
                    'sort_order' => ($i + 1) * 10,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // 大会データは管理画面で消せるので、ここでは何もしない
    }
};
