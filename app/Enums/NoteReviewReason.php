<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Why an order note extraction needs a human review.
 */
enum NoteReviewReason: string
{
    case LowConfidence = 'low_confidence';
    case InstructionsDetected = 'instructions_detected';
    case PastDeliveryDate = 'past_delivery_date';
    case DeliveryDateTooFarAhead = 'delivery_date_too_far_ahead';
    /** The model's answer was not valid JSON or did not match the expected fields. */
    case InvalidResponse = 'invalid_response';
    /** The model declined the request (stop_reason "refusal"). */
    case Refused = 'refused';
    /** The API rejected the request (4xx other than 429), for example a bad API key. */
    case ServiceError = 'service_error';
    /** The API was unreachable or overloaded on every attempt. */
    case ServiceUnavailable = 'service_unavailable';
    /** Anything else that made the job fail. */
    case ExtractionFailed = 'extraction_failed';
}
