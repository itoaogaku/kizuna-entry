<?php

namespace Tests\Feature;

use App\Models\Athlete;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AthleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_athlete_can_be_registered_with_hiragana_converted_to_katakana(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/athletes', [
            'last_name' => '青山', 'first_name' => '駿',
            'last_kana' => 'あおやま', 'first_kana' => 'ｼｭﾝ',
            'gender' => 'M', 'birthdate' => '2004-05-12',
        ])->assertRedirect(route('athletes.index'));

        $this->assertDatabaseHas('athletes', ['user_id' => $user->id, 'last_kana' => 'アオヤマ', 'first_kana' => 'シュン']);
    }

    public function test_cannot_edit_other_teams_athlete(): void
    {
        $athlete = Athlete::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('athletes.edit', $athlete))
            ->assertNotFound();
    }

    public function test_csv_import_accepts_shift_jis_from_excel(): void
    {
        $user = User::factory()->create();
        $csv = "姓,名,セイ,メイ,性別,生年月日,区分,陸連登録番号\r\n"
            ."伊藤,陸,イトウ,リク,男,2005/9/30,大学,12345\r\n"
            ."鈴木,美咲,スズキ,ミサキ,女,2007年11月3日,高校,\r\n";
        $file = UploadedFile::fake()->createWithContent('athletes.csv', mb_convert_encoding($csv, 'SJIS-win', 'UTF-8'));

        $this->actingAs($user)->post('/athletes/import', ['csv' => $file])
            ->assertRedirect(route('athletes.index'));

        $this->assertSame(2, $user->athletes()->count());
        $this->assertDatabaseHas('athletes', ['last_name' => '伊藤', 'gender' => 'M', 'birthdate' => '2005-09-30 00:00:00', 'jaaf_id' => '12345']);
        $this->assertDatabaseHas('athletes', ['last_name' => '鈴木', 'gender' => 'F', 'birthdate' => '2007-11-03 00:00:00']);
    }

    public function test_csv_import_with_an_invalid_row_registers_nobody(): void
    {
        $user = User::factory()->create();
        $csv = "\xEF\xBB\xBF姓,名,セイ,メイ,性別,生年月日\n伊藤,陸,イトウ,リク,男,2005/9/30\n鈴木,美咲,スズキ,ミサキ,不明,2007/11/3\n";
        $file = UploadedFile::fake()->createWithContent('athletes.csv', $csv);

        $this->actingAs($user)->post('/athletes/import', ['csv' => $file])
            ->assertSessionHasErrors();

        $this->assertSame(0, $user->athletes()->count());
    }
}
