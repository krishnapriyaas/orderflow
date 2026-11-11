<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\NoteReviewReason;
use RuntimeException;
use Throwable;

/**
 * A permanent problem: retrying the same note would fail the same way, so the
 * extraction goes straight to review.
 */
class InvalidNoteExtractionException extends RuntimeException
{
    public function __construct(
        public readonly NoteReviewReason $reason,
        string $message,
        public readonly ?string $rawResponse = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
