<?php

declare(strict_types=1);

namespace Tests\Feature\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowCartTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_items_and_total(): void
    {
        $cart = Cart::factory()->create();
        $paper = Product::factory()->create(['sku' => 'PAPER', 'price_cents' => 2495]);
        $tape = Product::factory()->create(['sku' => 'TAPE', 'price_cents' => 345]);
        CartItem::factory()->for($cart)->for($paper)->create(['quantity' => 2]);
        CartItem::factory()->for($cart)->for($tape)->create(['quantity' => 3]);

        $this->getJson("/api/carts/{$cart->token}")
            ->assertOk()
            ->assertJsonPath('data.token', $cart->token)
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.sku', 'PAPER')
            ->assertJsonPath('data.items.0.unit_price_cents', 2495)
            ->assertJsonPath('data.items.0.quantity', 2)
            ->assertJsonPath('data.items.0.line_total_cents', 4990)
            ->assertJsonPath('data.items.1.line_total_cents', 1035)
            ->assertJsonPath('data.total_cents', 6025);
    }

    public function test_it_returns_404_for_an_unknown_token(): void
    {
        $this->getJson('/api/carts/00000000-0000-0000-0000-000000000000')
            ->assertNotFound();
    }

    public function test_it_cannot_be_looked_up_by_internal_id(): void
    {
        $cart = Cart::factory()->create();

        $this->getJson("/api/carts/{$cart->id}")
            ->assertNotFound();
    }
}
