<?php

declare(strict_types=1);

namespace Tests\Feature\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckedOutCartTest extends TestCase
{
    use RefreshDatabase;

    private function checkedOutCart(): Cart
    {
        $cart = Cart::factory()->create();
        Order::factory()->for($cart)->create();

        return $cart;
    }

    public function test_items_cannot_be_added_to_a_checked_out_cart(): void
    {
        $cart = $this->checkedOutCart();
        $product = Product::factory()->create(['stock' => 10]);

        $this->postJson("/api/carts/{$cart->token}/items", ['product_id' => $product->id, 'quantity' => 1])
            ->assertConflict();

        $this->assertSame(0, CartItem::query()->count());
    }

    public function test_items_cannot_be_updated_in_a_checked_out_cart(): void
    {
        $cart = $this->checkedOutCart();
        $item = CartItem::factory()->for($cart)->create(['quantity' => 2]);

        $this->patchJson("/api/carts/{$cart->token}/items/{$item->id}", ['quantity' => 1])
            ->assertConflict();

        $this->assertSame(2, $item->fresh()?->quantity);
    }

    public function test_items_cannot_be_removed_from_a_checked_out_cart(): void
    {
        $cart = $this->checkedOutCart();
        $item = CartItem::factory()->for($cart)->create();

        $this->deleteJson("/api/carts/{$cart->token}/items/{$item->id}")
            ->assertConflict();

        $this->assertNotNull($item->fresh());
    }

    public function test_a_checked_out_cart_can_still_be_viewed(): void
    {
        $cart = $this->checkedOutCart();

        $this->getJson("/api/carts/{$cart->token}")->assertOk();
    }

    public function test_items_can_be_changed_after_a_real_checkout_attempt_failed_on_stock(): void
    {
        $cart = Cart::factory()->create();
        $product = Product::factory()->create(['stock' => 1]);
        $item = CartItem::factory()->for($cart)->for($product)->create(['quantity' => 1]);
        $product->update(['stock' => 0]);

        $this->postJson("/api/carts/{$cart->token}/checkout", ['email' => 'buyer@example.com'])
            ->assertConflict();

        $this->deleteJson("/api/carts/{$cart->token}/items/{$item->id}")
            ->assertOk();
    }
}
