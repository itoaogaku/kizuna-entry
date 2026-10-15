<?php

namespace App\Payments;

use App\Models\Entry;

/**
 * 支払い方法ごとの決済サービス。カードは Stripe、コンビニ払いは KOMOJU。
 */
class PaymentGateways
{
    public const METHODS = [
        Entry::METHOD_CARD => 'クレジットカード',
        Entry::METHOD_KONBINI => 'コンビニ払い',
    ];

    /**
     * @param  array<string, \Closure(): PaymentGateway>  $factories  支払い方法 → 決済サービスを作る関数
     */
    public function __construct(private array $factories) {}

    /** @var array<string, PaymentGateway> */
    private array $resolved = [];

    public function for(string $method): PaymentGateway
    {
        return $this->resolved[$method] ??= ($this->factories[$method] ?? throw new \InvalidArgumentException("Unknown payment method: $method"))();
    }

    public function forEntry(Entry $entry): PaymentGateway
    {
        return $this->for($entry->payment_method);
    }

    /**
     * 使える支払い方法（キーが設定されているもの）。
     *
     * @return array<string, string>
     */
    public static function available(): array
    {
        return array_filter([
            Entry::METHOD_CARD => config('services.stripe.secret') ? self::METHODS[Entry::METHOD_CARD] : null,
            Entry::METHOD_KONBINI => config('services.komoju.secret') ? self::METHODS[Entry::METHOD_KONBINI] : null,
        ]);
    }
}
