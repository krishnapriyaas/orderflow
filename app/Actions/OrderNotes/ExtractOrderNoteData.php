<?php

declare(strict_types=1);

namespace App\Actions\OrderNotes;

use App\Contracts\OrderNoteExtractor;
use App\Data\ExtractedOrderNote;
use App\Enums\NoteExtractionStatus;
use App\Enums\NoteReviewReason;
use App\Exceptions\InvalidNoteExtractionException;
use App\Exceptions\NoteExtractionUnavailableException;
use App\Models\Order;
use App\Models\OrderNoteExtraction;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExtractOrderNoteData
{
    public function __construct(private readonly OrderNoteExtractor $extractor) {}

    /**
     * Extracts the order note once per order. Returns null when the order has no note.
     *
     * @throws NoteExtractionUnavailableException when the LLM is temporarily unavailable (the job retries)
     */
    public function execute(Order $order): ?OrderNoteExtraction
    {
        $note = trim((string) $order->note);

        if ($note === '') {
            return null;
        }

        $existing = $order->noteExtraction()->first();

        if ($existing !== null) {
            return $existing;
        }

        $today = $this->today();

        try {
            $result = $this->extractor->extract($note, $today);
        } catch (InvalidNoteExtractionException $e) {
            Log::warning('Order note extraction sent to review.', [
                'order_uuid' => $order->uuid,
                'reason' => $e->reason->value,
                'error' => $e->getMessage(),
            ]);

            return $this->store($order, [
                'status' => NoteExtractionStatus::NeedsReview,
                'review_reason' => $e->reason,
                'error' => $e->getMessage(),
                'raw_response' => $e->rawResponse,
            ]);
        }

        $reason = $this->reviewReason($result, $today);

        return $this->store($order, [
            'delivery_date' => $result->deliveryDate,
            'po_number' => $result->poNumber,
            'invoice_address' => $result->invoiceAddress,
            'confidence' => $result->confidence,
            'instructions_detected' => $result->instructionsDetected,
            'status' => $reason === null ? NoteExtractionStatus::AutoApplied : NoteExtractionStatus::NeedsReview,
            'review_reason' => $reason,
            'raw_response' => $result->rawResponse,
        ]);
    }

    /**
     * Called by the job after its last attempt failed: the order still gets a row,
     * in review, so no paid order is silently left without one.
     */
    public function recordFailure(Order $order, ?Throwable $exception): ?OrderNoteExtraction
    {
        if (trim((string) $order->note) === '') {
            return null;
        }

        $existing = $order->noteExtraction()->first();

        if ($existing !== null) {
            return $existing;
        }

        Log::error('Order note extraction failed after all attempts.', [
            'order_uuid' => $order->uuid,
            'error' => $exception?->getMessage(),
        ]);

        return $this->store($order, [
            'status' => NoteExtractionStatus::NeedsReview,
            'review_reason' => $exception instanceof NoteExtractionUnavailableException
                ? NoteReviewReason::ServiceUnavailable
                : NoteReviewReason::ExtractionFailed,
            'error' => $exception?->getMessage(),
            'raw_response' => $exception instanceof NoteExtractionUnavailableException ? $exception->rawResponse : null,
        ]);
    }

    /**
     * The business rules. Null means the result can be applied without a review.
     */
    private function reviewReason(ExtractedOrderNote $result, CarbonImmutable $today): ?NoteReviewReason
    {
        // Checked first: an injected note may also claim a high confidence.
        if ($result->instructionsDetected) {
            return NoteReviewReason::InstructionsDetected;
        }

        if ($result->deliveryDate !== null) {
            // Compare calendar dates (Y-m-d strings), not timestamps: the delivery date has no
            // time zone, and "today" is midnight in Berlin, which is 23:00 UTC the day before.
            $deliveryDate = $result->deliveryDate->toDateString();
            $maxDaysAhead = config('orderflow.note_extraction.max_days_ahead');

            if ($deliveryDate < $today->toDateString()) {
                return NoteReviewReason::PastDeliveryDate;
            }

            if ($deliveryDate > $today->addDays(is_int($maxDaysAhead) ? $maxDaysAhead : 365)->toDateString()) {
                return NoteReviewReason::DeliveryDateTooFarAhead;
            }
        }

        $threshold = config('orderflow.note_extraction.auto_apply_threshold');

        if ($result->confidence < (is_float($threshold) || is_int($threshold) ? $threshold : 0.8)) {
            return NoteReviewReason::LowConfidence;
        }

        return null;
    }

    /**
     * The business date: "today" for the wholesaler, not the server's UTC date.
     */
    private function today(): CarbonImmutable
    {
        $timezone = config('orderflow.business_timezone');

        return CarbonImmutable::now(is_string($timezone) ? $timezone : 'Europe/Berlin')->startOfDay();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function store(Order $order, array $attributes): OrderNoteExtraction
    {
        try {
            /** @var OrderNoteExtraction */
            return $order->noteExtraction()->create($attributes);
        } catch (UniqueConstraintViolationException) {
            // Another run for the same order stored its row first; keep that one.
            return $order->noteExtraction()->firstOrFail();
        }
    }
}
