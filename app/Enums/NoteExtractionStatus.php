<?php

declare(strict_types=1);

namespace App\Enums;

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
}
