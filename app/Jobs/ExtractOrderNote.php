<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\OrderNotes\ExtractOrderNoteData;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Extracts structured data from the order note with an LLM. Queued when an order is paid.
 *
 * Temporary API problems throw and are retried with backoff. After the last attempt
 * failed() stores the extraction as needs_review, so the order is never left without one.
 */
class ExtractOrderNote implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public Order $order) {}

    /**
     * Seconds to wait before attempt 2 and attempt 3.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(ExtractOrderNoteData $extractOrderNoteData): void
    {
        $extractOrderNoteData->execute($this->order);
    }

    public function failed(?Throwable $exception): void
    {
        app(ExtractOrderNoteData::class)->recordFailure($this->order, $exception);
    }
}
