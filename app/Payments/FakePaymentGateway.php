<?php

namespace App\Payments;

use App\Models\Entry;
use Illuminate\Support\Str;

/**
 * テスト用。決済サービスに接続せずに決済の流れを再現する。
 */
class FakePaymentGateway implements PaymentGateway
{
    /** @var array<string, CheckoutSession> */
    public array $sessions = [];

    /** @var array<string, bool> */
    public array $expired = [];

    public function __construct(private string $prefix = 'cs_test_') {}

    public function createCheckout(Entry $entry, string $successUrl, string $cancelUrl): CheckoutSession
    {
        $id = $this->prefix.Str::random(24);

        return $this->sessions[$id] = new CheckoutSession($id, 'https://checkout.example.test/'.$id, CheckoutSession::UNPAID);
    }

    public function retrieveCheckout(string $sessionId): CheckoutSession
    {
        return $this->sessions[$sessionId];
    }

    public function expireCheckout(string $sessionId): bool
    {
        $session = $this->sessions[$sessionId];
        if ($session->isPaid() || $session->isAwaiting()) {
            return false;
        }

        return $this->expired[$sessionId] = true;
    }

    public function markPaid(string $sessionId, string $paymentId = 'pi_test_123'): void
    {
        $this->sessions[$sessionId] = new CheckoutSession($sessionId, null, CheckoutSession::PAID, $paymentId);
    }

    public function markAwaiting(string $sessionId, string $instructionsUrl = 'https://komoju.test/instructions'): void
    {
        $this->sessions[$sessionId] = new CheckoutSession($sessionId, null, CheckoutSession::AWAITING, 'pay_test', false, $instructionsUrl);
    }

    public function markAbandoned(string $sessionId): void
    {
        $this->sessions[$sessionId] = new CheckoutSession($sessionId, null, CheckoutSession::UNPAID, null, true);
    }
}
