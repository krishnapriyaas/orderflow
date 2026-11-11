<?php

declare(strict_types=1);

namespace App\Data;

use Carbon\CarbonImmutable;

/**
 * The fields a reviewer approved on the back-office page.
 */
final readonly class ReviewedOrderNote
{
    /** A sanity limit against typos such as 2062 for 2026. */
    public const MAX_YEARS_AHEAD = 2;

    public function __construct(
        public ?CarbonImmutable $deliveryDate,
        public ?string $poNumber,
        public ?string $invoiceAddress,
    ) {}

    /**
     * Same shape and lengths as the LLM answer. A past delivery date is blocked. A date more
     * than 365 days ahead is allowed, because a person who checked the note is the check,
     * but not more than MAX_YEARS_AHEAD years ahead.
     *
     * @param  CarbonImmutable  $today  the business date (Europe/Berlin)
     * @param  string  $prefix  for nested input, for example "fields.12."
     * @return array<string, list<string>>
     */
    public static function rules(CarbonImmutable $today, string $prefix = ''): array
    {
        return [
            $prefix.'delivery_date' => [
                'nullable',
                'string',
                'date_format:Y-m-d',
                'after_or_equal:'.$today->toDateString(),
                'before_or_equal:'.$today->addYears(self::MAX_YEARS_AHEAD)->toDateString(),
            ],
            $prefix.'po_number' => ['nullable', 'string', 'max:'.ExtractedOrderNote::PO_NUMBER_MAX_LENGTH],
            $prefix.'invoice_address' => ['nullable', 'string', 'max:'.ExtractedOrderNote::INVOICE_ADDRESS_MAX_LENGTH],
        ];
    }

    /**
     * Builds the DTO from input that passed rules(). Blank values become null.
     *
     * @param  array<mixed>  $input
     */
    public static function fromValidated(array $input): self
    {
        $date = self::nullIfBlank($input['delivery_date'] ?? null);

        return new self(
            deliveryDate: $date === null ? null : (CarbonImmutable::createFromFormat('!Y-m-d', $date) ?: null),
            poNumber: self::nullIfBlank($input['po_number'] ?? null),
            invoiceAddress: self::nullIfBlank($input['invoice_address'] ?? null),
        );
    }

    private static function nullIfBlank(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}
