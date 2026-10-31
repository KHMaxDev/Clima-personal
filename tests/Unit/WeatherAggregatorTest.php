<?php

namespace Tests\Unit;

use App\Services\Weather\WeatherAggregator;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class WeatherAggregatorTest extends TestCase
{
    private WeatherAggregator $aggregator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->aggregator = new WeatherAggregator(
            operators: [
                'temperature_2m' => 'max',
                'apparent_temperature' => 'max',
                'relative_humidity_2m' => 'max',
                'precipitation_probability' => 'max',
                'precipitation' => 'sum',
                'wind_speed_10m' => 'max',
                'wind_gusts_10m' => 'max',
                'uv_index' => 'max',
                'cloud_cover' => 'mean',
                'shortwave_radiation' => 'mean',
                'weather_code' => 'worst',
            ],
            severityOrder: [0, 1, 2, 3, 45, 48, 51, 53, 55, 56, 57, 61, 63, 65, 66, 67, 71, 73, 75, 77, 80, 81, 82, 85, 86, 95, 96, 99],
            overlapMinMinutes: 15,
        );
    }

    public function test_aggregates_full_single_hour(): void
    {
        $hourly = [
            $this->hour('2026-07-01 14:00:00', ['temperature_2m' => 33.5, 'precipitation' => 2.0]),
        ];

        $result = $this->aggregator->aggregate(
            $hourly,
            CarbonImmutable::parse('2026-07-01 14:00:00', 'UTC'),
            CarbonImmutable::parse('2026-07-01 15:00:00', 'UTC'),
        );

        $this->assertSame(33.5, $result['values']['temperature_2m']);
        $this->assertSame(2.0, $result['values']['precipitation']);
        $this->assertSame(1.0, $result['coverage']);
        $this->assertSame(1, $result['hours_used']);
        $this->assertSame([], $result['missing']);
    }

    public function test_excludes_partial_hours_below_overlap_threshold(): void
    {
        $hourly = [
            $this->hour('2026-07-01 14:00:00', ['temperature_2m' => 30.0]),
            $this->hour('2026-07-01 15:00:00', ['temperature_2m' => 31.0]),
        ];

        $result = $this->aggregator->aggregate(
            $hourly,
            CarbonImmutable::parse('2026-07-01 14:50:00', 'UTC'),
            CarbonImmutable::parse('2026-07-01 15:10:00', 'UTC'),
        );

        $this->assertSame([], $result['values']);
        $this->assertSame(0.0, $result['coverage']);
        $this->assertSame(0, $result['hours_used']);
        $this->assertContains('temperature_2m', $result['missing']);
    }

    public function test_includes_partial_hour_at_threshold(): void
    {
        $hourly = [
            $this->hour('2026-07-01 14:00:00', ['temperature_2m' => 30.0]),
        ];

        $result = $this->aggregator->aggregate(
            $hourly,
            CarbonImmutable::parse('2026-07-01 14:00:00', 'UTC'),
            CarbonImmutable::parse('2026-07-01 14:15:00', 'UTC'),
        );

        $this->assertSame(30.0, $result['values']['temperature_2m']);
        $this->assertSame(1.0, $result['coverage']);
    }

    public function test_precipitation_sum_is_weighted_by_overlap(): void
    {
        $hourly = [
            $this->hour('2026-07-01 14:00:00', ['precipitation' => 4.0]),
        ];

        $result = $this->aggregator->aggregate(
            $hourly,
            CarbonImmutable::parse('2026-07-01 14:00:00', 'UTC'),
            CarbonImmutable::parse('2026-07-01 14:30:00', 'UTC'),
        );

        $this->assertSame(2.0, $result['values']['precipitation']);
        $this->assertSame(1.0, $result['coverage']);
    }

    public function test_weather_code_uses_worst_by_severity(): void
    {
        $hourly = [
            $this->hour('2026-07-01 14:00:00', ['weather_code' => 1]),
            $this->hour('2026-07-01 15:00:00', ['weather_code' => 95]),
            $this->hour('2026-07-01 16:00:00', ['weather_code' => 61]),
        ];

        $result = $this->aggregator->aggregate(
            $hourly,
            CarbonImmutable::parse('2026-07-01 14:00:00', 'UTC'),
            CarbonImmutable::parse('2026-07-01 17:00:00', 'UTC'),
        );

        $this->assertSame(95, $result['values']['weather_code']);
        $this->assertSame(1.0, $result['coverage']);
    }

    public function test_reports_missing_variables(): void
    {
        $hourly = [
            $this->hour('2026-07-01 14:00:00', ['temperature_2m' => 30.0, 'uv_index' => null]),
        ];

        $result = $this->aggregator->aggregate(
            $hourly,
            CarbonImmutable::parse('2026-07-01 14:00:00', 'UTC'),
            CarbonImmutable::parse('2026-07-01 15:00:00', 'UTC'),
        );

        $this->assertArrayHasKey('temperature_2m', $result['values']);
        $this->assertContains('uv_index', $result['missing']);
    }

    public function test_max_across_multiple_hours(): void
    {
        $hourly = [
            $this->hour('2026-07-01 14:00:00', ['temperature_2m' => 30.0]),
            $this->hour('2026-07-01 15:00:00', ['temperature_2m' => 36.0]),
        ];

        $result = $this->aggregator->aggregate(
            $hourly,
            CarbonImmutable::parse('2026-07-01 14:00:00', 'UTC'),
            CarbonImmutable::parse('2026-07-01 16:00:00', 'UTC'),
        );

        $this->assertSame(36.0, $result['values']['temperature_2m']);
    }

    private function hour(string $iso, array $values): array
    {
        return ['time' => $iso] + $values;
    }
}
