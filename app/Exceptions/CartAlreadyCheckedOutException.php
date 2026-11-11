<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class CartAlreadyCheckedOutException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This cart has already been checked out.');
    }

    public function render(): JsonResponse
    {
        return new JsonResponse(['message' => $this->getMessage()], 409);
    }
}
