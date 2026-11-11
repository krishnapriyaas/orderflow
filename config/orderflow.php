<?php

declare(strict_types=1);

return [

    // The wholesaler's local time. "Today" for order notes ("deliver next Friday") is this
    // date, not the server's UTC date.
    'business_timezone' => env('BUSINESS_TIMEZONE', 'Europe/Berlin'),

    'note_extraction' => [
        // Extractions at or above this confidence are applied without a human review.
        'auto_apply_threshold' => (float) env('NOTE_EXTRACTION_AUTO_APPLY_THRESHOLD', 0.8),

        // A delivery date further ahead than this is sent to review.
        'max_days_ahead' => 365,
    ],

];
