<?php

namespace Database\Seeders;

use App\Models\Athlete;
use App\Models\Meet;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * 動作確認用のデータ。本番では実行しない。
 *   運営：admin@example.com / password
 *   チーム：team@example.com / password
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
            ['青山', '駿', 'アオヤマ', 'シュン', 'M', '2004-05-12', '大学'],
            ['伊藤', '陸', 'イトウ', 'リク', 'M', '2005-09-30', '大学'],
            ['佐藤', '翔太', 'サトウ', 'ショウタ', 'M', '2008-01-20', '高校'],
            ['鈴木', '美咲', 'スズキ', 'ミサキ', 'F', '2007-11-03', '高校'],
            ['高橋', '結衣', 'タカハシ', 'ユイ', 'F', '1998-07-07', '一般'],
        ];
        foreach ($athletes as [$ln, $fn, $lk, $fk, $g, $b, $c]) {
            Athlete::factory()->for($team)->create([
                'last_name' => $ln, 'first_name' => $fn, 'last_kana' => $lk, 'first_kana' => $fk,
                'gender' => $g, 'birthdate' => $b, 'category' => $c,
            ]);
        }

        $meet = Meet::factory()->create([
            'name' => '第1回 絆記録会',
            'notes' => "参加費は1種目ごとにかかります。\nお支払い後の返金はできません。",
        ]);
        $events = [
            ['一般男子1500m', 'M', 1500, 40],
            ['一般男子5000m', 'M', 2000, 60],
            ['一般女子1500m', 'F', 1500, 40],
            ['一般女子3000m', 'F', 2000, 40],
            ['中学男子3000m', 'M', 1000, null],
        ];
        foreach ($events as $i => [$name, $gender, $fee, $capacity]) {
            $meet->events()->create([
                'name' => $name, 'gender' => $gender, 'fee' => $fee, 'capacity' => $capacity, 'sort_order' => ($i + 1) * 10,
            ]);
        }
    }
}
