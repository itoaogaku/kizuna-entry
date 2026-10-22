<?php

namespace App\Payments;

use App\Models\Entry;
use Stripe\Checkout\Session;
use Stripe\Exception\InvalidRequestException;
use Stripe\StripeClient;

class StripePaymentGateway implements PaymentGateway
{
    private ?StripeClient $client = null;

    public function __construct(private string $secret) {}

    /**
     * キー未設定の環境でもアプリが起動するよう、実際に使うときに接続を作る。
     */
    private function stripe(): StripeClient
    {
        return $this->client ??= new StripeClient($this->secret);
    }

    public function createCheckout(Entry $entry, string $successUrl, string $cancelUrl): CheckoutSession
    {
        $entry->loadMissing('items.event', 'meet', 'user');

        // 種目・参加料ごとに「参加料 × 人数」でまとめる（区分別の参加料なら同じ種目でも金額が分かれる）
        $lineItems = $entry->items
            ->groupBy(fn ($item) => $item->event_id.'-'.$item->fee)
            ->map(fn ($items) => [
                'price_data' => [
                    'currency' => 'jpy',
                    'unit_amount' => $items->first()->fee,
                    'product_data' => ['name' => $entry->meet->name.' '.$items->first()->event->name.' 参加料（'.number_format($items->first()->fee).'円）'],
                ],
                'quantity' => $items->count(),
            ])
            ->values()
            ->all();

        // カードだけで受ける（コンビニはKOMOJU）。Wixでも同じStripeアカウントを使っているので、
        // ダッシュボードの決済手段の設定は変えずに、ここで指定する。Linkが有効ならカード入力の画面で使える
        $session = $this->stripe()->checkout->sessions->create([
            'mode' => 'payment',
            'payment_method_types' => ['card'],
            'line_items' => $lineItems,
            'customer_email' => $entry->user->email,
            'client_reference_id' => (string) $entry->id,
            'metadata' => ['entry_id' => (string) $entry->id],
            'payment_intent_data' => [
                'metadata' => ['entry_id' => (string) $entry->id],
                'description' => $entry->meet->name.' エントリー #'.$entry->id.'（'.$entry->user->displayName().'）',
            ],
            'expires_at' => now()->addMinutes(60)->timestamp,
            'success_url' => $successUrl.(str_contains($successUrl, '?') ? '&' : '?').'session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
        ]);

        return $this->toCheckoutSession($session);
    }

    public function retrieveCheckout(string $sessionId): CheckoutSession
    {
        return $this->toCheckoutSession($this->stripe()->checkout->sessions->retrieve($sessionId));
    }

    public function expireCheckout(string $sessionId): bool
    {
        $session = $this->stripe()->checkout->sessions->retrieve($sessionId);
        if ($session->status === 'expired') {
            return true;
        }
        if ($session->status !== 'open') {
            return false;
        }

        try {
            $this->stripe()->checkout->sessions->expire($sessionId);
        } catch (InvalidRequestException) {
            return false;
        }

        return true;
    }

    private function toCheckoutSession(Session $session): CheckoutSession
    {
        $paymentIntent = $session->payment_intent;

        return new CheckoutSession(
            id: $session->id,
            url: $session->url,
            paymentStatus: $session->payment_status === 'paid' ? CheckoutSession::PAID : CheckoutSession::UNPAID,
            paymentId: is_string($paymentIntent) ? $paymentIntent : $paymentIntent?->id,
            closed: $session->status === 'expired',
        );
    }
}
