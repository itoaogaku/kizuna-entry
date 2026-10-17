<?php

namespace Tests\Feature;

use App\Mail\PressReceivedMail;
use App\Models\Athlete;
use App\Models\Entry;
use App\Models\Event;
use App\Models\Meet;
use App\Models\Race;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ProgramTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Meet $meet;

    private Event $five;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->meet = Meet::factory()->create(['name' => '第21回絆記録挑戦会']);
        $this->five = Event::factory()->for($this->meet)->create(['name' => '5000m', 'gender' => null]);
    }

    private function paid(string $name, string $time, string $gender = 'M', string $status = Entry::STATUS_PAID): Athlete
    {
        $team = User::factory()->create();
        $athlete = Athlete::factory()->for($team)->create(['last_name' => $name, 'first_name' => '選手', 'gender' => $gender]);
        $entry = $team->entries()->create(['meet_id' => $this->meet->id, 'status' => $status, 'total_amount' => 2200]);
        $entry->items()->create(['athlete_id' => $athlete->id, 'event_id' => $this->five->id, 'fee' => 2200, 'declared_record' => $time]);

        return $athlete;
    }

    /** 組ごとの選手（姓）を返す */
    private function heats(string $gender = 'M'): array
    {
        $race = Race::where('gender', $gender)->with('startListEntries.entryItem.athlete')->sole();

        return $race->heats()->map(fn ($entries) => $entries->map(fn ($e) => $e->entryItem->athlete->last_name)->all())->all();
    }

    public function test_heats_are_built_by_declared_time_with_the_fastest_heat_last(): void
    {
        foreach (['A' => "14'10", 'B' => "14'20", 'C' => "14'30", 'D' => "14'40", 'E' => "14'50", 'F' => "15'00", 'G' => "15'10"] as $name => $time) {
            $this->paid($name, $time);
        }
        $this->paid('女子', "16'00", 'F');
        $this->paid('未入金', "13'00", 'M', Entry::STATUS_PENDING);

        $this->actingAs($this->admin)->get(route('admin.program.show', $this->meet))->assertOk()->assertSee('男子 5000m')->assertSee('女子 5000m');
        $race = Race::where('gender', 'M')->sole();
        $this->actingAs($this->admin)->put(route('admin.program.races', $this->meet), ['races' => [
            $race->id => ['start_time' => '10:00', 'heat_size' => 3, 'heat_interval' => 20, 'fastest_last' => '1', 'sort_order' => 10],
            Race::where('gender', 'F')->value('id') => ['start_time' => '09:30', 'heat_size' => 30, 'heat_interval' => 10, 'fastest_last' => '1', 'sort_order' => 20],
        ]])->assertRedirect();
        $this->actingAs($this->admin)->post(route('admin.program.generate', $this->meet))->assertRedirect();

        // 7人を3人ずつ → 3組（3・2・2人）。速い組が最後、組の中は速い順。未入金の人は入らない
        $this->assertSame([1 => ['F', 'G'], 2 => ['D', 'E'], 3 => ['A', 'B', 'C']], $this->heats('M'));
        $this->assertSame([1 => ['女子']], $this->heats('F'));
        $this->assertSame('10:40', Race::where('gender', 'M')->sole()->heatStartTime(3));
    }

    public function test_heat_can_be_changed_and_positions_are_renumbered(): void
    {
        foreach (['A' => "14'10", 'B' => "14'20", 'C' => "14'30", 'D' => "14'40"] as $name => $time) {
            $this->paid($name, $time);
        }
        $this->meet->races()->create(['event_id' => $this->five->id, 'gender' => 'M', 'heat_size' => 2]);
        $this->actingAs($this->admin)->post(route('admin.program.generate', $this->meet));
        $this->assertSame([1 => ['C', 'D'], 2 => ['A', 'B']], $this->heats());

        // 組変更希望：AとCを入れ替える
        $race = Race::with('startListEntries.entryItem.athlete')->sole();
        $id = fn ($name) => $race->startListEntries->first(fn ($e) => $e->entryItem->athlete->last_name === $name)->id;
        $this->actingAs($this->admin)->put(route('admin.program.heats', $this->meet), ['heats' => [$id('A') => 1, $id('C') => 2]])
            ->assertSessionHas('status', '2人の組を変更しました。');

        $this->assertSame([1 => ['A', 'D'], 2 => ['B', 'C']], $this->heats());
    }

    public function test_start_list_is_public_only_after_publishing_and_program_after_confirming(): void
    {
        $this->paid('青山', "14'30");
        $this->actingAs($this->admin)->post(route('admin.program.generate', $this->meet));
        auth()->logout();

        $this->get(route('site.startlist', $this->meet))->assertNotFound();
        $this->get('/')->assertDontSee(route('site.timetable', $this->meet));

        $this->actingAs($this->admin)->put(route('admin.program.status', $this->meet), ['status' => 'provisional'])->assertRedirect();
        auth()->logout();
        $this->get(route('site.startlist', $this->meet))->assertOk()->assertSee('青山 選手')->assertSee('暫定版');
        $this->get(route('site.timetable', $this->meet))->assertOk()->assertSee('男子 5000m');
        $this->get('/')->assertSee(route('site.timetable', $this->meet))->assertDontSee(route('site.program', $this->meet));

        $this->actingAs($this->admin)->put(route('admin.program.status', $this->meet), ['status' => 'final'])->assertRedirect();
        auth()->logout();
        $this->get('/')->assertSee(route('site.program', $this->meet));
        $this->get(route('site.program', $this->meet))->assertOk()
            ->assertSee('OFFICIAL PROGRAM')->assertSee('タイムテーブル')->assertSee('スタートリスト')->assertDontSee('※暫定版');
    }

    public function test_entries_paid_after_building_heats_are_reported(): void
    {
        $this->paid('青山', "14'30");
        $this->actingAs($this->admin)->post(route('admin.program.generate', $this->meet));
        $this->paid('遅れて入金', "15'00");

        $this->actingAs($this->admin)->get(route('admin.program.show', $this->meet))
            ->assertSee('組分けのあとに入金された申込が 1 件あります')->assertSee('遅れて入金');
    }

    public function test_press_can_apply_through_the_form(): void
    {
        Mail::fake();
        $this->get('/')->assertSee(route('press.create', $this->meet));

        $this->post(route('press.store', $this->meet), [
            'media_name' => '陸上通信', 'contact_name' => '記者 一郎', 'email' => 'press@example.com', 'phone' => '090-0000-0000',
            'coverage' => '取材・撮影', 'people' => 2, 'purpose' => 'Web記事', 'agree' => '1',
        ])->assertRedirect(route('site.meets'));

        $this->assertDatabaseHas('press_requests', ['meet_id' => $this->meet->id, 'media_name' => '陸上通信', 'people' => 2]);
        Mail::assertSent(PressReceivedMail::class, fn ($m) => $m->hasTo('press@example.com'));
        $this->actingAs($this->admin)->get(route('admin.press.index', $this->meet))->assertSee('陸上通信');

        $finished = Meet::factory()->create(['held_on' => now()->subDay()]);
        $this->get(route('press.create', $finished))->assertNotFound();
    }
}
