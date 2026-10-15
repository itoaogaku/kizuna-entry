<?php

namespace App\Payments;

use App\Models\Entry;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * KOMOJU のコンビニ払い。
 * 決済ページ（Session）で利用者がコンビニを選ぶと払込番号が発行され（payment: authorized）、
 * コンビニで支払うと入金済み（payment: captured）になる。
 */
class KomojuPaymentGateway implements PaymentGateway
{
    public function __construct(private string $secret, private string $baseUrl = 'https://komoju.com/api/v1') {}

    public function createCheckout(Entry $entry, string $successUrl, string $cancelUrl): CheckoutSession
    {
        $entry->loadMissing('meet', 'user');

        $session = $this->http()->post('/sessions', [
            'amount' => $entry->total_amount,
            'currency' => 'JPY',
            'return_url' => $successUrl, // 戻るときに ?session_id= が付く
            'default_locale' => 'ja',
            'email' => $entry->user->email,
            'payment_types' => ['konbini'],
            'expires_in_seconds' => 60 * 60,
            'metadata' => ['entry_id' => (string) $entry->id],
            'payment_data' => [
                'external_order_num' => self::orderNumber($entry),
                'name' => $entry->user->name,
            ],
        ])->throw()->json();

        return $this->toCheckoutSession($session);
    }

    public function retrieveCheckout(string $sessionId): CheckoutSession
    {
        return $this->toCheckoutSession($this->http()->get('/sessions/'.$sessionId)->throw()->json());
    }

    public function expireCheckout(string $sessionId): bool
    {
        $session = $this->retrieveCheckout($sessionId);
        if ($session->isPaid() || $session->isAwaiting()) {
            return false;
        }
        if ($session->closed) {
            return true;
        }

        try {
            $this->http()->post('/sessions/'.$sessionId.'/cancel')->throw();
        } catch (RequestException) {
            return $this->retrieveCheckout($sessionId)->isAbandoned();
        }

        return true;
    }

    /**
     * KOMOJUの管理画面で探しやすい注文番号。Webhookからエントリーを探すのにも使う。
     */
    public static function orderNumber(Entry $entry): string
    {
        return 'KIZUNA-'.$entry->id;
    }

    private function toCheckoutSession(array $session): CheckoutSession
    {
        $payment = $session['payment'] ?? null;
        $status = match ($payment['status'] ?? null) {
            'captured' => CheckoutSession::PAID,
            'authorized' => CheckoutSession::AWAITING,
            default => CheckoutSession::UNPAID,
        };
        $paymentClosed = in_array($payment['status'] ?? null, ['expired', 'cancelled', 'failed'], true);

        return new CheckoutSession(
            id: $session['id'],
            url: $session['session_url'] ?? null,
            paymentStatus: $status,
            paymentId: $payment['id'] ?? null,
            closed: ($session['status'] ?? null) === 'cancelled' || $paymentClosed,
            instructionsUrl: $payment['payment_details']['instructions_url'] ?? null,
        );
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withBasicAuth($this->secret, '')
            ->acceptJson()
            ->asJson()
            ->timeout(20);
    }
}
