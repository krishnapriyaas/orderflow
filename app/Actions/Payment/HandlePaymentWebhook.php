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
use LogicException;

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
            $existing = WebhookEvent::query()->where('event_id', $event->eventId)->first();

            if ($existing === null) {
                throw $e;
            }

            $this->warnIfPayloadDiffers($existing, $event);

            return WebhookOutcome::Duplicate;
        }
    }

    /**
     * A redelivery should carry the same body. A different body under the same id
     * is not applied (the first one wins), but someone should know about it.
     */
    private function warnIfPayloadDiffers(WebhookEvent $existing, PaymentWebhookEvent $event): void
    {
        if ($existing->payload === $event->payload) {
            return;
        }

        Log::warning('Payment webhook duplicate event id with a different payload.', [
            'event_id' => $event->eventId,
            'stored_type' => $existing->type,
            'received_type' => $event->type,
        ]);
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

        if ($event->paymentReference === null || $event->amountCents === null || $event->currency === null) {
            throw new LogicException('PaymentWebhookRequest requires the payment fields for known event types.');
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
