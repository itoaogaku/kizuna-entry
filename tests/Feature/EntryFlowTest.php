<?php

namespace Tests\Feature;

use App\Mail\EntryPaidMail;
use App\Models\Athlete;
use App\Models\Entry;
use App\Models\Event;
use App\Models\Meet;
use App\Models\User;
use App\Payments\FakePaymentGateway;
use App\Payments\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class EntryFlowTest extends TestCase
{
    use RefreshDatabase;

    private FakePaymentGateway $payments;

    private User $team;

    private Meet $meet;

    private Event $men5000;

    private Event $women3000;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->payments = new FakePaymentGateway;
        $this->app->instance(PaymentGateway::class, $this->payments);

        $this->team = User::factory()->create();
        $this->meet = Meet::factory()->create();
        $this->men5000 = Event::factory()->for($this->meet)->create(['name' => '一般男子5000m', 'gender' => 'M', 'fee' => 2000]);
        $this->women3000 = Event::factory()->for($this->meet)->create(['name' => '一般女子3000m', 'gender' => 'F', 'fee' => 1500]);
    }

    private function enter(array $items): TestResponse
    {
        $this->actingAs($this->team)->post(route('entries.review', $this->meet), ['items' => $items]);

        return $this->actingAs($this->team)->post(route('entries.store', $this->meet));
    }

    public function test_only_own_active_athletes_are_listed(): void
    {
        $mine = Athlete::factory()->for($this->team)->create(['last_name' => '青山']);
        Athlete::factory()->for($this->team)->create(['last_name' => '卒業', 'is_active' => false]);
        Athlete::factory()->create(['last_name' => '他校']);

        $this->actingAs($this->team)->get(route('entries.create', $this->meet))
            ->assertOk()
            ->assertSee('青山')
            ->assertDontSee('卒業')
            ->assertDontSee('他校');
    }

    public function test_selected_athletes_become_a_pending_entry_and_go_to_stripe(): void
    {
        $man = Athlete::factory()->for($this->team)->create();
        $woman = Athlete::factory()->for($this->team)->female()->create();
        $notSelected = Athlete::factory()->for($this->team)->create();

        $this->actingAs($this->team)->post(route('entries.review', $this->meet), ['items' => [
            $man->id => ['selected' => '1', 'events' => [['event_id' => $this->men5000->id, 'record' => '１５：３２．１０']]],
            $woman->id => ['selected' => '1', 'events' => [['event_id' => $this->women3000->id, 'record' => '']]],
            $notSelected->id => ['events' => [['event_id' => $this->men5000->id]]],
        ]])->assertRedirect(route('entries.confirm', $this->meet));

        $this->actingAs($this->team)->get(route('entries.confirm', $this->meet))
            ->assertOk()
            ->assertSee('3,500円');

        $response = $this->actingAs($this->team)->post(route('entries.store', $this->meet));

        $entry = Entry::sole();
        $this->assertSame(Entry::STATUS_PENDING, $entry->status);
        $this->assertSame(3500, $entry->total_amount);
        $this->assertCount(2, $entry->items);
        $this->assertSame('15:32.10', $entry->items->firstWhere('athlete_id', $man->id)->declared_record);
        $response->assertRedirect('https://checkout.stripe.test/'.$entry->stripe_session_id);
        Mail::assertNothingSent();
    }

    public function test_athlete_cannot_enter_event_for_other_gender(): void
    {
        $man = Athlete::factory()->for($this->team)->create();

        $this->actingAs($this->team)->post(route('entries.review', $this->meet), ['items' => [
            $man->id => ['selected' => '1', 'events' => [['event_id' => $this->women3000->id]]],
        ]])->assertSessionHasErrors();

        $this->assertDatabaseCount('entries', 0);
    }

    public function test_cannot_enter_other_teams_athlete(): void
    {
        $other = Athlete::factory()->create();

        $this->enter([$other->id => ['selected' => '1', 'events' => [['event_id' => $this->men5000->id]]]]);

        $this->assertDatabaseCount('entries', 0);
    }

    public function test_events_per_athlete_are_limited(): void
    {
        $this->meet->update(['max_events_per_athlete' => 1]);
        $other = Event::factory()->for($this->meet)->create(['name' => '一般男子1500m']);
        $man = Athlete::factory()->for($this->team)->create();

        $this->actingAs($this->team)->post(route('entries.review', $this->meet), ['items' => [
            $man->id => ['selected' => '1', 'events' => [['event_id' => $this->men5000->id], ['event_id' => $other->id]]],
        ]])->assertSessionHasErrors();
    }

    public function test_same_athlete_cannot_enter_same_event_twice(): void
    {
        $man = Athlete::factory()->for($this->team)->create();
        $items = [$man->id => ['selected' => '1', 'events' => [['event_id' => $this->men5000->id]]]];

        $this->enter($items);
        $this->enter($items)->assertSessionHasErrors();

        $this->assertDatabaseCount('entries', 1);
    }

    public function test_capacity_counts_pending_and_paid_entries(): void
    {
        $this->men5000->update(['capacity' => 1]);
        $first = Athlete::factory()->for($this->team)->create();
        $second = Athlete::factory()->for($this->team)->create();

        $this->enter([$first->id => ['selected' => '1', 'events' => [['event_id' => $this->men5000->id]]]]);
        $this->enter([$second->id => ['selected' => '1', 'events' => [['event_id' => $this->men5000->id]]]])
            ->assertSessionHasErrors();

        $this->assertDatabaseCount('entries', 1);
    }

    public function test_closed_meet_rejects_entries(): void
    {
        $meet = Meet::factory()->closed()->create();

        $this->actingAs($this->team)->get(route('entries.create', $meet))
            ->assertRedirect(route('home'));
    }

    public function test_returning_from_stripe_marks_paid_and_sends_mail_once(): void
    {
        $man = Athlete::factory()->for($this->team)->create();
        $this->enter([$man->id => ['selected' => '1', 'events' => [['event_id' => $this->men5000->id]]]]);
        $entry = Entry::sole();
        $this->payments->markPaid($entry->stripe_session_id, 'pi_abc');

        $url = route('entries.complete', ['entry' => $entry, 'session_id' => $entry->stripe_session_id]);
        $this->actingAs($this->team)->get($url)->assertOk()->assertSee('エントリーが完了しました');
        $this->actingAs($this->team)->get($url)->assertOk();

        $entry->refresh();
        $this->assertTrue($entry->isPaid());
        $this->assertSame('pi_abc', $entry->stripe_payment_intent_id);
        Mail::assertSent(EntryPaidMail::class, 1);
        Mail::assertSent(EntryPaidMail::class, fn ($mail) => $mail->hasTo($this->team->email));
    }

    public function test_cancelling_on_stripe_frees_the_slot_and_restores_the_selection(): void
    {
        $this->men5000->update(['capacity' => 1]);
        $man = Athlete::factory()->for($this->team)->create();
        $this->enter([$man->id => ['selected' => '1', 'events' => [['event_id' => $this->men5000->id, 'record' => '15:00']]]]);
        $entry = Entry::sole();

        $this->actingAs($this->team)->get(route('entries.checkout-cancelled', $entry))
            ->assertRedirect(route('entries.create', $this->meet));

        $this->assertSame(Entry::STATUS_CANCELLED, $entry->fresh()->status);
        $this->assertTrue($this->payments->expired[$entry->stripe_session_id]);
        $this->actingAs($this->team)->get(route('entries.create', $this->meet))->assertSee('value="15:00"', false);

        // 枠が空いたので、もう一度申し込める
        $this->enter([$man->id => ['selected' => '1', 'events' => [['event_id' => $this->men5000->id]]]]);
        $this->assertSame(1, Entry::where('status', Entry::STATUS_PENDING)->count());
    }

    public function test_cannot_view_other_teams_entry(): void
    {
        $man = Athlete::factory()->for($this->team)->create();
        $this->enter([$man->id => ['selected' => '1', 'events' => [['event_id' => $this->men5000->id]]]]);

        $this->actingAs(User::factory()->create())
            ->get(route('entries.show', Entry::sole()))
            ->assertNotFound();
    }
}
