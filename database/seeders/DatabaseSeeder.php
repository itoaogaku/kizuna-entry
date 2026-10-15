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

        $meet = Meet::factory()->create([
            'name' => '第1回 絆記録会',
            'notes' => "料金は1種目ごとにかかります。\nお支払い後の返金はできません。\n組変更希望は、暫定版スタートリスト掲載後に組変更希望用紙で受け付けます。",
        ]);
        // 種目と料金は仮の設定（エントリーシートの記入例：5000m 2,200円）
        $events = [
            ['1500m', null, 2000, 80],
            ['3000m', null, 2000, 80],
            ['5000m', null, 2200, 120],
            ['10000m', null, 2500, 60],
        ];
        foreach ($events as $i => [$name, $gender, $fee, $capacity]) {
            $meet->events()->create([
                'name' => $name, 'gender' => $gender, 'fee' => $fee, 'capacity' => $capacity, 'sort_order' => ($i + 1) * 10,
            ]);
        }

        // 管理画面の確認用：入金済みの申込（2団体＋個人）
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
    }
}
