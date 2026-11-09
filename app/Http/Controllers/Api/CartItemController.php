<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Cart\AddItemToCart;
use App\Actions\Cart\RemoveCartItem;
use App\Actions\Cart\UpdateCartItemQuantity;
use App\Http\Controllers\Controller;
use App\Http\Requests\AddCartItemRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Http\Resources\CartResource;
use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Http\JsonResponse;

class CartItemController extends Controller
{
    public function store(AddCartItemRequest $request, Cart $cart, AddItemToCart $addItemToCart): JsonResponse
    {
        $addItemToCart->execute($cart, $request->productId(), $request->quantity());

        return CartResource::make($cart->load('items.product'))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        UpdateCartItemRequest $request,
        Cart $cart,
        CartItem $item,
        UpdateCartItemQuantity $updateCartItemQuantity,
    ): CartResource {
        $updateCartItemQuantity->execute($item, $request->quantity());

        return CartResource::make($cart->load('items.product'));
    }

    public function destroy(Cart $cart, CartItem $item, RemoveCartItem $removeCartItem): CartResource
    {
        $removeCartItem->execute($item);

        return CartResource::make($cart->load('items.product'));
    }
}
