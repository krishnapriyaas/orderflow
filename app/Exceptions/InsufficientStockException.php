<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public function __construct(
        public readonly Product $product,
        public readonly int $requestedQuantity,
    ) {
        parent::__construct(sprintf(
            'Not enough stock for %s: requested %d, available %d.',
            $product->sku,
            $requestedQuantity,
            $product->stock,
        ));
    }

    public function render(): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'errors' => [
                'quantity' => [$this->getMessage()],
            ],
        ], 422);
    }
}
