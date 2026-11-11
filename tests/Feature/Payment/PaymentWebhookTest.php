<?php

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Data\SignedWebhook;
use App\Enums\OrderStatus;
use App\Enums\WebhookOutcome;
use App\Events\OrderPaid;
use App\Jobs\ExtractOrderNote;
use App\Models\Order;
use App\Models\WebhookEvent;
use App\Services\Payment\FakePaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    private FakePaymentGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new FakePaymentGateway;
    }

    private function order(OrderStatus $status = OrderStatus::PendingPayment): Order
    {
        return Order::factory()->withStatus($status)->create([
            'payment_reference' => 'fake_pay_123',
            'total_cents' => 6670,
            'currency' => 'EUR',
        ]);
    }

    private function signed(string $type, ?string $eventId = null, int $amountCents = 6670, string $currency = 'EUR', string $reference = 'fake_pay_123'): SignedWebhook
    {
        return $this->gateway->signedWebhook($type, $reference, $amountCents, $currency, $eventId);
    }

    /**
     * Sends the exact raw body. postJson() would re-encode it and break the signature.
     *
     * @param  array<string, string>|null  $headers
     * @return TestResponse<Response>
     */
    private function send(SignedWebhook $webhook, ?array $headers = null, ?string $body = null): TestResponse
    {
        return $this->call(
            'POST',
            '/api/webhooks/payment',
            server: $this->transformHeadersToServerVars(($headers ?? $webhook->headers()) + ['Accept' => 'application/json']),
            content: $body ?? $webhook->body,
        );
    }

    private function assertNothingWritten(Order $order, OrderStatus $status = OrderStatus::PendingPayment): void
    {
        $this->assertSame(0, WebhookEvent::query()->count());
        $this->assertSame($status, $order->fresh()?->status);
    }

    // --- Happy paths ---------------------------------------------------------

    public function test_a_succeeded_event_marks_the_order_paid(): void
    {
        $order = $this->order();

        $this->send($this->signed('payment.succeeded', 'evt_1'))
            ->assertOk()
            ->assertExactJson(['outcome' => 'applied']);

        $this->assertSame(OrderStatus::Paid, $order->fresh()?->status);

        $event = WebhookEvent::query()->sole();
        $this->assertSame('evt_1', $event->event_id);
        $this->assertSame('payment.succeeded', $event->type);
        $this->assertSame(WebhookOutcome::Applied, $event->outcome);
        $this->assertNotNull($event->processed_at);
        $this->assertSame('fake_pay_123', $event->payload['data']['payment_reference']);
    }

    public function test_a_failed_event_marks_the_order_payment_failed(): void
    {
        Event::fake([OrderPaid::class]);
        $order = $this->order();

        $this->send($this->signed('payment.failed'))
            ->assertOk()
            ->assertExactJson(['outcome' => 'applied']);

        $this->assertSame(OrderStatus::PaymentFailed, $order->fresh()?->status);
        $this->assertSame(WebhookOutcome::Applied, WebhookEvent::query()->sole()->outcome);
        Event::assertNotDispatched(OrderPaid::class);
    }

    public function test_a_succeeded_event_after_a_failed_one_marks_the_order_paid(): void
    {
        Event::fake([OrderPaid::class]);
        $order = $this->order(OrderStatus::PaymentFailed);

        $this->send($this->signed('payment.succeeded'))
            ->assertOk()
            ->assertExactJson(['outcome' => 'applied']);

        $this->assertSame(OrderStatus::Paid, $order->fresh()?->status);
        Event::assertDispatchedTimes(OrderPaid::class, 1);
    }

    // --- Signature -----------------------------------------------------------

    public function test_a_wrong_signature_is_rejected(): void
    {
        $order = $this->order();
        $webhook = $this->signed('payment.succeeded');

        $this->send($webhook, ['X-Signature' => str_repeat('0', 64)] + $webhook->headers())
            ->assertUnauthorized();

        $this->assertNothingWritten($order);
    }

    public function test_a_signature_made_with_another_secret_is_rejected(): void
    {
        $order = $this->order();
        $body = $this->signed('payment.succeeded')->body;

        $this->send($this->gateway->signWebhookBody($body, secret: 'not-the-secret'))
            ->assertUnauthorized();

        $this->assertNothingWritten($order);
    }

    public function test_a_tampered_body_is_rejected(): void
    {
        $order = $this->order();
        $webhook = $this->signed('payment.failed');
        $tampered = str_replace('payment.failed', 'payment.succeeded', $webhook->body);

        $this->send($webhook, body: $tampered)->assertUnauthorized();

        $this->assertNothingWritten($order);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function signatureHeaders(): array
    {
        return [
            'no X-Signature' => ['X-Signature'],
            'no X-Timestamp' => ['X-Timestamp'],
        ];
    }

    #[DataProvider('signatureHeaders')]
    public function test_a_missing_header_is_rejected(string $header): void
    {
        $order = $this->order();
        $webhook = $this->signed('payment.succeeded');
        $headers = $webhook->headers();
        unset($headers[$header]);

        $this->send($webhook, $headers)->assertUnauthorized();

        $this->assertNothingWritten($order);
    }

    public function test_a_timestamp_older_than_five_minutes_is_rejected(): void
    {
        $order = $this->order();
        $webhook = $this->signed('payment.succeeded');

        $this->travel(301)->seconds();

        $this->send($webhook)->assertUnauthorized();

        $this->assertNothingWritten($order);
    }

    public function test_a_timestamp_more_than_five_minutes_in_the_future_is_rejected(): void
    {
        $order = $this->order();
        $webhook = $this->signed('payment.succeeded');

        $this->travel(-301)->seconds();

        $this->send($webhook)->assertUnauthorized();

        $this->assertNothingWritten($order);
    }

    public function test_every_webhook_is_rejected_when_no_secret_is_configured(): void
    {
        config(['services.payment.webhook_secret' => null]);
        $order = $this->order();

        // Signed with the empty secret, which must not be accepted.
        $this->send($this->gateway->signWebhookBody($this->signed('payment.succeeded')->body, secret: ''))
            ->assertUnauthorized();

        $this->assertNothingWritten($order);
    }

    // --- Payload -------------------------------------------------------------

    /**
     * @return array<string, array{0: string}>
     */
    public static function malformedBodies(): array
    {
        $data = ['payment_reference' => 'fake_pay_123', 'amount_cents' => 6670, 'currency' => 'EUR'];
        $encode = fn (array $body): string => json_encode($body, JSON_THROW_ON_ERROR);

        return [
            'not json' => ['not json'],
            'no id' => [$encode(['type' => 'payment.succeeded', 'data' => $data])],
            'no type' => [$encode(['id' => 'evt_1', 'data' => $data])],
            'no payment reference' => [$encode(['id' => 'evt_1', 'type' => 'payment.succeeded', 'data' => ['payment_reference' => null] + $data])],
            'amount not an integer' => [$encode(['id' => 'evt_1', 'type' => 'payment.succeeded', 'data' => ['amount_cents' => '66.70'] + $data])],
            'no currency' => [$encode(['id' => 'evt_1', 'type' => 'payment.succeeded', 'data' => ['currency' => null] + $data])],
        ];
    }

    #[DataProvider('malformedBodies')]
    public function test_a_signed_but_malformed_payload_is_rejected_and_logged(string $body): void
    {
        Log::spy();
        $order = $this->order();

        $this->send($this->gateway->signWebhookBody($body))->assertUnprocessable();

        $this->assertNothingWritten($order);
        Log::shouldHaveReceived('error')->once()->withArgs(
            fn (string $message): bool => $message === 'Payment webhook payload is malformed.',
        );
    }

    // --- Duplicates and out-of-order events ----------------------------------

    public function test_the_same_event_id_is_processed_only_once(): void
    {
        Event::fake([OrderPaid::class]);
        $order = $this->order();
        $webhook = $this->signed('payment.succeeded', 'evt_dup');

        $this->send($webhook)->assertOk()->assertExactJson(['outcome' => 'applied']);
        $this->send($webhook)->assertOk()->assertExactJson(['outcome' => 'duplicate']);

        $this->assertSame(1, WebhookEvent::query()->count());
        $this->assertSame(WebhookOutcome::Applied, WebhookEvent::query()->sole()->outcome);
        $this->assertSame(OrderStatus::Paid, $order->fresh()?->status);
        Event::assertDispatchedTimes(OrderPaid::class, 1);
    }

    public function test_a_duplicate_event_id_does_not_apply_a_different_payload(): void
    {
        $order = $this->order();

        $this->send($this->signed('payment.failed', 'evt_dup'))->assertOk();
        $this->send($this->signed('payment.succeeded', 'evt_dup'))
            ->assertOk()
            ->assertExactJson(['outcome' => 'duplicate']);

        $this->assertSame(OrderStatus::PaymentFailed, $order->fresh()?->status);
        $this->assertSame('payment.failed', WebhookEvent::query()->sole()->type);
    }

    public function test_a_failed_event_after_paid_is_stored_but_does_not_change_the_status(): void
    {
        Log::spy();
        $order = $this->order(OrderStatus::Paid);

        $this->send($this->signed('payment.failed', 'evt_late'))
            ->assertOk()
            ->assertExactJson(['outcome' => 'ignored_transition']);

        $this->assertSame(OrderStatus::Paid, $order->fresh()?->status);
        $event = WebhookEvent::query()->sole();
        $this->assertSame('evt_late', $event->event_id);
        $this->assertSame(WebhookOutcome::IgnoredTransition, $event->outcome);
        $this->assertNotNull($event->processed_at);
    }

    public function test_a_second_succeeded_event_with_a_new_id_does_not_dispatch_order_paid_again(): void
    {
        Event::fake([OrderPaid::class]);
        $this->order();

        $this->send($this->signed('payment.succeeded', 'evt_a'))->assertExactJson(['outcome' => 'applied']);
        $this->send($this->signed('payment.succeeded', 'evt_b'))->assertExactJson(['outcome' => 'ignored_transition']);

        $this->assertSame(2, WebhookEvent::query()->count());
        Event::assertDispatchedTimes(OrderPaid::class, 1);
    }

    // --- Unknown or mismatching events ---------------------------------------

    public function test_an_unknown_payment_reference_is_stored_and_logged(): void
    {
        Log::spy();
        $order = $this->order();

        $this->send($this->signed('payment.succeeded', 'evt_x', reference: 'fake_pay_unknown'))
            ->assertOk()
            ->assertExactJson(['outcome' => 'unknown_reference']);

        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()?->status);
        $this->assertSame(WebhookOutcome::UnknownReference, WebhookEvent::query()->sole()->outcome);
        Log::shouldHaveReceived('warning')->once()->withArgs(
            fn (string $message, array $context): bool => $message === 'Payment webhook for an unknown payment reference.'
                && $context['payment_reference'] === 'fake_pay_unknown',
        );
    }

    /**
     * @return array<string, array{0: int, 1: string}>
     */
    public static function mismatches(): array
    {
        return [
            'different amount' => [6600, 'EUR'],
            'different currency' => [6670, 'USD'],
        ];
    }

    #[DataProvider('mismatches')]
    public function test_an_amount_or_currency_mismatch_is_stored_and_logged_without_changing_the_status(int $amountCents, string $currency): void
    {
        Log::spy();
        Event::fake([OrderPaid::class]);
        $order = $this->order();

        $this->send($this->signed('payment.succeeded', amountCents: $amountCents, currency: $currency))
            ->assertOk()
            ->assertExactJson(['outcome' => 'amount_mismatch']);

        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()?->status);
        $this->assertSame(WebhookOutcome::AmountMismatch, WebhookEvent::query()->sole()->outcome);
        Event::assertNotDispatched(OrderPaid::class);
        Log::shouldHaveReceived('error')->once()->withArgs(
            fn (string $message): bool => $message === 'Payment webhook amount does not match the order.',
        );
    }

    public function test_an_unknown_event_type_is_stored_and_ignored(): void
    {
        $order = $this->order();

        $this->send($this->signed('payment.refunded'))
            ->assertOk()
            ->assertExactJson(['outcome' => 'unknown_type']);

        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()?->status);
        $this->assertSame(WebhookOutcome::UnknownType, WebhookEvent::query()->sole()->outcome);
    }

    // --- OrderPaid and ExtractOrderNote --------------------------------------

    public function test_order_paid_is_dispatched_exactly_once_for_the_paid_order(): void
    {
        Event::fake([OrderPaid::class]);
        $order = $this->order();
        $webhook = $this->signed('payment.succeeded', 'evt_1');

        $this->send($webhook)->assertOk();
        $this->send($webhook)->assertOk();

        Event::assertDispatchedTimes(OrderPaid::class, 1);
        Event::assertDispatched(OrderPaid::class, fn (OrderPaid $event): bool => $event->order->is($order));
    }

    public function test_extract_order_note_is_queued_exactly_once_when_the_order_is_paid(): void
    {
        Queue::fake();
        $order = $this->order();
        $webhook = $this->signed('payment.succeeded', 'evt_1');

        $this->send($webhook)->assertOk();
        $this->send($webhook)->assertOk();
        $this->send($this->signed('payment.succeeded', 'evt_2'))->assertOk();

        Queue::assertPushed(ExtractOrderNote::class, 1);
        Queue::assertPushed(ExtractOrderNote::class, fn (ExtractOrderNote $job): bool => $job->order->is($order));
    }

    public function test_extract_order_note_is_not_queued_for_a_failed_payment(): void
    {
        Queue::fake();
        $this->order();

        $this->send($this->signed('payment.failed'))->assertOk();

        Queue::assertNotPushed(ExtractOrderNote::class);
    }

    public function test_order_paid_listeners_run_after_the_transaction_commits(): void
    {
        Queue::fake();
        $this->order();
        $outsideLevel = DB::transactionLevel(); // RefreshDatabase wraps each test in a transaction.

        /** @var list<array{level: int, status: OrderStatus|null}> $seen */
        $seen = [];
        Event::listen(OrderPaid::class, function (OrderPaid $event) use (&$seen): void {
            $seen[] = [
                'level' => DB::transactionLevel(),
                'status' => $event->order->fresh()?->status,
            ];
        });

        $this->send($this->signed('payment.succeeded'))->assertOk();

        $this->assertSame([['level' => $outsideLevel, 'status' => OrderStatus::Paid]], $seen);
    }
}
