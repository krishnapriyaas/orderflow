<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class StockConflictException extends RuntimeException
{
    /**
     * @param  list<array{sku: string, requested: int, available: int}>  $shortages
     */
    public function __construct(public readonly array $shortages)
    {
        parent::__construct('Not enough stock for one or more items in the cart.');
    }

    public function render(): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'shortages' => $this->shortages,
        ], 409);
    }
}
