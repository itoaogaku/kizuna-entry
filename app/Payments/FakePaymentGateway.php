<?php

namespace App\Payments;

use App\Models\Entry;
use Illuminate\Support\Str;

/**
 * テスト用。Stripeに接続せずに決済の流れを再現する。
 */
class FakePaymentGateway implements PaymentGateway
{
    /** @var array<string, CheckoutSession> */
    public array $sessions = [];

    /** @var array<string, bool> */
    public array $expired = [];

    public function createCheckout(Entry $entry, string $successUrl, string $cancelUrl): CheckoutSession
    {
        $id = 'cs_test_'.Str::random(24);

        return $this->sessions[$id] = new CheckoutSession($id, 'https://checkout.stripe.test/'.$id, 'unpaid');
    }

    public function retrieveCheckout(string $sessionId): CheckoutSession
    {
        return $this->sessions[$sessionId];
    }

    public function expireCheckout(string $sessionId): bool
    {
        if ($this->sessions[$sessionId]->isPaid()) {
            return false;
        }

        return $this->expired[$sessionId] = true;
    }

    public function markPaid(string $sessionId, string $paymentIntentId = 'pi_test_123'): void
    {
        $this->sessions[$sessionId] = new CheckoutSession($sessionId, null, 'paid', $paymentIntentId);
    }
}
