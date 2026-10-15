<?php

namespace Tests\Feature;

use App\Models\Athlete;
use App\Models\Entry;
use App\Models\Event;
use App\Models\Meet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EasyEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_previous_meet_entry_can_be_copied_into_the_form(): void
    {
        $team = User::factory()->create();
        $aoyama = Athlete::factory()->for($team)->create(['last_name' => '青山']);
        $retired = Athlete::factory()->for($team)->create(['last_name' => '卒業', 'is_active' => false]);

        $past = Meet::factory()->create(['name' => '第1回', 'held_on' => now()->subMonths(2)]);
        $past5000 = Event::factory()->for($past)->create(['name' => '5000m', 'gender' => null]);
        $past1500 = Event::factory()->for($past)->create(['name' => '1500m', 'gender' => null]);
        $entry = $team->entries()->create(['meet_id' => $past->id, 'status' => Entry::STATUS_PAID, 'total_amount' => 4400]);
        $entry->items()->create(['athlete_id' => $aoyama->id, 'event_id' => $past5000->id, 'fee' => 2200, 'declared_record' => "14'30", 'pr_comment' => '練習量No.1']);
        $entry->items()->create(['athlete_id' => $aoyama->id, 'event_id' => $past1500->id, 'fee' => 2200, 'declared_record' => "3'58"]); // 今回はない種目
        $entry->items()->create(['athlete_id' => $retired->id, 'event_id' => $past5000->id, 'fee' => 2200, 'declared_record' => "15'00"]);

        $meet = Meet::factory()->create(['name' => '第2回']);
        $new5000 = Event::factory()->for($meet)->create(['name' => '5000m', 'gender' => null]);

        $this->actingAs($team)->get(route('entries.create', $meet))
            ->assertSee('前回（第1回）と同じ内容で申し込む');

        $this->actingAs($team)->get(route('entries.create', ['meet' => $meet, 'copy' => 'previous']))
            ->assertRedirect(route('entries.create', $meet));

        $this->assertSame([
            $aoyama->id => ['selected' => '1', 'pr' => '練習量No.1', 'events' => [['event_id' => $new5000->id, 'record' => "14'30"]]],
        ], session("entry_draft.{$meet->id}"));
    }

    public function test_grade_advance_moves_everyone_up_and_handles_graduates(): void
    {
        Carbon::setTestNow('2027-04-05');
        $team = User::factory()->create();
        $second = Athlete::factory()->for($team)->create(['category' => '高校生', 'grade' => 2]);
        $graduateRetire = Athlete::factory()->for($team)->create(['category' => '高校生', 'grade' => 3]);
        $graduatePromote = Athlete::factory()->for($team)->create(['category' => '高校生', 'grade' => 3]);
        $senior = Athlete::factory()->for($team)->create(['category' => '大学生', 'grade' => 4]);
        $adult = Athlete::factory()->for($team)->create(['category' => '一般', 'grade' => null]);

        $this->actingAs($team)->get('/')->assertSee('新年度になりました');
        $this->actingAs($team)->get(route('athletes.advance'))->assertOk()->assertSee('高校生 3年');

        $this->actingAs($team)->post(route('athletes.advance'), ['choices' => [
            $graduateRetire->id => 'retire',
            $graduatePromote->id => 'promote',
            $senior->id => 'promote',
        ]])->assertRedirect(route('athletes.index'));

        $this->assertSame(3, $second->fresh()->grade);
        $this->assertFalse($graduateRetire->fresh()->is_active);
        $this->assertSame(['大学生', 1], [$graduatePromote->fresh()->category, $graduatePromote->fresh()->grade]);
        $this->assertSame(['一般', null], [$senior->fresh()->category, $senior->fresh()->grade]);
        $this->assertNull($adult->fresh()->grade);
        $this->assertSame(2027, $team->fresh()->grades_updated_year);

        // 同じ年度に2回押しても、確認なしでは学年は上がらない
        $this->actingAs($team)->post(route('athletes.advance'))->assertSessionHasErrors('again');
        $this->assertSame(3, $second->fresh()->grade);
        $this->actingAs($team)->get('/')->assertDontSee('新年度になりました');
    }
}
