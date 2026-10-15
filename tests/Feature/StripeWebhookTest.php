<?php

namespace Tests\Feature;

use App\Mail\EntryPaidMail;
use App\Models\Athlete;
use App\Models\Entry;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private Entry $entry;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $team = User::factory()->create();
        $event = Event::factory()->create(['fee' => 2000]);
        $athlete = Athlete::factory()->for($team)->create();
        $this->entry = $team->entries()->create([
            'meet_id' => $event->meet_id,
            'status' => Entry::STATUS_PENDING,
            'total_amount' => 2000,
            'payment_session_id' => 'cs_test_1',
        ]);
        $this->entry->items()->create(['athlete_id' => $athlete->id, 'event_id' => $event->id, 'fee' => 2000]);
    }

    private function sendWebhook(string $type, array $session, ?string $secret = 'whsec_test_secret'): TestResponse
    {
        $payload = json_encode([
            'id' => 'evt_1',
            'object' => 'event',
            'type' => $type,
            'data' => ['object' => $session + ['object' => 'checkout.session']],
        ]);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        return $this->call('POST', '/stripe/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t=$timestamp,v1=$signature",
            'CONTENT_TYPE' => 'application/json',
        ], $payload);
    }

    private function checkoutSession(array $overrides = []): array
    {
        return $overrides + [
            'id' => 'cs_test_1',
            'payment_status' => 'paid',
            'payment_intent' => 'pi_123',
            'metadata' => ['entry_id' => (string) $this->entry->id],
        ];
    }

    public function test_completed_checkout_marks_entry_paid_and_sends_mail(): void
    {
        $this->sendWebhook('checkout.session.completed', $this->checkoutSession())->assertOk();

        $this->entry->refresh();
        $this->assertTrue($this->entry->isPaid());
        $this->assertSame('pi_123', $this->entry->payment_id);
        $this->assertNotNull($this->entry->paid_at);
        Mail::assertSent(EntryPaidMail::class, 1);
    }

    public function test_duplicate_webhook_sends_mail_only_once(): void
    {
        $this->sendWebhook('checkout.session.completed', $this->checkoutSession())->assertOk();
        $this->sendWebhook('checkout.session.completed', $this->checkoutSession())->assertOk();

        Mail::assertSent(EntryPaidMail::class, 1);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $this->sendWebhook('checkout.session.completed', $this->checkoutSession(), 'whsec_wrong')->assertStatus(400);

        $this->assertFalse($this->entry->fresh()->isPaid());
    }

    public function test_unpaid_completion_such_as_konbini_waits_for_async_payment(): void
    {
        $this->sendWebhook('checkout.session.completed', $this->checkoutSession(['payment_status' => 'unpaid']))->assertOk();
        $this->assertSame(Entry::STATUS_PENDING, $this->entry->fresh()->status);

        $this->sendWebhook('checkout.session.async_payment_succeeded', $this->checkoutSession())->assertOk();
        $this->assertTrue($this->entry->fresh()->isPaid());
    }

    public function test_expired_checkout_cancels_pending_entry(): void
    {
        $this->sendWebhook('checkout.session.expired', $this->checkoutSession(['payment_status' => 'unpaid']))->assertOk();

        $this->assertSame(Entry::STATUS_CANCELLED, $this->entry->fresh()->status);
    }

    public function test_session_mismatch_is_ignored(): void
    {
        $this->sendWebhook('checkout.session.completed', $this->checkoutSession(['id' => 'cs_other']))->assertOk();

        $this->assertFalse($this->entry->fresh()->isPaid());
    }
}
