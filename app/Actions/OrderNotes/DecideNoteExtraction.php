<?php

declare(strict_types=1);

namespace App\Actions\OrderNotes;

use App\Enums\NoteExtractionStatus;
use App\Exceptions\InvalidNoteExtractionStatusTransitionException;
use App\Models\OrderNoteExtraction;
use App\Models\User;

/**
 * The shared step of approve and reject: one conditional update, so a decision is recorded
 * at most once, also when two reviewers click at the same time or a tab is stale.
 */
class DecideNoteExtraction
{
    /**
     * @param  array<string, mixed>  $fields  extracted fields to save with the decision
     *
     * @throws InvalidNoteExtractionStatusTransitionException
     */
    public function execute(
        OrderNoteExtraction $extraction,
        NoteExtractionStatus $decision,
        User $reviewer,
        array $fields = [],
    ): OrderNoteExtraction {
        $from = NoteExtractionStatus::NeedsReview;
        $from->transitionTo($decision);

        $updated = OrderNoteExtraction::query()
            ->whereKey($extraction->getKey())
            ->where('status', $from->value)
            ->update([
                ...$fields,
                'status' => $decision->value,
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            $current = OrderNoteExtraction::query()->whereKey($extraction->getKey())->value('status');

            throw new InvalidNoteExtractionStatusTransitionException(
                is_string($current) ? NoteExtractionStatus::from($current) : $extraction->status,
                $decision,
            );
        }

        return $extraction->refresh();
    }
}
