<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Cart;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cart_id' => Cart::factory(),
            'customer_email' => fake()->safeEmail(),
            'note' => null,
            'status' => OrderStatus::PendingPayment,
            'total_cents' => fake()->numberBetween(100, 50000),
            'currency' => 'EUR',
            'payment_reference' => null,
            'payment_redirect_url' => null,
            'idempotency_key' => null,
        ];
    }

    public function withStatus(OrderStatus $status): static
    {
        return $this->state(['status' => $status]);
    }
}
