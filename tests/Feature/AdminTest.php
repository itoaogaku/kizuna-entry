<?php

namespace Tests\Feature;

use App\Models\Athlete;
use App\Models\Entry;
use App\Models\Event;
use App\Models\Meet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_csv_export_lists_paid_entries_for_excel(): void
    {
        $admin = User::factory()->admin()->create();
        $team = User::factory()->create(['team_name' => '絆AC']);
        $event = Event::factory()->create(['name' => '一般男子5000m']);
        $athlete = Athlete::factory()->for($team)->create(['last_name' => '青山', 'first_name' => '駿', 'jaaf_id' => '=CMD()']);

        foreach ([Entry::STATUS_PAID, Entry::STATUS_CANCELLED] as $status) {
            $entry = $team->entries()->create(['meet_id' => $event->meet_id, 'status' => $status, 'total_amount' => 1500, 'paid_at' => now()]);
            $entry->items()->create(['athlete_id' => $athlete->id, 'event_id' => $event->id, 'fee' => 1500, 'declared_record' => '15:32.10']);
        }

        $csv = $this->actingAs($admin)->get(route('admin.entries.export', $event->meet))
            ->assertOk()
            ->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF受付番号,種目,氏名", $csv);
        $this->assertStringContainsString('一般男子5000m,"青山 駿"', $csv);
        $this->assertStringContainsString("'=CMD()", $csv);
        $this->assertSame(2, substr_count(trim($csv), "\n") + 1); // 見出し＋入金済み1件
    }
}
