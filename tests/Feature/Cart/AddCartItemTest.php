<?php

declare(strict_types=1);

namespace Tests\Feature\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AddCartItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_adds_a_product_to_the_cart(): void
    {
        $cart = Cart::factory()->create();
        $product = Product::factory()->create(['price_cents' => 1890, 'stock' => 10]);

        $this->postJson("/api/carts/{$cart->token}/items", [
            'product_id' => $product->id,
            'quantity' => 3,
        ])
            ->assertCreated()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.product_id', $product->id)
            ->assertJsonPath('data.items.0.quantity', 3)
            ->assertJsonPath('data.items.0.line_total_cents', 5670)
            ->assertJsonPath('data.total_cents', 5670);

        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 3,
        ]);
    }

    public function test_adding_the_same_product_twice_merges_the_line(): void
    {
        $cart = Cart::factory()->create();
        $product = Product::factory()->create(['price_cents' => 100, 'stock' => 10]);

        $this->postJson("/api/carts/{$cart->token}/items", ['product_id' => $product->id, 'quantity' => 2])
            ->assertCreated();
        $this->postJson("/api/carts/{$cart->token}/items", ['product_id' => $product->id, 'quantity' => 3])
            ->assertCreated()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.quantity', 5)
            ->assertJsonPath('data.total_cents', 500);

        $this->assertSame(1, CartItem::query()->count());
    }

    public function test_it_rejects_an_unknown_product(): void
    {
        $cart = Cart::factory()->create();

        $this->postJson("/api/carts/{$cart->token}/items", ['product_id' => 9999, 'quantity' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product_id']);

        $this->assertSame(0, CartItem::query()->count());
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function invalidQuantities(): array
    {
        return [
            'zero' => [0],
            'negative' => [-1],
            'decimal' => [1.5],
            'string' => ['abc'],
            'null' => [null],
        ];
    }

    #[DataProvider('invalidQuantities')]
    public function test_it_rejects_an_invalid_quantity(mixed $quantity): void
    {
        $cart = Cart::factory()->create();
        $product = Product::factory()->create(['stock' => 10]);

        $this->postJson("/api/carts/{$cart->token}/items", ['product_id' => $product->id, 'quantity' => $quantity])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);

        $this->assertSame(0, CartItem::query()->count());
    }

    public function test_it_requires_product_id_and_quantity(): void
    {
        $cart = Cart::factory()->create();

        $this->postJson("/api/carts/{$cart->token}/items", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product_id', 'quantity']);
    }

    public function test_it_rejects_a_quantity_above_stock(): void
    {
        $cart = Cart::factory()->create();
        $product = Product::factory()->create(['stock' => 5]);

        $this->postJson("/api/carts/{$cart->token}/items", ['product_id' => $product->id, 'quantity' => 6])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);

        $this->assertSame(0, CartItem::query()->count());
    }

    public function test_it_accepts_a_quantity_equal_to_stock(): void
    {
        $cart = Cart::factory()->create();
        $product = Product::factory()->create(['stock' => 5]);

        $this->postJson("/api/carts/{$cart->token}/items", ['product_id' => $product->id, 'quantity' => 5])
            ->assertCreated()
            ->assertJsonPath('data.items.0.quantity', 5);
    }

    public function test_it_rejects_a_merged_quantity_above_stock(): void
    {
        $cart = Cart::factory()->create();
        $product = Product::factory()->create(['stock' => 5]);
        CartItem::factory()->for($cart)->for($product)->create(['quantity' => 4]);

        $this->postJson("/api/carts/{$cart->token}/items", ['product_id' => $product->id, 'quantity' => 2])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);

        $this->assertDatabaseHas('cart_items', ['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 4]);
    }

    public function test_adding_to_a_cart_does_not_change_stock(): void
    {
        $cart = Cart::factory()->create();
        $product = Product::factory()->create(['stock' => 5]);

        $this->postJson("/api/carts/{$cart->token}/items", ['product_id' => $product->id, 'quantity' => 2])
            ->assertCreated();

        $this->assertSame(5, $product->fresh()?->stock);
    }

    public function test_it_returns_404_for_an_unknown_cart(): void
    {
        $product = Product::factory()->create();

        $this->postJson('/api/carts/00000000-0000-0000-0000-000000000000/items', ['product_id' => $product->id, 'quantity' => 1])
            ->assertNotFound();
    }
}
