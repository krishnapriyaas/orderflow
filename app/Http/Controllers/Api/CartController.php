<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Cart\CreateCart;
use App\Http\Controllers\Controller;
use App\Http\Resources\CartResource;
use App\Models\Cart;
use Illuminate\Http\JsonResponse;

class CartController extends Controller
{
    public function store(CreateCart $createCart): JsonResponse
    {
        $cart = $createCart->execute();

        return CartResource::make($cart->load('items.product'))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Cart $cart): CartResource
    {
        return CartResource::make($cart->load('items.product'));
    }
}
