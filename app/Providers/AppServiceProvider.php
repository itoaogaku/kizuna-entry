<?php

namespace App\Providers;

use App\Models\Entry;
use App\Payments\KomojuPaymentGateway;
use App\Payments\PaymentGateways;
use App\Payments\StripePaymentGateway;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentGateways::class, fn () => new PaymentGateways([
            Entry::METHOD_CARD => fn () => new StripePaymentGateway((string) config('services.stripe.secret')),
            Entry::METHOD_KONBINI => fn () => new KomojuPaymentGateway((string) config('services.komoju.secret')),
        ]));
    }

    public function boot(): void
    {
        //
    }
}
