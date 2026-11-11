<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\NoteExtractionStatus;
use PHPUnit\Framework\TestCase;

class NoteExtractionStatusTest extends TestCase
{
    public function test_an_extraction_in_review_can_be_approved_or_rejected(): void
    {
        $this->assertSame(
            [NoteExtractionStatus::Approved, NoteExtractionStatus::Rejected],
            NoteExtractionStatus::NeedsReview->allowedTransitions(),
        );
        $this->assertTrue(NoteExtractionStatus::NeedsReview->canTransitionTo(NoteExtractionStatus::Approved));
        $this->assertFalse(NoteExtractionStatus::NeedsReview->canTransitionTo(NoteExtractionStatus::AutoApplied));
    }

    public function test_the_other_statuses_are_final(): void
    {
        foreach ([NoteExtractionStatus::AutoApplied, NoteExtractionStatus::Approved, NoteExtractionStatus::Rejected] as $status) {
            $this->assertSame([], $status->allowedTransitions(), $status->value);
        }
    }
}
