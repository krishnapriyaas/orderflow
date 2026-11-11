<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use App\Data\PaymentIntent;
use App\Enums\PaymentIntentStatus;
use App\Exceptions\PaymentGatewayException;
use App\Models\Order;
use Illuminate\Support\Str;

/**
 * In-memory payment gateway. No network calls. Used locally and in tests.
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
}
