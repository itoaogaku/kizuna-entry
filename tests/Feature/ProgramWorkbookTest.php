<?php

namespace Tests\Feature;

use App\Models\Athlete;
use App\Models\Entry;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ProgramWorkbookTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_downloads_program_workbook_with_schedule_and_heats(): void
    {
        config(['kizuna.heat_size.5000m' => 2]);
        $admin = User::factory()->admin()->create();
        $team = User::factory()->create(['team_name' => '絆AC']);
        $event = Event::factory()->create(['name' => '5000m', 'gender' => null]);

        $runners = [['伊藤', "14'30", 'イトウ リク'], ['青山', "14'50", 'アオヤマ シュン'], ['取消', "13'00", null]];
        foreach ($runners as $i => [$name, $time, $kana]) {
            $athlete = Athlete::factory()->for($team)->create(['last_name' => $name, 'kana' => $kana, 'grade' => 'M1', 'association' => '学連']);
            $entry = $team->entries()->create(['meet_id' => $event->meet_id, 'status' => $kana ? Entry::STATUS_PAID : Entry::STATUS_CANCELLED, 'total_amount' => 2200, 'paid_at' => now()]);
            $entry->items()->create(['athlete_id' => $athlete->id, 'event_id' => $event->id, 'fee' => 2200, 'declared_record' => $time]);
        }
        $slow = Athlete::factory()->female()->for($team)->create(['last_name' => '女子']);
        $entry = $team->entries()->create(['meet_id' => $event->meet_id, 'status' => Entry::STATUS_PAID, 'total_amount' => 2200, 'paid_at' => now()]);
        $entry->items()->create(['athlete_id' => $slow->id, 'event_id' => $event->id, 'fee' => 2200, 'declared_record' => "17'00"]);
        $extra = Athlete::factory()->for($team)->create(['last_name' => '遅井']);
        $entry->items()->create(['athlete_id' => $extra->id, 'event_id' => $event->id, 'fee' => 2200, 'declared_record' => "16'00"]);

        $this->actingAs(User::factory()->create())->get(route('admin.documents.program', $event->meet))->assertForbidden();
        $response = $this->actingAs($admin)->get(route('admin.documents.program', $event->meet))->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'program');
        file_put_contents($path, $response->streamedContent());
        $book = IOFactory::load($path);

        $this->assertSame(['競技日程', '男子5000m', '女子5000m'], $book->getSheetNames());
        $schedule = $book->getSheetByName('競技日程');
        $this->assertSame(['男子', '5000m', 1, 1], [$schedule->getCell('B7')->getValue(), $schedule->getCell('C7')->getValue(), $schedule->getCell('D7')->getValue(), $schedule->getCell('E7')->getValue()]);
        $this->assertSame(2, $schedule->getCell('D8')->getValue());
        $this->assertSame('=IF(A7="","",A7-TIME(0,30,0))', $schedule->getCell('F7')->getValue());

        $rows = $book->getSheetByName('男子5000m')->toArray(null, false, false, false);
        $this->assertSame('1組', $rows[2][0]);
        $this->assertSame(['ORD', '氏名', null, 'ﾌﾘｶﾞﾅ', '学年', '所属', '陸協'], array_slice($rows[3], 0, 7));
        $this->assertSame('遅井', $rows[4][1]); // 遅い組が先
        $heat2 = array_search('2組', array_column($rows, 0), true);
        $this->assertSame([1, '伊藤'], [$rows[$heat2 + 2][0], $rows[$heat2 + 2][1]]);
        $this->assertSame(['ｲﾄｳ ﾘｸ', 'M1', '絆AC', '学連', "14'30"], [$rows[$heat2 + 2][3], $rows[$heat2 + 2][4], $rows[$heat2 + 2][5], $rows[$heat2 + 2][6], $rows[$heat2 + 2][8]]);
        $this->assertNotContains('取消', array_column($rows, 1));
    }
}
