<?php

declare(strict_types=1);

namespace App\Actions\Checkout;

use App\Contracts\PaymentGateway;
use App\Data\CheckoutData;
use App\Data\CheckoutResult;
use App\Enums\OrderStatus;
use App\Exceptions\CartAlreadyCheckedOutException;
use App\Exceptions\EmptyCartException;
use App\Exceptions\IdempotencyKeyConflictException;
use App\Exceptions\PaymentGatewayException;
use App\Exceptions\PaymentStartFailedException;
use App\Exceptions\StockConflictException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class CheckoutCart
{
    public function __construct(private readonly PaymentGateway $paymentGateway) {}

    /**
     * Turns the cart into an order and starts the payment.
     *
     * @throws EmptyCartException
     * @throws StockConflictException
     * @throws CartAlreadyCheckedOutException
     * @throws IdempotencyKeyConflictException
     * @throws PaymentStartFailedException
     */
    public function execute(Cart $cart, CheckoutData $data): CheckoutResult
    {
        try {
            $result = DB::transaction(fn (): CheckoutResult => $this->createOrder($cart, $data));
        } catch (UniqueConstraintViolationException $e) {
            // A concurrent request won the race (same cart or same key).
            $result = $this->resolveDuplicate($cart, $data) ?? throw $e;
        }

        if ($result->replayed) {
            return $result;
        }

        return new CheckoutResult($this->startPayment($result->order), replayed: false);
    }

    private function createOrder(Cart $cart, CheckoutData $data): CheckoutResult
    {
        // Lock the cart row so checkouts of the same cart run one after the other.
        $cart = Cart::query()->lockForUpdate()->findOrFail($cart->id);

        if ($data->idempotencyKey !== null) {
            $existing = Order::query()->where('idempotency_key', $data->idempotencyKey)->first();

            if ($existing !== null) {
                return $this->replay($existing, $cart, $data);
            }
        }

        if ($cart->order()->exists()) {
            throw new CartAlreadyCheckedOutException;
        }

        $items = $cart->items()->get();

        if ($items->isEmpty()) {
            throw new EmptyCartException;
        }

        // Lock in a fixed order (by id) so two checkouts cannot deadlock each other.
        $products = Product::query()
            ->whereIn('id', $items->pluck('product_id'))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        /** @var list<array{item: CartItem, product: Product}> $lines */
        $lines = [];
        $shortages = [];
        $totalCents = 0;

        foreach ($items as $item) {
            // cart_items.product_id is a restricting foreign key, so the product exists.
            /** @var Product $product */
            $product = $products->get($item->product_id);

            if ($item->quantity > $product->stock) {
                $shortages[] = [
                    'sku' => $product->sku,
                    'requested' => $item->quantity,
                    'available' => $product->stock,
                ];
            }

            $lines[] = ['item' => $item, 'product' => $product];
            $totalCents += $product->price_cents * $item->quantity;
        }

        if ($shortages !== []) {
            throw new StockConflictException($shortages);
        }

        /** @var Order $order */
        $order = $cart->order()->create([
            'customer_email' => $data->email,
            'note' => $data->note,
            'status' => OrderStatus::PendingPayment,
            'total_cents' => $totalCents,
            'currency' => 'EUR',
            'idempotency_key' => $data->idempotencyKey,
        ]);

        foreach ($lines as ['item' => $item, 'product' => $product]) {
            $product->decrement('stock', $item->quantity);

            $order->items()->create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'sku' => $product->sku,
                'unit_price_cents' => $product->price_cents,
                'quantity' => $item->quantity,
                'line_total_cents' => $product->price_cents * $item->quantity,
            ]);
        }

        return new CheckoutResult($order, replayed: false);
    }

    /**
     * Returns the earlier order for a repeated Idempotency-Key, but only if the
     * request is the same one (same cart, email and note).
     */
    private function replay(Order $existing, Cart $cart, CheckoutData $data): CheckoutResult
    {
        $sameRequest = $existing->cart_id === $cart->id
            && $existing->customer_email === $data->email
            && $existing->note === $data->note;

        if (! $sameRequest) {
            throw new IdempotencyKeyConflictException;
        }

        return new CheckoutResult($existing, replayed: true);
    }

    private function resolveDuplicate(Cart $cart, CheckoutData $data): ?CheckoutResult
    {
        if ($data->idempotencyKey !== null) {
            $existing = Order::query()->where('idempotency_key', $data->idempotencyKey)->first();

            if ($existing !== null) {
                return $this->replay($existing, $cart, $data);
            }
        }

        if (Order::query()->where('cart_id', $cart->id)->exists()) {
            throw new CartAlreadyCheckedOutException;
        }

        return null;
    }

    private function startPayment(Order $order): Order
    {
        try {
            $intent = $this->paymentGateway->createPayment($order);
        } catch (PaymentGatewayException $e) {
            $order->transitionTo(OrderStatus::PaymentFailed);

            throw new PaymentStartFailedException($order, $e);
        }

        $order->payment_reference = $intent->reference;
        $order->payment_redirect_url = $intent->redirectUrl;
        $order->save();

        return $order;
    }
}
