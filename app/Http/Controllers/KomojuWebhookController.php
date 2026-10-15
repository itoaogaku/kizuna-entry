<?php

namespace App\Http\Controllers;

use App\Models\Entry;
use App\Services\EntryService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * KOMOJU（コンビニ払い）からの通知を受け取る。
 * KOMOJU管理画面のWebhookで次のイベントを送るよう設定する：
 * payment.authorized（払込番号の発行）/ payment.captured（入金）/ payment.expired / payment.cancelled / payment.failed
 *
 * 通知の中身はきっかけとしてだけ使い、状態はKOMOJUのAPIに問い合わせて確かめる。
 */
class KomojuWebhookController extends Controller
{
    public function __invoke(Request $request, EntryService $entries): Response
    {
        $secret = (string) config('services.komoju.webhook_secret');
        $signature = (string) $request->header('X-Komoju-Signature');
        if ($secret === '' || ! hash_equals(hash_hmac('sha256', $request->getContent(), $secret), $signature)) {
            return response('invalid signature', 400);
        }

        $payment = $request->json('data', []);
        $entry = $this->findEntry($payment);
        if (! $entry || $entry->payment_method !== Entry::METHOD_KONBINI) {
            Log::warning('KOMOJU webhook: entry not found', ['type' => $request->json('type'), 'payment' => $payment['id'] ?? null]);

            return response('ignored');
        }

        $entries->syncPayment($entry);

        return response('ok');
    }

    private function findEntry(array $payment): ?Entry
    {
        if (preg_match('/^KIZUNA-(\d+)$/', (string) ($payment['external_order_num'] ?? ''), $m)) {
            return Entry::find($m[1]);
        }
        if (! empty($payment['session'])) {
            return Entry::where('payment_session_id', $payment['session'])->first();
        }
        if (! empty($payment['metadata']['entry_id'])) {
            return Entry::find($payment['metadata']['entry_id']);
        }

        return null;
    }
}
