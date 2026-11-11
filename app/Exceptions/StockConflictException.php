<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class StockConflictException extends RuntimeException
{
    /**
     * Only the SKUs are exposed; stock levels are not shown to customers.
     *
     * @param  list<string>  $shortSkus
     */
    public function __construct(public readonly array $shortSkus)
    {
        parent::__construct('Not enough stock for one or more items in the cart.');
    }

    public function render(): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'short_skus' => $this->shortSkus,
        ], 409);
    }
}
