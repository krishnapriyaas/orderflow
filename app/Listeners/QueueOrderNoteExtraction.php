<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Jobs\ExtractOrderNote;

class QueueOrderNoteExtraction
{
    public function handle(OrderPaid $event): void
    {
        ExtractOrderNote::dispatch($event->order);
    }
}
