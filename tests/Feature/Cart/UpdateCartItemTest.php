<?php

declare(strict_types=1);

namespace Tests\Feature\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateCartItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_updates_the_quantity(): void
    {
        $cart = Cart::factory()->create();
        $product = Product::factory()->create(['price_cents' => 250, 'stock' => 10]);
        $item = CartItem::factory()->for($cart)->for($product)->create(['quantity' => 1]);

        $this->patchJson("/api/carts/{$cart->token}/items/{$item->id}", ['quantity' => 4])
            ->assertOk()
            ->assertJsonPath('data.items.0.quantity', 4)
            ->assertJsonPath('data.total_cents', 1000);

        $this->assertSame(4, $item->fresh()?->quantity);
    }

    public function test_it_rejects_a_quantity_below_one(): void
    {
        $cart = Cart::factory()->create();
        $item = CartItem::factory()->for($cart)->create(['quantity' => 2]);

        $this->patchJson("/api/carts/{$cart->token}/items/{$item->id}", ['quantity' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);

        $this->assertSame(2, $item->fresh()?->quantity);
    }

    public function test_it_requires_a_quantity(): void
    {
        $cart = Cart::factory()->create();
        $item = CartItem::factory()->for($cart)->create();

        $this->patchJson("/api/carts/{$cart->token}/items/{$item->id}", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);
    }

    public function test_it_rejects_a_quantity_above_stock(): void
    {
        $cart = Cart::factory()->create();
        $product = Product::factory()->create(['stock' => 3]);
        $item = CartItem::factory()->for($cart)->for($product)->create(['quantity' => 2]);

        $this->patchJson("/api/carts/{$cart->token}/items/{$item->id}", ['quantity' => 4])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);

        $this->assertSame(2, $item->fresh()?->quantity);
    }

    public function test_it_returns_404_for_an_item_from_another_cart(): void
    {
        $cart = Cart::factory()->create();
        $otherItem = CartItem::factory()->create(['quantity' => 1]);

        $this->patchJson("/api/carts/{$cart->token}/items/{$otherItem->id}", ['quantity' => 2])
            ->assertNotFound();

        $this->assertSame(1, $otherItem->fresh()?->quantity);
    }

    public function test_it_returns_404_for_an_unknown_item(): void
    {
        $cart = Cart::factory()->create();

        $this->patchJson("/api/carts/{$cart->token}/items/9999", ['quantity' => 2])
            ->assertNotFound();
    }
}
