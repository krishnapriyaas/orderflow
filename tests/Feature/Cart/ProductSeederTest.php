<?php

declare(strict_types=1);

namespace Tests\Feature\Cart;

use App\Models\Product;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_ten_products_with_unique_skus_and_integer_prices(): void
    {
        $this->seed(ProductSeeder::class);

        $products = Product::all();

        $this->assertCount(10, $products);
        $this->assertCount(10, $products->pluck('sku')->unique());
        foreach ($products as $product) {
            $this->assertIsInt($product->price_cents);
            $this->assertGreaterThan(0, $product->price_cents);
            $this->assertGreaterThan(0, $product->stock);
        }
    }

    public function test_it_can_run_twice_without_duplicates(): void
    {
        $this->seed(ProductSeeder::class);
        $this->seed(ProductSeeder::class);

        $this->assertSame(10, Product::query()->count());
    }
}
