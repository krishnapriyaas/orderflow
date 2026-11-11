<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\PaymentGateway;
use App\Services\Payment\FakePaymentGateway;
use App\Services\Payment\WebhookSignatureVerifier;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PaymentGateway::class, FakePaymentGateway::class);

        // Not a singleton: config is read on every resolve, so tests can change it.
        $this->app->bind(WebhookSignatureVerifier::class, function (): WebhookSignatureVerifier {
            $secret = config('services.payment.webhook_secret');
            $tolerance = config('services.payment.webhook_tolerance_seconds');

            return new WebhookSignatureVerifier(
                secret: is_string($secret) ? $secret : '',
                toleranceSeconds: is_int($tolerance) ? $tolerance : 300,
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
