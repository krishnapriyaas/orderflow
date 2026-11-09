<?php

declare(strict_types=1);

namespace App\Actions\Cart;

use App\Models\Cart;

class CreateCart
{
    public function execute(): Cart
    {
        return Cart::query()->create();
    }
}
