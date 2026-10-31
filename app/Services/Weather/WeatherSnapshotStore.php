<?php

namespace App\Services\Weather;

use App\Models\Activity;
use App\Models\WeatherSnapshot;
use Carbon\CarbonImmutable;

class WeatherSnapshotStore
{
    public function __construct(
        private OpenMeteoService $openMeteo,
        private array $config,
    ) {}

    public function forActivity(Activity $activity): WeatherSnapshot
    {
        $latitude = round((float) $activity->latitude, 2);
        $longitude = round((float) $activity->longitude, 2);
        $forecastFor = $this->localDate($activity)->toDateString();
        $days = $this->forecastDays($activity);
        $provider = (string) ($this->config['provider'] ?? 'open-meteo');

        $existing = WeatherSnapshot::query()
            ->where('provider', $provider)
            ->where('latitude', $latitude)
            ->where('longitude', $longitude)
            ->where('forecast_for', $forecastFor)
            ->where('forecast_days', $days)
            ->first();

        if ($existing !== null && ! $existing->isExpired()) {
            return $existing;
        }

        $forecast = $this->openMeteo->forecast(
            (float) $activity->latitude,
            (float) $activity->longitude,
            $activity->timezone,
            $days,
        );

        $now = CarbonImmutable::now();

        return WeatherSnapshot::updateOrCreate(
            [
                'provider' => $provider,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'forecast_for' => $forecastFor,
                'forecast_days' => $days,
            ],
            [
                'fetched_at' => $now,
                'expires_at' => $now->addHours($this->ttlHours($activity)),
                'payload' => $forecast,
            ],
        );
    }

    private function localDate(Activity $activity): CarbonImmutable
    {
        return CarbonImmutable::instance($activity->start_at)->setTimezone($activity->timezone)->startOfDay();
    }

    private function forecastDays(Activity $activity): int
    {
        $days = CarbonImmutable::now()->startOfDay()->diffInDays($this->localDate($activity), false) + 1;

        return (int) max(1, min((int) ($this->config['forecast_days'] ?? 16), $days));
    }

    private function ttlHours(Activity $activity): int
    {
        $days = CarbonImmutable::now()->startOfDay()->diffInDays($this->localDate($activity), false);
        $ttl = $this->config['cache_ttl_hours'];

        return match (true) {
            $days < 1 => (int) $ttl['lt_24h'],
            $days < 3 => (int) $ttl['lt_3d'],
            $days < 7 => (int) $ttl['lt_7d'],
            default => (int) $ttl['default'],
        };
    }
}
