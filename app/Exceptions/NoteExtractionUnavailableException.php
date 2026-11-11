<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * A temporary problem (HTTP 429, 5xx, connection error or timeout). The job retries.
 */
class NoteExtractionUnavailableException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $rawResponse = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
