<?php

declare(strict_types=1);

namespace App\Actions\Cart;

use App\Exceptions\CartAlreadyCheckedOutException;
use App\Exceptions\InsufficientStockException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class UpdateCartItemQuantity
{
    /**
     * @throws InsufficientStockException
     * @throws CartAlreadyCheckedOutException
     */
    public function execute(CartItem $item, int $quantity): CartItem
    {
        return DB::transaction(function () use ($item, $quantity): CartItem {
            Cart::lockOpenCart($item->cart_id);

            $product = Product::query()->lockForUpdate()->findOrFail($item->product_id);

            if ($quantity > $product->stock) {
                throw new InsufficientStockException($product, $quantity);
            }

            $item->quantity = $quantity;
            $item->save();

            return $item;
        });
    }
}
