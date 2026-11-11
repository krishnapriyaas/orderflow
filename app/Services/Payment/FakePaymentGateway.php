<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use App\Data\PaymentIntent;
use App\Data\SignedWebhook;
use App\Enums\PaymentIntentStatus;
use App\Exceptions\PaymentGatewayException;
use App\Models\Order;
use Illuminate\Support\Str;

/**
 * In-memory payment gateway. No network calls. Used locally and in tests.
 * It can also build correctly signed webhook payloads for tests.
 */
class FakePaymentGateway implements PaymentGateway
{
    private bool $failNextCall = false;

    /** @var list<PaymentIntent> */
    private array $created = [];

    public function createPayment(Order $order): PaymentIntent
    {
        if ($this->failNextCall) {
            $this->failNextCall = false;

            throw new PaymentGatewayException('Fake payment gateway failure.');
        }

        $reference = 'fake_pay_'.Str::uuid()->toString();

        $intent = new PaymentIntent(
            reference: $reference,
            status: PaymentIntentStatus::RequiresAction,
            redirectUrl: rtrim((string) config('app.url'), '/').'/fake-pay/'.$reference,
        );

        $this->created[] = $intent;

        return $intent;
    }

    public function failNextCall(): void
    {
        $this->failNextCall = true;
    }

    /**
     * @return list<PaymentIntent>
     */
    public function createdPayments(): array
    {
        return $this->created;
    }

    /**
     * Builds a webhook body the way the provider would send it, signed with the
     * configured PAYMENT_WEBHOOK_SECRET.
     */
    public function signedWebhook(
        string $type,
        string $paymentReference,
        int $amountCents,
        string $currency = 'EUR',
        ?string $eventId = null,
        ?int $timestamp = null,
    ): SignedWebhook {
        $body = json_encode([
            'id' => $eventId ?? 'evt_'.Str::uuid()->toString(),
            'type' => $type,
            'data' => [
                'payment_reference' => $paymentReference,
                'amount_cents' => $amountCents,
                'currency' => $currency,
            ],
        ], JSON_THROW_ON_ERROR);

        return $this->signWebhookBody($body, $timestamp);
    }

    /**
     * Signs any raw body. Use it to send a malformed but correctly signed payload.
     */
    public function signWebhookBody(string $body, ?int $timestamp = null, ?string $secret = null): SignedWebhook
    {
        $timestamp ??= now()->getTimestamp();
        $configured = config('services.payment.webhook_secret');
        $secret ??= is_string($configured) ? $configured : '';

        return new SignedWebhook(
            body: $body,
            timestamp: $timestamp,
            signature: WebhookSignature::sign((string) $timestamp, $body, $secret),
        );
    }
}
