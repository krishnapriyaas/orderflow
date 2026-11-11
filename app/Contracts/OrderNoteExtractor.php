<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Data\ExtractedOrderNote;
use App\Exceptions\InvalidNoteExtractionException;
use App\Exceptions\NoteExtractionUnavailableException;
use Carbon\CarbonImmutable;

/**
 * Extracts structured data from a free-text order note.
 *
 * Implementations must not trust the note: it is customer input. They throw
 * NoteExtractionUnavailableException for temporary problems (the job retries) and
 * InvalidNoteExtractionException for everything that would fail again (sent to review).
 */
interface OrderNoteExtractor
{
    /**
     * @param  CarbonImmutable  $today  the business date, used to resolve "next Friday"
     *
     * @throws NoteExtractionUnavailableException
     * @throws InvalidNoteExtractionException
     */
    public function extract(string $note, CarbonImmutable $today): ExtractedOrderNote;
}
