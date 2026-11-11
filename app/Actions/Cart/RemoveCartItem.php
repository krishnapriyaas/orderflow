<?php

declare(strict_types=1);

namespace App\Actions\Cart;

use App\Exceptions\CartAlreadyCheckedOutException;
use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Support\Facades\DB;

class RemoveCartItem
{
    /**
     * @throws CartAlreadyCheckedOutException
     */
    public function execute(CartItem $item): void
    {
        DB::transaction(function () use ($item): void {
            Cart::lockOpenCart($item->cart_id);

            $item->delete();
        });
    }
}
