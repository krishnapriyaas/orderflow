<?php

declare(strict_types=1);

namespace App\Actions\Cart;

use App\Exceptions\InsufficientStockException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class AddItemToCart
{
    /**
     * Adds a product to the cart. If the product is already in the cart,
     * the quantity is added to the existing line.
     *
     * @throws InsufficientStockException
     */
    public function execute(Cart $cart, int $productId, int $quantity): CartItem
    {
        return DB::transaction(function () use ($cart, $productId, $quantity): CartItem {
            $product = Product::query()->lockForUpdate()->findOrFail($productId);

            $item = $cart->items()->firstOrNew(['product_id' => $product->id], ['quantity' => 0]);
            $newQuantity = $item->quantity + $quantity;

            if ($newQuantity > $product->stock) {
                throw new InsufficientStockException($product, $newQuantity);
            }

            $item->quantity = $newQuantity;
            $item->save();

            return $item;
        });
    }
}
