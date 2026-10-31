<?php

namespace Tests\Unit;

use App\Support\ActivityScheduleExpander;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class ActivityScheduleExpanderTest extends TestCase
{
    private string $timezone = 'America/Cancun';

    private ActivityScheduleExpander $expander;

    protected function setUp(): void
    {
        parent::setUp();

        $this->expander = new ActivityScheduleExpander(7);
    }

    public function test_it_expands_weekdays_within_the_horizon(): void
    {
        $now = CarbonImmutable::parse('2026-07-06 06:00', $this->timezone);

        $this->assertSame(1, $now->isoWeekday());

        $occurrences = $this->expander->expand([1, 2, 3, 4, 5], '15:00', '20:00', $this->timezone, $now);

        $this->assertCount(5, $occurrences);

        $expectedDates = ['2026-07-06', '2026-07-07', '2026-07-08', '2026-07-09', '2026-07-10'];

        foreach ($occurrences as $index => $occurrence) {
            $this->assertSame($expectedDates[$index], $occurrence['start']->toDateString());
            $this->assertSame('15:00', $occurrence['start']->format('H:i'));
            $this->assertSame('20:00', $occurrence['end']->format('H:i'));
            $this->assertSame($this->timezone, $occurrence['start']->timezoneName);
        }
    }

    public function test_it_skips_a_matching_day_whose_start_already_passed(): void
    {
        $now = CarbonImmutable::parse('2026-07-06 16:00', $this->timezone);

        $occurrences = $this->expander->expand([1, 2], '15:00', '20:00', $this->timezone, $now);

        $this->assertCount(1, $occurrences);
        $this->assertSame('2026-07-07', $occurrences[0]['start']->toDateString());
    }

    public function test_it_creates_nothing_when_the_only_matching_day_already_passed(): void
    {
        $now = CarbonImmutable::parse('2026-07-06 16:00', $this->timezone);

        $occurrences = $this->expander->expand([1], '15:00', '20:00', $this->timezone, $now);

        $this->assertCount(0, $occurrences);
    }

    public function test_it_rolls_over_to_the_next_day_when_end_is_earlier_than_start(): void
    {
        $now = CarbonImmutable::parse('2026-07-06 06:00', $this->timezone);

        $occurrences = $this->expander->expand([1], '22:00', '01:00', $this->timezone, $now);

        $this->assertCount(1, $occurrences);
        $this->assertSame('2026-07-06 22:00', $occurrences[0]['start']->format('Y-m-d H:i'));
        $this->assertSame('2026-07-07 01:00', $occurrences[0]['end']->format('Y-m-d H:i'));
    }

    public function test_it_uses_today_for_unknown_days_when_start_is_still_ahead(): void
    {
        $now = CarbonImmutable::parse('2026-07-06 06:00', $this->timezone);

        $occurrences = $this->expander->expand([], '15:00', '20:00', $this->timezone, $now);

        $this->assertCount(1, $occurrences);
        $this->assertSame('2026-07-06', $occurrences[0]['start']->toDateString());
    }

    public function test_it_uses_tomorrow_for_unknown_days_when_start_already_passed(): void
    {
        $now = CarbonImmutable::parse('2026-07-06 20:00', $this->timezone);

        $occurrences = $this->expander->expand([], '15:00', '20:00', $this->timezone, $now);

        $this->assertCount(1, $occurrences);
        $this->assertSame('2026-07-07', $occurrences[0]['start']->toDateString());
    }

    public function test_it_treats_all_seven_days_as_a_daily_schedule(): void
    {
        $now = CarbonImmutable::parse('2026-07-06 06:00', $this->timezone);

        $occurrences = $this->expander->expand([1, 2, 3, 4, 5, 6, 7], '08:00', '09:00', $this->timezone, $now);

        $this->assertCount(7, $occurrences);
        $this->assertSame('2026-07-06', $occurrences[0]['start']->toDateString());
        $this->assertSame('2026-07-12', $occurrences[6]['start']->toDateString());
    }

    public function test_it_orders_occurrences_and_respects_a_limit(): void
    {
        $now = CarbonImmutable::parse('2026-07-06 06:00', $this->timezone);

        $occurrences = $this->expander->expand([5, 1, 3], '07:00', '08:00', $this->timezone, $now, 2);

        $this->assertCount(2, $occurrences);
        $this->assertSame('2026-07-06', $occurrences[0]['start']->toDateString());
        $this->assertSame('2026-07-08', $occurrences[1]['start']->toDateString());
    }

    public function test_it_ignores_out_of_range_days(): void
    {
        $now = CarbonImmutable::parse('2026-07-06 06:00', $this->timezone);

        $occurrences = $this->expander->expand([0, 8, 1], '07:00', '08:00', $this->timezone, $now);

        $this->assertCount(1, $occurrences);
        $this->assertSame('2026-07-06', $occurrences[0]['start']->toDateString());
    }
}
