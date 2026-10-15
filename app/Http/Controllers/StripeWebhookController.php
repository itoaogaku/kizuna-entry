<?php

namespace App\Http\Controllers;

use App\Models\Entry;
use App\Services\EntryService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

/**
 * Stripeからの決済通知を受け取る。
 * Stripeダッシュボードで次のイベントを送るよう設定する：
 * checkout.session.completed / checkout.session.async_payment_succeeded /
 * checkout.session.async_payment_failed / checkout.session.expired
 */
class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, EntryService $entries): Response
    {
        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
                (string) config('services.stripe.webhook_secret'),
            );
        } catch (SignatureVerificationException|\UnexpectedValueException) {
            return response('invalid signature', 400);
        }

        $session = $event->data->object;
        $entry = isset($session->metadata->entry_id)
            ? Entry::find($session->metadata->entry_id)
            : null;

        if (! $entry || $entry->payment_session_id !== $session->id) {
            Log::warning('Stripe webhook: entry not found', ['type' => $event->type, 'session' => $session->id ?? null]);

            return response('ignored');
        }

        switch ($event->type) {
            case 'checkout.session.completed':
            case 'checkout.session.async_payment_succeeded':
                if ($session->payment_status === 'paid') {
                    $paymentIntent = $session->payment_intent;
                    $entries->markPaid($entry, is_string($paymentIntent) ? $paymentIntent : $paymentIntent?->id);
                }
                break;
            case 'checkout.session.expired':
            case 'checkout.session.async_payment_failed':
                $entries->cancelByWebhook($entry);
                break;
        }

        return response('ok');
    }
}
