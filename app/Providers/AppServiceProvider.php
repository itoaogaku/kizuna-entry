<?php

namespace App\Providers;

use App\Payments\PaymentGateway;
use App\Payments\StripePaymentGateway;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, fn () => new StripePaymentGateway(
            (string) config('services.stripe.secret')
        ));
    }

    public function boot(): void
    {
        //
    }
}
