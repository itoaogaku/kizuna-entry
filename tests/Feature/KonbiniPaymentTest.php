<?php

namespace Tests\Feature;

use App\Mail\EntryPaidMail;
use App\Models\Athlete;
use App\Models\Entry;
use App\Models\Event;
use App\Models\Meet;
use App\Models\User;
use App\Payments\FakePaymentGateway;
use App\Payments\KomojuPaymentGateway;
use App\Payments\PaymentGateways;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class KonbiniPaymentTest extends TestCase
{
    use RefreshDatabase;

    private FakePaymentGateway $konbini;

    private User $team;

    private Meet $meet;

    private Event $event;

    private Athlete $athlete;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config([
            'services.stripe.secret' => 'sk_test',
            'services.komoju.secret' => 'komoju_test',
            'services.komoju.webhook_secret' => 'komoju_whsec',
        ]);
        $this->konbini = new FakePaymentGateway('komoju_');
        $this->app->instance(PaymentGateways::class, new PaymentGateways([
            Entry::METHOD_CARD => fn () => new FakePaymentGateway('cs_test_'),
            Entry::METHOD_KONBINI => fn () => $this->konbini,
        ]));

        $this->team = User::factory()->create();
        $this->meet = Meet::factory()->create();
        $this->event = Event::factory()->for($this->meet)->create(['fee' => 2200]);
        $this->athlete = Athlete::factory()->for($this->team)->create();
    }

    private function enterWithKonbini(): Entry
    {
        $this->actingAs($this->team)->post(route('entries.review', $this->meet), ['items' => [
            $this->athlete->id => ['selected' => '1', 'events' => [['event_id' => $this->event->id, 'record' => '1430']]],
        ]]);
        $this->actingAs($this->team)->get(route('entries.confirm', $this->meet))
            ->assertSee('コンビニ払い')
            ->assertSee('クレジットカード');
        $response = $this->actingAs($this->team)->post(route('entries.store', $this->meet), ['payment_method' => 'konbini', 'terms' => '1']);
        $entry = Entry::sole();
        $response->assertRedirect('https://checkout.example.test/'.$entry->payment_session_id);

        return $entry;
    }

    private function komojuWebhook(string $type, array $payment, string $secret = 'komoju_whsec'): TestResponse
    {
        $body = json_encode(['id' => 'evt_1', 'type' => $type, 'data' => $payment]);

        return $this->call('POST', '/komoju/webhook', [], [], [], [
            'HTTP_X_KOMOJU_SIGNATURE' => hash_hmac('sha256', $body, $secret),
            'CONTENT_TYPE' => 'application/json',
        ], $body);
    }

    public function test_konbini_entry_waits_for_payment_then_becomes_paid_by_webhook(): void
    {
        $entry = $this->enterWithKonbini();
        $this->assertSame(Entry::METHOD_KONBINI, $entry->payment_method);
        $this->assertSame("14'30", $entry->items->first()->declared_record); // 数字だけの入力を直す

        // 払込番号が発行されて戻ってくる
        $this->konbini->markAwaiting($entry->payment_session_id, 'https://komoju.test/slip/1');
        $this->actingAs($this->team)->get(route('entries.complete', ['entry' => $entry, 'session_id' => $entry->payment_session_id]))
            ->assertOk()
            ->assertSee('コンビニでお支払いください')
            ->assertSee('https://komoju.test/slip/1');
        $entry->refresh();
        $this->assertSame(Entry::STATUS_PENDING, $entry->status);
        $this->assertSame('コンビニ支払待ち', $entry->statusLabel());

        // 番号発行後は利用者が取り消せない（二重払いを防ぐ）
        $this->actingAs($this->team)->post(route('entries.cancel', $entry))->assertSessionHasErrors();
        $this->assertSame(Entry::STATUS_PENDING, $entry->fresh()->status);

        // コンビニで支払われた通知
        $this->konbini->markPaid($entry->payment_session_id, 'pay_123');
        $this->komojuWebhook('payment.captured', ['id' => 'pay_123', 'status' => 'captured', 'external_order_num' => 'KIZUNA-'.$entry->id])
            ->assertOk();

        $entry->refresh();
        $this->assertTrue($entry->isPaid());
        $this->assertSame('pay_123', $entry->payment_id);
        Mail::assertSent(EntryPaidMail::class, 1);
    }

    public function test_expired_konbini_payment_frees_the_slot(): void
    {
        $entry = $this->enterWithKonbini();
        $this->konbini->markAbandoned($entry->payment_session_id);

        $this->komojuWebhook('payment.expired', ['id' => 'pay_1', 'status' => 'expired', 'external_order_num' => 'KIZUNA-'.$entry->id])
            ->assertOk();

        $this->assertSame(Entry::STATUS_CANCELLED, $entry->fresh()->status);
    }

    public function test_giving_up_on_komoju_page_restores_the_selection(): void
    {
        $entry = $this->enterWithKonbini();
        $this->konbini->markAbandoned($entry->payment_session_id);

        $this->actingAs($this->team)->get(route('entries.complete', ['entry' => $entry, 'session_id' => $entry->payment_session_id]))
            ->assertRedirect(route('entries.create', $this->meet));

        $this->assertSame(Entry::STATUS_CANCELLED, $entry->fresh()->status);
    }

    public function test_komoju_webhook_with_bad_signature_is_rejected(): void
    {
        $entry = $this->enterWithKonbini();
        $this->konbini->markPaid($entry->payment_session_id);

        $this->komojuWebhook('payment.captured', ['external_order_num' => 'KIZUNA-'.$entry->id], 'wrong')->assertStatus(400);

        $this->assertFalse($entry->fresh()->isPaid());
    }

    public function test_konbini_is_not_offered_without_komoju_key(): void
    {
        config(['services.komoju.secret' => null]);
        $this->actingAs($this->team)->post(route('entries.review', $this->meet), ['items' => [
            $this->athlete->id => ['selected' => '1', 'events' => [['event_id' => $this->event->id, 'record' => "14'30"]]],
        ]]);

        $this->actingAs($this->team)->get(route('entries.confirm', $this->meet))->assertDontSee('コンビニ払い');
        $this->actingAs($this->team)->post(route('entries.store', $this->meet), ['payment_method' => 'konbini', 'terms' => '1'])
            ->assertSessionHasErrors('payment_method');
        $this->assertDatabaseCount('entries', 0);
    }

    public function test_komoju_gateway_creates_a_konbini_session_and_reads_its_state(): void
    {
        Http::fake([
            'komoju.com/api/v1/sessions' => Http::response(['id' => 'sess_1', 'session_url' => 'https://komoju.com/sessions/sess_1', 'status' => 'pending', 'payment' => null]),
            'komoju.com/api/v1/sessions/sess_1' => Http::response([
                'id' => 'sess_1', 'status' => 'completed',
                'payment' => ['id' => 'pay_1', 'status' => 'authorized', 'payment_details' => ['instructions_url' => 'https://komoju.com/slip']],
            ]),
        ]);
        $entry = $this->team->entries()->create(['meet_id' => $this->meet->id, 'status' => 'pending', 'total_amount' => 2200, 'payment_method' => 'konbini']);
        $gateway = new KomojuPaymentGateway('sk_komoju');

        $created = $gateway->createCheckout($entry, 'https://entry.test/complete', 'https://entry.test/cancel');
        $this->assertSame('https://komoju.com/sessions/sess_1', $created->url);
        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://komoju.com/api/v1/sessions'
            && $r['amount'] === 2200 && $r['currency'] === 'JPY' && $r['payment_types'] === ['konbini']
            && $r['payment_data']['external_order_num'] === 'KIZUNA-'.$entry->id
            && $r->hasHeader('Authorization', 'Basic '.base64_encode('sk_komoju:')));

        $session = $gateway->retrieveCheckout('sess_1');
        $this->assertTrue($session->isAwaiting());
        $this->assertSame('https://komoju.com/slip', $session->instructionsUrl);
        $this->assertFalse($gateway->expireCheckout('sess_1')); // 番号発行後は取り消さない
    }
}
