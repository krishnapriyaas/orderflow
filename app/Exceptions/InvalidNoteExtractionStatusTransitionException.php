<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\NoteExtractionStatus;
use DomainException;

class InvalidNoteExtractionStatusTransitionException extends DomainException
{
    public function __construct(
        public readonly NoteExtractionStatus $from,
        public readonly NoteExtractionStatus $to,
    ) {
        parent::__construct(sprintf(
            'Note extraction status cannot change from %s to %s.',
            $from->value,
            $to->value,
        ));
    }
}
