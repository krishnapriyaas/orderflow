<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\OrderNoteExtractor;
use App\Contracts\PaymentGateway;
use App\Models\User;
use App\Services\OrderNotes\FakeOrderNoteExtractor;
use App\Services\OrderNotes\LlmOrderNoteExtractor;
use App\Services\Payment\FakePaymentGateway;
use App\Services\Payment\WebhookSignatureVerifier;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PaymentGateway::class, FakePaymentGateway::class);

        // Tests never use the real LLM. Without an API key the app falls back to the fake,
        // which sends every note to review.
        $this->app->singleton(OrderNoteExtractor::class, function (Application $app): OrderNoteExtractor {
            $config = config('services.anthropic');
            $key = is_array($config) ? ($config['key'] ?? null) : null;

            if ($app->environment('testing') || ! is_string($key) || $key === '') {
                return $app->make(FakeOrderNoteExtractor::class);
            }

            /** @var array{model: string, url: string, version: string, max_tokens: int, timeout_seconds: int} $config */
            return new LlmOrderNoteExtractor(
                apiKey: $key,
                model: $config['model'],
                url: $config['url'],
                apiVersion: $config['version'],
                maxTokens: $config['max_tokens'],
                timeoutSeconds: $config['timeout_seconds'],
            );
        });

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
        Gate::define('review-order-notes', fn (User $user): bool => $user->is_admin === true);
    }
}
