<?php

namespace App\Services\Weather;

use Carbon\CarbonImmutable;
use DateTimeInterface;

class WeatherAggregator
{
    public function __construct(
        private array $operators,
        private array $severityOrder,
        private int $overlapMinMinutes = 15,
    ) {}

    public function aggregate(array $hourly, DateTimeInterface $start, DateTimeInterface $end): array
    {
        $start = CarbonImmutable::instance($start)->utc();
        $end = CarbonImmutable::instance($end)->utc();

        $durationMinutes = $start->diffInMinutes($end);

        if ($durationMinutes <= 0 || $hourly === []) {
            return [
                'values' => [],
                'coverage' => 0.0,
                'missing' => $this->expectedVariables($hourly),
                'hours_used' => 0,
                'interval' => [$start->toIso8601String(), $end->toIso8601String()],
            ];
        }

        $accumulators = [];
        $totalOverlap = 0.0;
        $hoursUsed = 0;

        foreach ($hourly as $entry) {
            if (! is_array($entry) || ! array_key_exists('time', $entry)) {
                continue;
            }

            $hourStart = $this->toCarbon($entry['time']);
            $hourEnd = $hourStart->addHour();

            $overlapStart = $start->max($hourStart);
            $overlapEnd = $end->min($hourEnd);

            $overlapMinutes = $overlapEnd->greaterThan($overlapStart)
                ? (float) $overlapStart->diffInMinutes($overlapEnd)
                : 0.0;

            $hourSpan = (float) $hourStart->diffInMinutes($hourEnd);
            $threshold = max((float) $this->overlapMinMinutes, 0.25 * $hourSpan);

            if ($overlapMinutes < $threshold || $overlapMinutes <= 0) {
                continue;
            }

            $hoursUsed++;
            $totalOverlap += $overlapMinutes;
            $fraction = $hourSpan > 0 ? $overlapMinutes / $hourSpan : 0.0;

            foreach ($entry as $variable => $rawValue) {
                if ($variable === 'time' || $rawValue === null) {
                    continue;
                }

                $operator = $this->operators[$variable] ?? 'max';
                $this->accumulate($accumulators, $variable, $operator, $rawValue, $fraction);
            }
        }

        $values = [];
        foreach ($accumulators as $variable => $accumulator) {
            $values[$variable] = $this->finalize($accumulator['operator'], $accumulator);
        }

        $expected = $this->expectedVariables($hourly);
        $missing = array_values(array_diff($expected, array_keys($values)));

        $coverage = min(1.0, $totalOverlap / $durationMinutes);

        return [
            'values' => $values,
            'coverage' => round($coverage, 3),
            'missing' => $missing,
            'hours_used' => $hoursUsed,
            'interval' => [$start->toIso8601String(), $end->toIso8601String()],
        ];
    }

    private function accumulate(array &$accumulators, string $variable, string $operator, mixed $value, float $fraction): void
    {
        if (! isset($accumulators[$variable])) {
            $accumulators[$variable] = [
                'operator' => $operator,
                'max' => null,
                'min' => null,
                'sum' => 0.0,
                'weighted' => 0.0,
                'count' => 0,
                'worst' => null,
                'worst_rank' => -1,
            ];
        }

        $accumulator = &$accumulators[$variable];

        match ($operator) {
            'min' => $accumulator['min'] = $accumulator['min'] === null ? $value : min($accumulator['min'], $value),
            'mean' => $this->accumulateMean($accumulator, $value),
            'sum' => $accumulator['weighted'] += ((float) $value) * $fraction,
            'worst' => $this->accumulateWorst($accumulator, $value),
            default => $accumulator['max'] = $accumulator['max'] === null ? $value : max($accumulator['max'], $value),
        };
    }

    private function accumulateMean(array &$accumulator, mixed $value): void
    {
        $accumulator['sum'] += (float) $value;
        $accumulator['count']++;
    }

    private function accumulateWorst(array &$accumulator, mixed $value): void
    {
        $rank = $this->severityRank((int) $value);

        if ($rank > $accumulator['worst_rank'] || ($rank === $accumulator['worst_rank'] && $value > ($accumulator['worst'] ?? -1))) {
            $accumulator['worst_rank'] = $rank;
            $accumulator['worst'] = (int) $value;
        }
    }

    private function finalize(string $operator, array $accumulator): int|float
    {
        return match ($operator) {
            'min' => $accumulator['min'],
            'mean' => $accumulator['count'] > 0 ? round($accumulator['sum'] / $accumulator['count'], 2) : 0.0,
            'sum' => round($accumulator['weighted'], 2),
            'worst' => $accumulator['worst'],
            default => $accumulator['max'],
        };
    }

    private function severityRank(int $code): int
    {
        $rank = array_search($code, $this->severityOrder, true);

        return $rank === false ? -1 : (int) $rank;
    }

    private function expectedVariables(array $hourly): array
    {
        $variables = [];

        foreach ($hourly as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            foreach (array_keys($entry) as $key) {
                if ($key !== 'time') {
                    $variables[$key] = true;
                }
            }
        }

        return array_keys($variables);
    }

    private function toCarbon(mixed $time): CarbonImmutable
    {
        if ($time instanceof DateTimeInterface) {
            return CarbonImmutable::instance($time)->utc();
        }

        if (is_numeric($time)) {
            return CarbonImmutable::createFromTimestamp((int) $time)->utc();
        }

        return CarbonImmutable::parse((string) $time)->utc();
    }
}
