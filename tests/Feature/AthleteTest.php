<?php

namespace Tests\Feature;

use App\Models\Athlete;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class AthleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_athlete_can_be_registered_with_entry_sheet_items(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/athletes', [
            'last_name' => '鈴木', 'first_name' => '太郎',
            'kana' => 'ｽｽﾞｷ ﾀﾛｳ',
            'grade' => '3', 'gender' => 'M', 'association' => '東京', 'category' => '高校生',
        ])->assertRedirect(route('athletes.index'));

        // 大学院生・学連登録・フリガナなし（外国籍の選手など）も登録できる
        $this->actingAs($user)->post('/athletes', [
            'last_name' => 'Bryan', 'first_name' => 'Ong', 'kana' => '',
            'grade' => 'ｍ１', 'gender' => 'M', 'association' => '学連', 'category' => '大学生',
        ])->assertRedirect(route('athletes.index'));
        $this->assertDatabaseHas('athletes', ['last_name' => 'Bryan', 'kana' => null, 'grade' => 'M1', 'association' => '学連']);

        $this->assertDatabaseHas('athletes', [
            'user_id' => $user->id, 'kana' => 'スズキ タロウ', 'grade' => 3, 'association' => '東京', 'category' => '高校生', 'birthdate' => null,
        ]);
    }

    public function test_athlete_form_offers_graduate_grades_and_optional_kana(): void
    {
        $user = User::factory()->create();
        $athlete = Athlete::factory()->for($user)->create(['grade' => 'M1']);

        $this->actingAs($user)->get(route('athletes.create'))->assertOk()
            ->assertSee('<option value="M2"', false)
            ->assertDontSee('name="kana" value="" placeholder="スズキ タロウ" required', false);
        $this->actingAs($user)->get(route('athletes.edit', $athlete))->assertOk()
            ->assertSee('<option value="M1" selected', false);
    }

    public function test_cannot_edit_other_teams_athlete(): void
    {
        $athlete = Athlete::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('athletes.edit', $athlete))
            ->assertNotFound();
    }

    public function test_current_entry_sheet_can_be_uploaded_as_is(): void
    {
        $user = User::factory()->create();
        Athlete::factory()->for($user)->create(['last_name' => '高橋', 'first_name' => '結衣']);

        // 今使っているエントリーシート（申込みシート）と同じ配置
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('申込み');
        $sheet->setCellValue('B1', '大会への団体申込み・団体サイト会員登録');
        $sheet->setCellValue('B4', '団体名');
        $sheet->fromArray(['', '姓', '名', 'フリガナ', "学年\n(学生の場合)", '性別', '所属陸協', "申込み種目\n（例5000m）",
            "申請タイム\n(例14'30)", "自己ベスト\n(例14'45)", "種別\n（高校生・大学生）", 'PRしたい一言', '料金'], null, 'A7');
        $sheet->fromArray(['例', '鈴木', '太郎', 'ｽｽﾞｷ ﾀﾛｳ', 3, '男', '東京', '5000m', "14'30", "14'45", '高校生・大学生', '', 2200], null, 'A9');
        $sheet->fromArray([1, '伊藤', '陸', 'ｲﾄｳ ﾘｸ', 1, '男', '神奈川陸協', '5000m', "14'50", '', '大学生'], null, 'A10');
        $sheet->fromArray([2, '伊藤', '陸', 'ｲﾄｳ ﾘｸ', 1, '男', '神奈川陸協', '1500m', "4'05", '', '大学生'], null, 'A11');
        $sheet->fromArray([3, '鈴木', '美咲', 'すずき みさき', '２', '女', '埼玉県', '3000m', "10'05", '', '高校'], null, 'A12');
        $sheet->fromArray([4, '高橋', '結衣', 'ﾀｶﾊｼ ﾕｲ', '', '女', '千葉', '3000m', "10'30", '', '一般'], null, 'A13');
        $sheet->setCellValue('A14', 5);
        $path = tempnam(sys_get_temp_dir(), 'sheet').'.xlsx';
        (new Xlsx($book))->save($path);

        $this->actingAs($user)
            ->post('/athletes/import', ['file' => new UploadedFile($path, 'エントリーシート.xlsx', null, null, true)])
            ->assertRedirect(route('athletes.index'))
            ->assertSessionHas('status', '2人の選手を登録しました。（登録済み・重複の2行は飛ばしました）');

        $this->assertSame(3, $user->athletes()->count());
        $this->assertDatabaseHas('athletes', ['last_name' => '伊藤', 'kana' => 'イトウ リク', 'grade' => 1, 'association' => '神奈川', 'category' => '大学生', 'gender' => 'M']);
        $this->assertDatabaseHas('athletes', ['last_name' => '鈴木', 'first_name' => '美咲', 'kana' => 'スズキ ミサキ', 'grade' => 2, 'association' => '埼玉', 'category' => '高校生', 'gender' => 'F']);
        $this->assertDatabaseMissing('athletes', ['first_name' => '太郎']); // 記入例の行は読まない
    }

    public function test_csv_import_accepts_shift_jis(): void
    {
        $user = User::factory()->create();
        $csv = "姓,名,フリガナ,学年,性別,所属陸協,種別,生年月日,陸連登録番号\r\n"
            ."伊藤,陸,イトウ リク,1,男,神奈川,大学生,2005/9/30,12345\r\n";
        $file = UploadedFile::fake()->createWithContent('athletes.csv', mb_convert_encoding($csv, 'SJIS-win', 'UTF-8'));

        $this->actingAs($user)->post('/athletes/import', ['file' => $file])
            ->assertRedirect(route('athletes.index'));

        $this->assertDatabaseHas('athletes', ['last_name' => '伊藤', 'birthdate' => '2005-09-30 00:00:00', 'jaaf_id' => '12345']);
    }

    public function test_import_with_an_invalid_row_registers_nobody(): void
    {
        $user = User::factory()->create();
        $csv = "\xEF\xBB\xBF姓,名,フリガナ,性別\n伊藤,陸,イトウ リク,男\n鈴木,美咲,スズキ ミサキ,不明\n";
        $file = UploadedFile::fake()->createWithContent('athletes.csv', $csv);

        $this->actingAs($user)->post('/athletes/import', ['file' => $file])
            ->assertSessionHasErrors();

        $this->assertSame(0, $user->athletes()->count());
    }

    public function test_team_downloads_athlete_workbook_and_uploads_it_back(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('athletes.template-xlsx'))->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'athletes').'.xlsx';
        file_put_contents($path, $response->streamedContent());
        $book = IOFactory::load($path);
        $sheet = $book->getSheetByName('選手名簿');
        $this->assertSame(['No', '姓', '名', 'フリガナ', '学年', '性別', '所属陸協', '種別'], array_slice($sheet->rangeToArray('A4:H4')[0], 0, 8));
        $this->assertSame(DataValidation::TYPE_LIST, $sheet->getCell('F6')->getDataValidation()->getType());

        $sheet->fromArray(['高橋', '結衣', 'タカハシ ユイ', 'M1', '女', '学連', '大学生'], null, 'B6');
        IOFactory::createWriter($book, 'Xlsx')->save($path);

        $this->actingAs($user)->post(route('athletes.import'), [
            'file' => new UploadedFile($path, '選手名簿.xlsx', null, null, true),
        ])->assertRedirect(route('athletes.index'));

        $this->assertDatabaseHas('athletes', ['user_id' => $user->id, 'last_name' => '高橋', 'grade' => 'M1', 'gender' => 'F', 'association' => '学連', 'category' => '大学生']);
        $this->assertSame(1, $user->athletes()->count()); // 記入例の行は登録しない
    }
}
