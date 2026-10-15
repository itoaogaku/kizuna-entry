<?php

namespace App\Payments;

/**
 * 決済サービス側の決済ページ（Stripe Checkout / KOMOJU Session）の状態。
 */
final readonly class CheckoutSession
{
    public const PAID = 'paid';

    /** コンビニ払いの番号が発行され、支払いを待っている */
    public const AWAITING = 'awaiting';

    public const UNPAID = 'unpaid';

    public function __construct(
        public string $id,
        public ?string $url,
        public string $paymentStatus,
        public ?string $paymentId = null,
        public bool $closed = false, // 期限切れ・キャンセルで、もう支払えない
        public ?string $instructionsUrl = null,
    ) {}

    public function isPaid(): bool
    {
        return $this->paymentStatus === self::PAID;
    }

    public function isAwaiting(): bool
    {
        return $this->paymentStatus === self::AWAITING;
    }

    /**
     * 支払われないまま終わった（期限切れ・キャンセル）。
     */
    public function isAbandoned(): bool
    {
        return $this->closed && ! $this->isPaid() && ! $this->isAwaiting();
    }
}
