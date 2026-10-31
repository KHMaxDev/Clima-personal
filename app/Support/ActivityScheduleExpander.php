<?php

namespace App\Support;

use Carbon\CarbonImmutable;

class ActivityScheduleExpander
{
    public function __construct(
        private int $horizonDays = 7,
    ) {}

    /**
     * Convierte un patrón (días ISO + horas locales) en ocurrencias concretas
     * dentro del horizonte configurado a partir de "ahora".
     *
     * @param  int[]  $days
     * @return array<int, array{start: CarbonImmutable, end: CarbonImmutable}>
     */
    public function expand(
        array $days,
        string $startTime,
        string $endTime,
        string $timezone,
        ?CarbonImmutable $now = null,
        ?int $limit = null,
    ): array {
        $now = ($now ?? CarbonImmutable::now($timezone))->setTimezone($timezone);
        $today = $now->startOfDay();
        $isoWeekday = $now->isoWeekday();

        $days = $this->normalizeDays($days);

        $dates = $days === []
            ? [$this->nextSingleDate($now, $startTime)]
            : $this->datesForDays($today, $isoWeekday, $days, $now, $startTime);

        $occurrences = [];
        foreach ($dates as $date) {
            $start = $this->compose($date, $startTime);
            $end = $this->compose($date, $endTime);

            if ($end->lessThanOrEqualTo($start)) {
                $end = $end->addDay();
            }

            $occurrences[] = ['start' => $start, 'end' => $end];
        }

        usort($occurrences, fn (array $a, array $b) => $a['start']->getTimestamp() <=> $b['start']->getTimestamp());

        if ($limit !== null && $limit > 0) {
            $occurrences = array_slice($occurrences, 0, $limit);
        }

        return $occurrences;
    }

    /**
     * @param  int[]  $days
     * @return int[]
     */
    private function normalizeDays(array $days): array
    {
        $normalized = [];

        foreach ($days as $day) {
            $day = (int) $day;

            if ($day >= 1 && $day <= 7) {
                $normalized[$day] = $day;
            }
        }

        return array_values($normalized);
    }

    /**
     * @param  int[]  $days
     * @return array<int, CarbonImmutable>
     */
    private function datesForDays(
        CarbonImmutable $today,
        int $isoWeekday,
        array $days,
        CarbonImmutable $now,
        string $startTime,
    ): array {
        $dates = [];

        foreach ($days as $day) {
            $offset = ($day - $isoWeekday + 7) % 7;

            if ($offset === 0 && ! $this->isFutureToday($now, $startTime)) {
                continue;
            }

            if ($offset >= $this->horizonDays) {
                continue;
            }

            $dates[$day] = $today->addDays($offset);
        }

        return array_values($dates);
    }

    private function nextSingleDate(CarbonImmutable $now, string $startTime): CarbonImmutable
    {
        return $this->isFutureToday($now, $startTime)
            ? $now->startOfDay()
            : $now->startOfDay()->addDay();
    }

    private function isFutureToday(CarbonImmutable $now, string $startTime): bool
    {
        return $this->compose($now->startOfDay(), $startTime)->greaterThan($now);
    }

    private function compose(CarbonImmutable $date, string $time): CarbonImmutable
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        $extraDays = intdiv($hours, 24);
        $hours %= 24;

        return $date
            ->startOfDay()
            ->addDays($extraDays)
            ->setTime($hours, $minutes);
    }
}
