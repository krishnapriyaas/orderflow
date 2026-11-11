<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * The wholesaler's calendar. "Today" for order notes and reviews is the date in
 * orderflow.business_timezone (Europe/Berlin), not the server's UTC date.
 */
final class BusinessDate
{
    /**
     * Midnight today in the business time zone.
     */
    public static function today(): CarbonImmutable
    {
        $timezone = config('orderflow.business_timezone');

        return CarbonImmutable::now(is_string($timezone) ? $timezone : 'Europe/Berlin')->startOfDay();
    }
}
