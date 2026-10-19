<?php

namespace Tests\Feature;

use App\Models\Athlete;
use App\Models\Entry;
use App\Models\Event;
use App\Models\Meet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_accounts_cannot_open_admin(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    }

    public function test_admin_can_create_meet_and_events(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.meets.store'), [
            'name' => '第2回 絆記録会',
            'held_on' => '2026-12-06',
            'entry_opens_at' => '2026-10-20T00:00',
            'entry_closes_at' => '2026-11-20T23:59',
            'max_events_per_athlete' => 2,
            'is_published' => '1',
        ])->assertRedirect();
        $meet = Meet::sole();

        $this->actingAs($admin)->post(route('admin.events.store', $meet), [
            'name' => '一般男子5000m', 'gender' => 'M', 'fee' => 2000, 'capacity' => 60,
        ])->assertRedirect();

        $this->assertTrue($meet->is_published);
        $this->assertDatabaseHas('events', ['meet_id' => $meet->id, 'name' => '一般男子5000m', 'fee' => 2000, 'capacity' => 60]);
    }

    public function test_excel_export_uses_entry_sheet_columns_sorted_by_declared_time(): void
    {
        $admin = User::factory()->admin()->create();
        $team = User::factory()->create(['team_name' => '絆AC']);
        $event = Event::factory()->create(['name' => '5000m', 'gender' => null]);
        $slow = Athlete::factory()->for($team)->create(['last_name' => '青山', 'first_name' => '駿', 'kana' => 'アオヤマ シュン', 'grade' => 2, 'category' => '大学生']);
        $fast = Athlete::factory()->for($team)->create(['last_name' => '伊藤', 'first_name' => '陸', 'kana' => '=CMD()']);
        $cancelled = Athlete::factory()->for($team)->create(['last_name' => '取消']);

        foreach ([[$slow, "15'10", Entry::STATUS_PAID], [$fast, "14'30", Entry::STATUS_PAID], [$cancelled, "13'00", Entry::STATUS_CANCELLED]] as [$athlete, $time, $status]) {
            $entry = $team->entries()->create(['meet_id' => $event->meet_id, 'status' => $status, 'total_amount' => 2200, 'paid_at' => now()]);
            $entry->items()->create(['athlete_id' => $athlete->id, 'event_id' => $event->id, 'fee' => 2200, 'declared_record' => $time, 'personal_best' => "14'45", 'pr_comment' => '練習量No.1']);
        }

        $response = $this->actingAs($admin)->get(route('admin.entries.export', $event->meet))->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'export');
        file_put_contents($path, $response->streamedContent());
        $book = IOFactory::load($path);
        $rows = $book->getSheet(0)->toArray(null, false, false, false);

        $this->assertSame(['No', '団体名', '姓', '名', 'フリガナ', '学年', '性別', '所属陸協', '申込み種目', '申請タイム', '自己ベスト', '種別', 'PRしたい一言', '料金'], array_slice($rows[0], 0, 14));
        $this->assertCount(3, $rows); // 見出し＋入金済み2件（取消は出さない）
        $this->assertSame(['伊藤', "14'30"], [$rows[1][2], $rows[1][9]]); // 申請タイムの速い順
        $this->assertSame([2, '絆AC', '青山', '駿', 'アオヤマ シュン', '2', '男'], array_slice($rows[2], 0, 7));
        $this->assertSame('=CMD()', $book->getSheet(0)->getCell('E2')->getValue()); // 数式にしない
        $this->assertSame('5000m', $book->getSheet(1)->getTitle()); // 種目ごとのシート
    }

    public function test_roster_lists_all_teams_by_target_time_then_personal_best(): void
    {
        $admin = User::factory()->admin()->create();
        $event = Event::factory()->create(['name' => '5000m', 'gender' => null]);
        $teamA = User::factory()->create(['team_name' => 'A大学']);
        $teamB = User::factory()->create(['team_name' => 'B高校']);
        $rows = [
            [$teamA, '青山', "14'30", "14'40"],
            [$teamB, '伊藤', "14'30", "14'20"], // 同タイム・自己ベストが速い
            [$teamB, '佐藤', "14'30", null],     // 同タイム・自己ベスト未入力は後ろ
            [$teamA, '鈴木', "14'10", null],
            [$teamB, '取消', "13'00", null, Entry::STATUS_CANCELLED],
        ];
        foreach ($rows as $row) {
            [$team, $name, $target, $best] = $row;
            $athlete = Athlete::factory()->for($team)->create(['last_name' => $name]);
            $entry = $team->entries()->create(['meet_id' => $event->meet_id, 'status' => $row[4] ?? Entry::STATUS_PAID, 'total_amount' => 2200]);
            $entry->items()->create(['athlete_id' => $athlete->id, 'event_id' => $event->id, 'fee' => 2200, 'declared_record' => $target, 'personal_best' => $best]);
        }

        $this->actingAs($admin)->get(route('admin.roster.index', $event->meet))
            ->assertOk()
            ->assertSeeInOrder(['鈴木', '伊藤', '青山', '佐藤'])
            ->assertSee('A大学')
            ->assertSee('B高校')
            ->assertDontSee('取消 ');

        $this->actingAs($admin)->get(route('admin.roster.index', ['meet' => $event->meet, 'team' => $teamA->id]))
            ->assertSee('青山')
            ->assertDontSee('伊藤');
    }

    public function test_admin_can_fill_in_personal_best(): void
    {
        $admin = User::factory()->admin()->create();
        $event = Event::factory()->create();
        $team = User::factory()->create();
        $entry = $team->entries()->create(['meet_id' => $event->meet_id, 'status' => Entry::STATUS_PAID, 'total_amount' => 2200]);
        $item = $entry->items()->create(['athlete_id' => Athlete::factory()->for($team)->create()->id, 'event_id' => $event->id, 'fee' => 2200, 'declared_record' => "14'30"]);

        $this->actingAs($admin)->put(route('admin.roster.best', $item), ['personal_best' => '１４’２０'])->assertRedirect();
        $this->assertSame("14'20", $item->fresh()->personal_best);

        $this->actingAs($admin)->put(route('admin.roster.best', $item), ['personal_best' => 'はやい'])->assertSessionHasErrors();
        $this->actingAs($team)->put(route('admin.roster.best', $item), ['personal_best' => "13'00"])->assertForbidden();
    }

    public function test_new_meet_form_carries_over_the_latest_meet_and_registers_events_at_once(): void
    {
        $admin = User::factory()->admin()->create();
        $last = Meet::factory()->create(['name' => '第9回 絆記録会', 'venue' => '絆競技場', 'held_on' => '2026-06-01']);
        Event::factory()->for($last)->create(['name' => '5000m', 'gender' => null, 'fee' => 2200, 'capacity' => 120]);

        $this->actingAs($admin)->get(route('admin.meets.create'))
            ->assertOk()
            ->assertSee('value="第10回 絆記録会"', false)
            ->assertSee('value="絆競技場"', false)
            ->assertSee('value="5000m"', false)
            ->assertSee('value="2200"', false);

        $this->actingAs($admin)->post(route('admin.meets.store'), [
            'name' => '第10回 絆記録会',
            'held_on' => '2026-12-06',
            'entry_opens_at' => '2026-10-20T00:00',
            'entry_closes_at' => '2026-11-20T23:59',
            'max_events_per_athlete' => 2,
            'is_published' => '1',
            'events' => [
                ['name' => '5000m', 'gender' => '', 'fee' => '2200', 'capacity' => '120'],
                ['name' => '1500m', 'gender' => '', 'fee' => '2000', 'capacity' => ''],
                ['name' => '', 'gender' => '', 'fee' => '', 'capacity' => ''],
            ],
        ])->assertRedirect();

        $meet = Meet::where('name', '第10回 絆記録会')->sole();
        $this->assertSame(['5000m', '1500m'], $meet->events->pluck('name')->all());
        $this->assertNull($meet->events[1]->capacity);
        $this->assertSame(2000, $meet->events[1]->fee);
    }

    public function test_new_meet_rejects_events_without_fee(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.meets.store'), [
            'name' => '第1回', 'held_on' => '2026-12-06',
            'entry_opens_at' => '2026-10-20T00:00', 'entry_closes_at' => '2026-11-20T23:59',
            'max_events_per_athlete' => 2,
            'events' => [['name' => '5000m', 'fee' => '']],
        ])->assertSessionHasErrors();

        $this->assertDatabaseCount('meets', 0);
    }

    public function test_make_admin_command_reads_accounts_from_config_and_skips_missing_ones(): void
    {
        $user = User::factory()->create(['email' => 'kizuna@example.com']);
        config(['services.kizuna.admin_accounts' => 'kizuna@example.com, missing@example.com']);

        $this->artisan('kizuna:make-admin --if-exists')->assertSuccessful();
        $this->assertTrue($user->fresh()->is_admin);
        $this->artisan('kizuna:make-admin missing@example.com')->assertFailed();
    }

    public function test_admin_has_its_own_screen_and_cannot_use_entry_pages(): void
    {
        $admin = User::factory()->admin()->create(['team_name' => '絆記録会 運営', 'password' => 'secret-pass']);

        $this->actingAs($admin)->get(route('admin.meets.index'))->assertOk()
            ->assertSee('admin-header', false)
            ->assertSee('運営専用')
            ->assertDontSee('エントリー用ログイン');
        $this->actingAs($admin)->get(route('athletes.index'))->assertRedirect(route('admin.meets.index'));
        $this->actingAs($admin)->get(route('home'))->assertRedirect(route('admin.meets.index'));
        $this->actingAs($admin)->get(route('entries.index'))->assertRedirect(route('admin.meets.index'));
    }

    public function test_admin_login_goes_straight_to_admin_screen(): void
    {
        User::factory()->admin()->create(['team_name' => '絆記録会 運営', 'email' => 'info@example.com', 'password' => 'secret-pass']);

        $this->post(route('team.login'), ['email' => 'info@example.com', 'password' => 'secret-pass'])
            ->assertRedirect(route('admin.meets.index'));
    }
}
