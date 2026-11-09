<?php

declare(strict_types=1);

namespace Tests\Feature\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RemoveCartItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_removes_an_item_and_updates_the_total(): void
    {
        $cart = Cart::factory()->create();
        $keep = CartItem::factory()->for($cart)->for(Product::factory()->state(['price_cents' => 300]))->create(['quantity' => 2]);
        $remove = CartItem::factory()->for($cart)->for(Product::factory()->state(['price_cents' => 999]))->create(['quantity' => 1]);

        $this->deleteJson("/api/carts/{$cart->token}/items/{$remove->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $keep->id)
            ->assertJsonPath('data.total_cents', 600);

        $this->assertModelMissing($remove);
        $this->assertModelExists($keep);
    }

    public function test_it_returns_404_for_an_item_from_another_cart(): void
    {
        $cart = Cart::factory()->create();
        $otherItem = CartItem::factory()->create();

        $this->deleteJson("/api/carts/{$cart->token}/items/{$otherItem->id}")
            ->assertNotFound();

        $this->assertModelExists($otherItem);
    }
}
