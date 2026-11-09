<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            ['sku' => 'PAP-A4-80-5000', 'name' => 'Copy paper A4 80 g/m², box of 5 reams (2,500 sheets)', 'price_cents' => 2495, 'stock' => 400],
            ['sku' => 'BOX-SW-305', 'name' => 'Single-wall cardboard box 305×215×150 mm, pack of 25', 'price_cents' => 1890, 'stock' => 250],
            ['sku' => 'TAP-PP-48-66', 'name' => 'Packing tape brown 48 mm × 66 m, pack of 36 rolls', 'price_cents' => 3450, 'stock' => 180],
            ['sku' => 'BUB-500-100', 'name' => 'Bubble wrap roll 500 mm × 100 m', 'price_cents' => 2275, 'stock' => 120],
            ['sku' => 'STR-500-23', 'name' => 'Stretch film hand roll 500 mm, 23 µm, pack of 6', 'price_cents' => 4190, 'stock' => 90],
            ['sku' => 'TON-HP-26X', 'name' => 'Laser toner cartridge black, high yield (compatible 26X)', 'price_cents' => 7990, 'stock' => 60],
            ['sku' => 'STA-266-5000', 'name' => 'Staples 26/6, box of 5,000', 'price_cents' => 295, 'stock' => 800],
            ['sku' => 'BIN-A4-80', 'name' => 'Lever arch binder A4, 80 mm spine, pack of 10', 'price_cents' => 2690, 'stock' => 150],
            ['sku' => 'LAB-SHP-A6', 'name' => 'Self-adhesive shipping labels A6, roll of 500', 'price_cents' => 1550, 'stock' => 300],
            ['sku' => 'VOI-FIL-200', 'name' => 'Paper void fill, 200 m roll', 'price_cents' => 3895, 'stock' => 75],
        ];

        foreach ($products as $product) {
            Product::query()->updateOrCreate(['sku' => $product['sku']], $product);
        }
    }
}
