<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class IdempotencyKeyConflictException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This Idempotency-Key was already used for a different checkout request.');
    }

    public function render(): JsonResponse
    {
        return new JsonResponse(['message' => $this->getMessage()], 409);
    }
}
