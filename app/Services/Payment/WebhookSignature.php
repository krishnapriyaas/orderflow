<?php

declare(strict_types=1);

namespace App\Services\Payment;

/**
 * The one place that defines how a payment webhook is signed.
 */
final class WebhookSignature
{
    /**
     * Lowercase hex HMAC-SHA256 of "{timestamp}.{raw body}".
     */
    public static function sign(string $timestamp, string $body, string $secret): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }
}
