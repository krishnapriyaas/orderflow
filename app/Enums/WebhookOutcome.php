<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What happened to a payment webhook event.
 *
 * `Duplicate` is only returned in the response: a duplicate never gets its own row.
 */
enum WebhookOutcome: string
{
    case Applied = 'applied';
    case Duplicate = 'duplicate';
    case IgnoredTransition = 'ignored_transition';
    case UnknownReference = 'unknown_reference';
    case AmountMismatch = 'amount_mismatch';
    case UnknownType = 'unknown_type';
}
