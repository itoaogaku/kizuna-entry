<?php

namespace Database\Seeders;

use App\Models\Athlete;
use App\Models\Meet;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * 動作確認用のデータ。本番では実行しない。
 *   運営（団体申し込み）：団体名「絆記録会 運営」 / password
 *   団体（団体申し込み）：団体名「絆AC」 / password
 *   個人（個人申し込み）：solo@example.com / password
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => '運営 太郎',
            'team_name' => '絆記録会 運営',
            'email' => 'admin@example.com',
        ]);

        $team = User::factory()->create([
            'name' => '代表 花子',
            'team_name' => '絆AC',
            'email' => 'team@example.com',
        ]);

        $athletes = [
            ['青山', '駿', 'アオヤマ シュン', 'M', '大学生', 2, '東京'],
            ['伊藤', '陸', 'イトウ リク', 'M', '大学生', 1, '神奈川'],
            ['佐藤', '翔太', 'サトウ ショウタ', 'M', '高校生', 3, '東京'],
            ['鈴木', '美咲', 'スズキ ミサキ', 'F', '高校生', 2, '埼玉'],
            ['高橋', '結衣', 'タカハシ ユイ', 'F', '一般', null, '千葉'],
        ];
        foreach ($athletes as [$ln, $fn, $kana, $g, $c, $grade, $assoc]) {
            Athlete::factory()->for($team)->create([
                'last_name' => $ln, 'first_name' => $fn, 'kana' => $kana,
                'gender' => $g, 'category' => $c, 'grade' => $grade, 'association' => $assoc,
            ]);
        }

        $solo = User::factory()->individual()->create([
            'name' => '中村 光',
            'team_name' => '〇〇大学',
            'email' => 'solo@example.com',
        ]);
        Athlete::factory()->for($solo)->create([
            'last_name' => '中村', 'first_name' => '光', 'kana' => 'ナカムラ ヒカル',
            'gender' => 'M', 'category' => '一般', 'association' => '東京',
        ]);

        // 2026年度の大会（今のサイトの開催予定と同じ内容。資料のURLは仮）
        $site = 'https://acckizuna.com/';
        $pastMeets = [
            ['第17回絆記録挑戦会', '2026-04-04', '高校生・大学生・一般', [['800m', 'M'], ['1500m', null], ['3000m', null], ['3000mSC', 'M'], ['5000m', 'M']]],
            ['第18回絆記録挑戦会', '2026-05-17', '中学生・高校生・大学生・一般', [['3000m', null], ['5000m', 'M']]],
            ['第19回絆記録挑戦会', '2026-06-27', '中学生・高校生・大学生・一般', [['3000m', null], ['5000m', null]]],
            ['第20回絆記録挑戦会', '2026-09-26', '中学生・高校生・大学生・一般', [['1500m', null], ['3000m', null], ['5000m', null], ['10000m', 'M']]],
        ];
        $fees = ['800m' => 2000, '1500m' => 2000, '3000m' => 2000, '3000mSC' => 2200, '5000m' => 2200, '10000m' => 2500];
        foreach ($pastMeets as [$name, $date, $eligible, $eventList]) {
            $past = Meet::factory()->create([
                'name' => $name, 'held_on' => $date, 'eligible' => $eligible,
                'entry_opens_at' => now()->parse($date)->subMonths(2), 'entry_closes_at' => now()->parse($date)->subWeeks(2),
                'outline_url' => $site, 'program_url' => $site, 'result_url' => $site, 'photo_url' => $site,
                'program_note' => $name === '第19回絆記録挑戦会' ? '0628更新' : null,
            ]);
            foreach ($eventList as $i => [$eventName, $gender]) {
                $past->events()->create(['name' => $eventName, 'gender' => $gender, 'fee' => $fees[$eventName], 'sort_order' => ($i + 1) * 10]);
            }
        }

        // エントリー受付中の大会（申込一覧などの確認用）
        $meet = Meet::factory()->create([
            'name' => '第21回絆記録挑戦会',
            'held_on' => now()->addMonth()->toDateString(),
            'eligible' => '中学生・高校生・大学生・一般',
            'outline_url' => $site,
            'notes' => "料金は1種目ごとにかかります。お支払い後の返金はできません。\n組変更希望は、暫定版スタートリスト掲載後に組変更希望用紙で受け付けます。",
        ]);
        $events = [
            ['1500m', null, 2000, 80],
            ['3000m', null, 2000, 80],
            ['5000m', null, 2200, 120],
            ['10000m', 'M', 2500, 60],
        ];
        foreach ($events as $i => [$name, $gender, $fee, $capacity]) {
            $meet->events()->create([
                'name' => $name, 'gender' => $gender, 'fee' => $fee, 'capacity' => $capacity, 'sort_order' => ($i + 1) * 10,
            ]);
        }

        // 入金済みの申込（2団体＋個人）
        $five = $meet->events()->where('name', '5000m')->first();
        $other = User::factory()->create(['name' => '代表 次郎', 'team_name' => '〇〇高校', 'email' => 'koko@example.com']);
        $demo = [
            [$team, $team->athletes()->where('last_name', '青山')->first(), "14'30", "14'41", 'チーム内No.1の練習量'],
            [$team, $team->athletes()->where('last_name', '伊藤')->first(), "14'50", null, null],
            [$solo, $solo->athletes()->first(), "14'30", "14'28", null],
            [$other, Athlete::factory()->for($other)->create(['last_name' => '山田', 'first_name' => '健', 'kana' => 'ヤマダ ケン', 'category' => '高校生', 'grade' => 3, 'association' => '埼玉']), "14'30", null, '初の14分台を狙います'],
            [$other, Athlete::factory()->for($other)->create(['last_name' => '小林', 'first_name' => '蓮', 'kana' => 'コバヤシ レン', 'category' => '高校生', 'grade' => 2, 'association' => '埼玉']), "15'05", null, null],
        ];
        foreach ($demo as [$owner, $athlete, $target, $best, $pr]) {
            $entry = $owner->entries()->create([
                'meet_id' => $meet->id, 'status' => 'paid', 'total_amount' => $five->fee, 'paid_at' => now(),
            ]);
            $entry->items()->create([
                'athlete_id' => $athlete->id, 'event_id' => $five->id, 'fee' => $five->fee,
                'declared_record' => $target, 'personal_best' => $best, 'pr_comment' => $pr,
            ]);
        }

        // 次の大会（受付開始前。「前回と同じ内容で申し込む」は受付が始まると使える）
        $next = Meet::factory()->create([
            'name' => '第22回絆記録挑戦会',
            'held_on' => now()->addMonths(3)->toDateString(),
            'eligible' => '中学生・高校生・大学生・一般',
            'entry_opens_at' => now()->addMonth(),
            'entry_closes_at' => now()->addMonths(2),
        ]);
        foreach ($events as $i => [$name, $gender, $fee, $capacity]) {
            $next->events()->create([
                'name' => $name, 'gender' => $gender, 'fee' => $fee, 'capacity' => $capacity, 'sort_order' => ($i + 1) * 10,
            ]);
        }
    }
}
