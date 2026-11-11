<?php

declare(strict_types=1);

namespace Tests\Feature\Checkout;

use App\Contracts\PaymentGateway;
use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderStatusTransitionException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\Payment\FakePaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CheckoutCartTest extends TestCase
{
    use RefreshDatabase;

    private FakePaymentGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new FakePaymentGateway;
        $this->app->instance(PaymentGateway::class, $this->gateway);
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $headers
     * @return TestResponse<JsonResponse>
     */
    private function checkout(Cart $cart, array $body = [], array $headers = []): TestResponse
    {
        return $this->postJson(
            "/api/carts/{$cart->token}/checkout",
            $body + ['email' => 'buyer@example.com', 'note' => 'Deliver on Friday, PO 4711'],
            $headers,
        );
    }

    public function test_it_turns_the_cart_into_an_order_and_starts_a_payment(): void
    {
        $cart = Cart::factory()->create();
        $bolts = Product::factory()->create(['name' => 'Bolts', 'sku' => 'BOLT-1', 'price_cents' => 1890, 'stock' => 10]);
        $nuts = Product::factory()->create(['name' => 'Nuts', 'sku' => 'NUT-1', 'price_cents' => 250, 'stock' => 4]);
        CartItem::factory()->for($cart)->for($bolts)->create(['quantity' => 3]);
        CartItem::factory()->for($cart)->for($nuts)->create(['quantity' => 4]);

        $response = $this->checkout($cart)
            ->assertCreated()
            ->assertHeaderMissing('Idempotent-Replayed')
            ->assertJsonPath('data.status', 'pending_payment')
            ->assertJsonPath('data.customer_email', 'buyer@example.com')
            ->assertJsonPath('data.note', 'Deliver on Friday, PO 4711')
            ->assertJsonPath('data.currency', 'EUR')
            ->assertJsonPath('data.total_cents', 6670)
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.sku', 'BOLT-1')
            ->assertJsonPath('data.items.0.unit_price_cents', 1890)
            ->assertJsonPath('data.items.0.line_total_cents', 5670);

        $order = Order::query()->sole();
        $this->assertSame($order->uuid, $response->json('data.uuid'));
        $this->assertSame($cart->id, $order->cart_id);
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertSame(6670, $order->total_cents);

        $this->assertCount(1, $this->gateway->createdPayments());
        $intent = $this->gateway->createdPayments()[0];
        $this->assertSame($intent->reference, $order->payment_reference);
        $response->assertJsonPath('data.payment.reference', $intent->reference);
        $response->assertJsonPath('data.payment.redirect_url', $intent->redirectUrl);

        $this->assertSame(7, $bolts->fresh()?->stock);
        $this->assertSame(0, $nuts->fresh()?->stock);
        $this->assertSame(2, OrderItem::query()->count());
    }

    public function test_the_note_is_optional(): void
    {
        $cart = Cart::factory()->create();
        CartItem::factory()->for($cart)->create(['quantity' => 1]);

        $this->postJson("/api/carts/{$cart->token}/checkout", ['email' => 'buyer@example.com'])
            ->assertCreated()
            ->assertJsonPath('data.note', null);
    }

    public function test_an_empty_cart_cannot_be_checked_out(): void
    {
        $cart = Cart::factory()->create();

        $this->checkout($cart)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cart']);

        $this->assertSame(0, Order::query()->count());
        $this->assertSame([], $this->gateway->createdPayments());
    }

    public function test_insufficient_stock_returns_409_and_writes_nothing(): void
    {
        $cart = Cart::factory()->create();
        $enough = Product::factory()->create(['sku' => 'OK-1', 'stock' => 10]);
        $short = Product::factory()->create(['sku' => 'SHORT-1', 'stock' => 2]);
        CartItem::factory()->for($cart)->for($enough)->create(['quantity' => 5]);
        CartItem::factory()->for($cart)->for($short)->create(['quantity' => 2]);

        // Stock drops after the item was added to the cart (another customer bought some).
        $short->update(['stock' => 1]);

        $this->checkout($cart)
            ->assertConflict()
            ->assertExactJson([
                'message' => 'Not enough stock for one or more items in the cart.',
                'short_skus' => ['SHORT-1'],
            ]);

        $this->assertSame(10, $enough->fresh()?->stock);
        $this->assertSame(1, $short->fresh()?->stock);
        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, OrderItem::query()->count());
        $this->assertSame([], $this->gateway->createdPayments());
    }

    public function test_order_items_keep_the_price_and_name_from_checkout_time(): void
    {
        $cart = Cart::factory()->create();
        $product = Product::factory()->create(['name' => 'Old name', 'sku' => 'SKU-OLD', 'price_cents' => 1000, 'stock' => 10]);
        CartItem::factory()->for($cart)->for($product)->create(['quantity' => 2]);

        $this->checkout($cart)->assertCreated();

        $product->update(['name' => 'New name', 'sku' => 'SKU-NEW', 'price_cents' => 9999]);

        $order = Order::query()->sole();
        $item = $order->items()->sole();
        $this->assertSame('Old name', $item->product_name);
        $this->assertSame('SKU-OLD', $item->sku);
        $this->assertSame(1000, $item->unit_price_cents);
        $this->assertSame(2000, $item->line_total_cents);
        $this->assertSame(2000, $order->total_cents);
    }

    public function test_a_cart_can_only_be_checked_out_once(): void
    {
        $cart = Cart::factory()->create();
        $product = Product::factory()->create(['stock' => 10]);
        CartItem::factory()->for($cart)->for($product)->create(['quantity' => 3]);

        $this->checkout($cart)->assertCreated();
        $this->checkout($cart)->assertConflict();

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(7, $product->fresh()?->stock);
        $this->assertCount(1, $this->gateway->createdPayments());
    }

    public function test_the_email_is_trimmed_and_lowercased_before_storing(): void
    {
        $cart = Cart::factory()->create();
        CartItem::factory()->for($cart)->create(['quantity' => 1]);

        $this->checkout($cart, ['email' => '  Buyer@Example.COM  '])
            ->assertCreated()
            ->assertJsonPath('data.customer_email', 'buyer@example.com');

        $this->assertSame('buyer@example.com', Order::query()->sole()->customer_email);
    }

    public function test_an_idempotent_replay_matches_the_email_case_insensitively(): void
    {
        $cart = Cart::factory()->create();
        CartItem::factory()->for($cart)->create(['quantity' => 1]);
        $headers = ['Idempotency-Key' => 'checkout-abc-123'];

        $first = $this->checkout($cart, ['email' => 'buyer@example.com'], $headers)->assertCreated();
        $second = $this->checkout($cart, ['email' => ' BUYER@Example.com '], $headers)
            ->assertOk()
            ->assertHeader('Idempotent-Replayed', 'true');

        $this->assertSame($first->json('data.uuid'), $second->json('data.uuid'));
        $this->assertSame(1, Order::query()->count());
    }

    public function test_the_same_idempotency_key_returns_the_same_order(): void
    {
        $cart = Cart::factory()->create();
        $product = Product::factory()->create(['stock' => 10]);
        CartItem::factory()->for($cart)->for($product)->create(['quantity' => 3]);
        $headers = ['Idempotency-Key' => 'checkout-abc-123'];

        $first = $this->checkout($cart, [], $headers)->assertCreated();
        $second = $this->checkout($cart, [], $headers)
            ->assertOk()
            ->assertHeader('Idempotent-Replayed', 'true');

        $this->assertSame($first->json('data'), $second->json('data'));
        $this->assertSame(1, Order::query()->count());
        $this->assertSame('checkout-abc-123', Order::query()->sole()->idempotency_key);
        $this->assertSame(7, $product->fresh()?->stock);
        $this->assertCount(1, $this->gateway->createdPayments());
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function changedRequestBodies(): array
    {
        return [
            'different email' => [['email' => 'someone-else@example.com']],
            'different note' => [['note' => 'Deliver on Monday instead']],
            'note removed' => [['note' => null]],
        ];
    }

    /**
     * @param  array<string, mixed>  $changed
     */
    #[DataProvider('changedRequestBodies')]
    public function test_the_same_idempotency_key_with_a_different_body_returns_409(array $changed): void
    {
        $cart = Cart::factory()->create();
        $product = Product::factory()->create(['stock' => 10]);
        CartItem::factory()->for($cart)->for($product)->create(['quantity' => 3]);
        $headers = ['Idempotency-Key' => 'checkout-abc-123'];

        $this->checkout($cart, [], $headers)->assertCreated();
        $this->checkout($cart, $changed, $headers)
            ->assertConflict()
            ->assertHeaderMissing('Idempotent-Replayed')
            ->assertJsonMissingPath('data');

        $order = Order::query()->sole();
        $this->assertSame('buyer@example.com', $order->customer_email);
        $this->assertSame('Deliver on Friday, PO 4711', $order->note);
        $this->assertSame(7, $product->fresh()?->stock);
    }

    public function test_the_same_idempotency_key_on_a_different_cart_returns_409(): void
    {
        $firstCart = Cart::factory()->create();
        $secondCart = Cart::factory()->create();
        $product = Product::factory()->create(['stock' => 10]);
        CartItem::factory()->for($firstCart)->for($product)->create(['quantity' => 3]);
        CartItem::factory()->for($secondCart)->for($product)->create(['quantity' => 2]);
        $headers = ['Idempotency-Key' => 'checkout-abc-123'];

        $this->checkout($firstCart, [], $headers)->assertCreated();
        $this->checkout($secondCart, [], $headers)->assertConflict();

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(7, $product->fresh()?->stock);
    }

    public function test_different_idempotency_keys_on_the_same_cart_still_return_409(): void
    {
        $cart = Cart::factory()->create();
        CartItem::factory()->for($cart)->create(['quantity' => 1]);

        $this->checkout($cart, [], ['Idempotency-Key' => 'key-1'])->assertCreated();
        $this->checkout($cart, [], ['Idempotency-Key' => 'key-2'])->assertConflict();

        $this->assertSame(1, Order::query()->count());
    }

    public function test_a_gateway_failure_marks_the_order_payment_failed_and_returns_502(): void
    {
        $cart = Cart::factory()->create();
        $product = Product::factory()->create(['stock' => 10]);
        CartItem::factory()->for($cart)->for($product)->create(['quantity' => 3]);
        $this->gateway->failNextCall();

        $response = $this->checkout($cart)
            ->assertStatus(502)
            ->assertJsonPath('order.status', 'payment_failed');

        $order = Order::query()->sole();
        $this->assertSame($order->uuid, $response->json('order.uuid'));
        $this->assertSame(OrderStatus::PaymentFailed, $order->status);
        $this->assertNull($order->payment_reference);
        // Stock release is out of scope for this step (see README known limits).
        $this->assertSame(7, $product->fresh()?->stock);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: array<string, string>, 2: string}>
     */
    public static function invalidInput(): array
    {
        return [
            'missing email' => [['email' => null], [], 'email'],
            'invalid email' => [['email' => 'not-an-email'], [], 'email'],
            'email too long' => [['email' => str_repeat('a', 250).'@example.com'], [], 'email'],
            'note not a string' => [['note' => ['array']], [], 'note'],
            'note too long' => [['note' => str_repeat('x', 2001)], [], 'note'],
            'idempotency key too long' => [[], ['Idempotency-Key' => str_repeat('k', 256)], 'idempotency_key'],
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $headers
     */
    #[DataProvider('invalidInput')]
    public function test_it_validates_the_input(array $body, array $headers, string $field): void
    {
        $cart = Cart::factory()->create();
        $product = Product::factory()->create(['stock' => 10]);
        CartItem::factory()->for($cart)->for($product)->create(['quantity' => 1]);

        $this->checkout($cart, $body, $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(10, $product->fresh()?->stock);
    }

    public function test_it_returns_404_for_an_unknown_cart(): void
    {
        $this->postJson('/api/carts/00000000-0000-0000-0000-000000000000/checkout', ['email' => 'buyer@example.com'])
            ->assertNotFound();
    }

    public function test_an_order_rejects_an_invalid_status_transition(): void
    {
        $order = Order::factory()->withStatus(OrderStatus::Paid)->create();

        try {
            $order->transitionTo(OrderStatus::PendingPayment);
            $this->fail('Expected InvalidOrderStatusTransitionException.');
        } catch (InvalidOrderStatusTransitionException) {
            $this->assertSame(OrderStatus::Paid, $order->fresh()?->status);
        }
    }
}
