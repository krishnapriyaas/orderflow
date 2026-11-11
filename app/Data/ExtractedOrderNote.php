<?php

declare(strict_types=1);

namespace App\Data;

use Carbon\CarbonImmutable;

/**
 * Structured data extracted from an order note. Shape-validated by the extractor;
 * business rules (dates, confidence threshold) are applied by ExtractOrderNoteData.
 */
final readonly class ExtractedOrderNote
{
    /**
     * @param  float  $confidence  0.0 – 1.0, as reported by the model
     * @param  bool  $instructionsDetected  the note tried to instruct the model
     * @param  string|null  $rawResponse  the provider's raw response body, kept for review
     */
    public function __construct(
        public ?CarbonImmutable $deliveryDate,
        public ?string $poNumber,
        public ?string $invoiceAddress,
        public float $confidence,
        public bool $instructionsDetected = false,
        public ?string $rawResponse = null,
    ) {}
}
