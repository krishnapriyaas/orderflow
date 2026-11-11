<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Checkout\CheckoutCart;
use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Models\Cart;
use Illuminate\Http\JsonResponse;

class CheckoutController extends Controller
{
    public function __invoke(CheckoutRequest $request, Cart $cart, CheckoutCart $checkoutCart): JsonResponse
    {
        $result = $checkoutCart->execute($cart, $request->toData());

        $response = OrderResource::make($result->order->load('items'))->response();

        if ($result->replayed) {
            return $response->setStatusCode(200)->header('Idempotent-Replayed', 'true');
        }

        return $response->setStatusCode(201);
    }
}
