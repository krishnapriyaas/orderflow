<?php

declare(strict_types=1);

namespace App\Enums;

use App\Exceptions\InvalidNoteExtractionStatusTransitionException;

enum NoteExtractionStatus: string
{
    case AutoApplied = 'auto_applied';
    case NeedsReview = 'needs_review';
    case Approved = 'approved';
    case Rejected = 'rejected';

    /**
     * A person decides on extractions in review (Step 5 back-office page).
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::NeedsReview => [self::Approved, self::Rejected],
            self::AutoApplied, self::Approved, self::Rejected => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    /**
     * Returns the target status, or throws if the move is not allowed.
     *
     * @throws InvalidNoteExtractionStatusTransitionException
     */
    public function transitionTo(self $to): self
    {
        if (! $this->canTransitionTo($to)) {
            throw new InvalidNoteExtractionStatusTransitionException($this, $to);
        }

        return $to;
    }
}
