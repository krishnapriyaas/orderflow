<?php

declare(strict_types=1);

namespace App\Actions\OrderNotes;

use App\Enums\NoteExtractionStatus;
use App\Exceptions\InvalidNoteExtractionStatusTransitionException;
use App\Models\OrderNoteExtraction;
use App\Models\User;

class RejectNoteExtraction
{
    public function __construct(private readonly DecideNoteExtraction $decide) {}

    /**
     * Marks the extraction rejected. The extracted fields stay on the row as they were.
     *
     * @throws InvalidNoteExtractionStatusTransitionException when it is no longer in review
     */
    public function execute(OrderNoteExtraction $extraction, User $reviewer): OrderNoteExtraction
    {
        return $this->decide->execute($extraction, NoteExtractionStatus::Rejected, $reviewer);
    }
}
