<?php

namespace App\Payments;

/**
 * 決済サービス側の決済セッションの状態。
 */
final readonly class CheckoutSession
{
    public function __construct(
        public string $id,
        public ?string $url,
        public string $paymentStatus, // paid / unpaid / no_payment_required
        public ?string $paymentIntentId = null,
    ) {}

    public function isPaid(): bool
    {
        return $this->paymentStatus === 'paid';
    }
}
