<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Support\BusinessDate;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class BusinessDateTest extends TestCase
{
    public function test_today_is_midnight_in_berlin(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-11 15:20', 'Europe/Berlin'));

        $today = BusinessDate::today();

        $this->assertSame('2026-11-11 00:00:00', $today->format('Y-m-d H:i:s'));
        $this->assertSame('Europe/Berlin', $today->getTimezone()->getName());
    }

    public function test_just_after_midnight_in_berlin_it_is_already_the_next_day(): void
    {
        // 23:30 UTC on the 11th is 00:30 on the 12th in Berlin.
        $this->travelTo(CarbonImmutable::parse('2026-11-11 23:30', 'UTC'));

        $this->assertSame('2026-11-12', BusinessDate::today()->toDateString());
    }

    public function test_the_time_zone_comes_from_config(): void
    {
        config(['orderflow.business_timezone' => 'America/New_York']);
        $this->travelTo(CarbonImmutable::parse('2026-11-12 02:00', 'UTC'));

        $this->assertSame('2026-11-11', BusinessDate::today()->toDateString());
    }
}
