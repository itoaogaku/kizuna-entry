<?php

namespace Tests\Feature;

use App\Mail\EntryPaidMail;
use App\Models\Entry;
use App\Models\Event;
use App\Models\Meet;
use App\Models\User;
use App\Payments\FakePaymentGateway;
use App\Payments\PaymentGateways;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class IndividualEntryTest extends TestCase
{
    use RefreshDatabase;

    private FakePaymentGateway $payments;

    private Meet $meet;

    private Event $men5000;

    private Event $women3000;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.stripe.secret' => 'sk_test']);
        $this->payments = new FakePaymentGateway('cs_test_');
        $this->app->instance(PaymentGateways::class, new PaymentGateways([
            Entry::METHOD_CARD => fn () => $this->payments,
        ]));
        $this->meet = Meet::factory()->create(['fee_student' => 2200, 'fee_general' => 3300, 'max_events_per_athlete' => 2]);
        $this->men5000 = Event::factory()->for($this->meet)->create(['name' => '5000m', 'gender' => 'M']);
        $this->women3000 = Event::factory()->for($this->meet)->create(['name' => '3000m', 'gender' => 'F']);
    }

    private function form(array $overrides = []): array
    {
        return array_merge([
            'meet_id' => $this->meet->id,
            'last_name' => '鈴木', 'first_name' => '太郎', 'kana' => 'スズキ タロウ',
            'gender' => 'M', 'category' => '大学生', 'grade' => '3', 'association' => '東京',
            'team_name' => '〇〇大学',
            'events' => [['event_id' => $this->men5000->id, 'record' => '1430'], ['event_id' => '', 'record' => '']],
            'pr' => '初の14分台',
            'email' => 'suzuki@example.com', 'phone' => '090-0000-0000',
            'agree' => '1',
        ], $overrides);
    }

    public function test_entry_page_separates_team_and_individual(): void
    {
        $this->get(route('home'))->assertOk()
            ->assertSeeInOrder(['団体でお申し込み', 'Webでエントリー', 'エクセル（エントリーシート）を添付', '個人でお申し込み', '個人で申し込む'])
            ->assertSee(route('individual-entry.create'));
    }

    public function test_individual_fills_form_confirms_and_goes_to_payment_without_account(): void
    {
        $this->get(route('individual-entry.create'))->assertOk()->assertSee($this->meet->name)->assertSee('5000m');

        $this->post(route('individual-entry.review'), $this->form())->assertRedirect(route('individual-entry.confirm'));
        $this->get(route('individual-entry.confirm'))->assertOk()
            ->assertSee('鈴木 太郎')->assertSee("14'30")->assertSee('2,200円');

        $response = $this->post(route('individual-entry.store'), ['terms' => '1', 'payment_method' => Entry::METHOD_CARD, 'receipt_name' => '鈴木 太郎']);
        $response->assertRedirectContains('https://checkout.example.test/');
        $this->assertGuest();

        $entry = Entry::with('user', 'items.athlete')->sole();
        $this->assertSame(Entry::SOURCE_FORM, $entry->source);
        $this->assertSame(Entry::STATUS_PENDING, $entry->status);
        $this->assertSame(2200, $entry->total_amount);
        $this->assertSame(User::TYPE_GUEST, $entry->user->type);
        $this->assertSame('鈴木 太郎', $entry->user->displayName());
        $this->assertSame('〇〇大学', $entry->user->team_name);
        $this->assertSame("14'30", $entry->items[0]->declared_record);
        $this->assertSame('鈴木', $entry->items[0]->athlete->last_name);
        $this->assertStringContainsString('/sheet-entry/'.$entry->id, $entry->statusUrl());
    }

    public function test_form_rejects_event_for_other_gender_and_missing_time(): void
    {
        $this->post(route('individual-entry.review'), $this->form([
            'events' => [['event_id' => $this->women3000->id, 'record' => '930'], ['event_id' => $this->men5000->id, 'record' => '']],
        ]))->assertSessionHasErrors(['events.0.event_id', 'events.1.record']);

        $this->post(route('individual-entry.review'), $this->form(['events' => []]))->assertSessionHasErrors('events');
        $this->post(route('individual-entry.review'), $this->form(['category' => '']))->assertSessionHasErrors('category');
    }

    public function test_email_of_team_account_cannot_be_used(): void
    {
        User::factory()->create(['email' => 'suzuki@example.com']);

        $this->post(route('individual-entry.review'), $this->form())->assertSessionHasErrors('email');
    }

    public function test_gold_or_silver_member_enters_free_without_payment(): void
    {
        Mail::fake();

        $this->post(route('individual-entry.review'), $this->form(['membership' => 'silver']))->assertRedirect(route('individual-entry.confirm'));
        $this->get(route('individual-entry.confirm'))->assertOk()
            ->assertSee('会員の無料枠でお申し込みします')->assertSee('0円')->assertDontSee('お支払い方法');

        $response = $this->post(route('individual-entry.store'), ['terms' => '1']);

        $entry = Entry::with('items')->sole();
        $response->assertRedirectContains('/sheet-entry/'.$entry->id);
        $this->assertSame(Entry::STATUS_PAID, $entry->status);
        $this->assertSame(Entry::METHOD_MEMBER, $entry->payment_method);
        $this->assertSame('silver', $entry->membership);
        $this->assertSame(0, $entry->total_amount);
        $this->assertSame(0, $entry->items[0]->fee);
        $this->assertNull($entry->receipt_number); // 0円なので領収書は出さない
        $this->assertSame([], $this->payments->sessions);
        Mail::assertSent(EntryPaidMail::class, fn ($m) => $m->attachments() === []);

        $this->get($entry->statusUrl())->assertOk()->assertSee('エントリーを受け付けました')->assertSee('シルバー会員');
    }
}
