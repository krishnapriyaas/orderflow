<?php

declare(strict_types=1);

use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CartItemController;
use Illuminate\Support\Facades\Route;

Route::post('/carts', [CartController::class, 'store']);
Route::get('/carts/{cart}', [CartController::class, 'show']);

Route::scopeBindings()->group(function (): void {
    Route::post('/carts/{cart}/items', [CartItemController::class, 'store']);
    Route::patch('/carts/{cart}/items/{item}', [CartItemController::class, 'update']);
    Route::delete('/carts/{cart}/items/{item}', [CartItemController::class, 'destroy']);
});
