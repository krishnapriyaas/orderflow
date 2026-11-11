<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Extracts structured data from the order note with an LLM. Filled in by Step 4.
 */
class ExtractOrderNote implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order) {}

    public function handle(): void
    {
        // Step 4: call the OrderNoteExtractor and store the result or send it to review.
    }
}
