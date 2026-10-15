<?php

namespace App\Payments;

use App\Models\Entry;

interface PaymentGateway
{
    /**
     * エントリーの決済ページを作成する。
     */
    public function createCheckout(Entry $entry, string $successUrl, string $cancelUrl): CheckoutSession;

    public function retrieveCheckout(string $sessionId): CheckoutSession;

    /**
     * 未完了の決済ページを無効にする。すでに支払い済み・コンビニの番号発行後などで無効にできなければ false。
     */
    public function expireCheckout(string $sessionId): bool;
}
