<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Data\PaymentWebhookEvent;
use App\Enums\OrderStatus;
use App\Enums\PaymentWebhookEventType;
use App\Enums\WebhookOutcome;
use App\Events\OrderPaid;
use App\Models\Order;
use App\Models\WebhookEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HandlePaymentWebhook
{
    /**
     * Stores the event and applies it to the order, exactly once per event id.
     */
    public function execute(PaymentWebhookEvent $event): WebhookOutcome
    {
        try {
            return DB::transaction(fn (): WebhookOutcome => $this->process($event));
        } catch (UniqueConstraintViolationException $e) {
            // The event id is already stored (earlier or concurrent delivery). The whole
            // transaction rolled back, so this delivery changed nothing.
            if (WebhookEvent::query()->where('event_id', $event->eventId)->exists()) {
                return WebhookOutcome::Duplicate;
            }

            throw $e;
        }
    }

    private function process(PaymentWebhookEvent $event): WebhookOutcome
    {
        // Inserted first: a duplicate event id fails here, before anything else is touched.
        $record = WebhookEvent::query()->create([
            'event_id' => $event->eventId,
            'type' => $event->type,
            'payload' => $event->payload,
        ]);

        $outcome = $this->apply($event);

        $record->outcome = $outcome;
        $record->processed_at = now();
        $record->save();

        return $outcome;
    }

    private function apply(PaymentWebhookEvent $event): WebhookOutcome
    {
        $context = [
            'event_id' => $event->eventId,
            'type' => $event->type,
            'payment_reference' => $event->paymentReference,
        ];

        $type = PaymentWebhookEventType::tryFrom($event->type);

        if ($type === null) {
            Log::info('Payment webhook has an unhandled type.', $context);

            return WebhookOutcome::UnknownType;
        }

        $order = Order::query()
            ->where('payment_reference', $event->paymentReference)
            ->lockForUpdate()
            ->first();

        if ($order === null) {
            Log::warning('Payment webhook for an unknown payment reference.', $context);

            return WebhookOutcome::UnknownReference;
        }

        if ($event->amountCents !== $order->total_cents || $event->currency !== $order->currency) {
            Log::error('Payment webhook amount does not match the order.', $context + [
                'order_uuid' => $order->uuid,
                'expected' => ['amount_cents' => $order->total_cents, 'currency' => $order->currency],
                'received' => ['amount_cents' => $event->amountCents, 'currency' => $event->currency],
            ]);

            return WebhookOutcome::AmountMismatch;
        }

        $target = $type->targetStatus();

        if (! $order->status->canTransitionTo($target)) {
            // Out-of-order or repeated event (e.g. failed after paid). Stored, not applied.
            Log::info('Payment webhook ignored: transition not allowed.', $context + [
                'order_uuid' => $order->uuid,
                'from' => $order->status->value,
                'to' => $target->value,
            ]);

            return WebhookOutcome::IgnoredTransition;
        }

        $order->transitionTo($target);

        if ($target === OrderStatus::Paid) {
            // OrderPaid implements ShouldDispatchAfterCommit: listeners run only after commit.
            OrderPaid::dispatch($order);
        }

        return WebhookOutcome::Applied;
    }
}
