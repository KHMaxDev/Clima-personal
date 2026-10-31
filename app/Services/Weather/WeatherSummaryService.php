<?php

namespace App\Services\Weather;

use Illuminate\Support\Facades\Cache;

class WeatherSummaryService
{
    public function __construct(
        private OpenMeteoService $openMeteo,
        private array $config,
    ) {}

    /**
     * Resumen meteorológico de una ubicación (condiciones actuales + próximas horas).
     *
     * @return array<string, mixed>
     */
    public function summary(float $latitude, float $longitude, string $timezone, int $hours = 24): array
    {
        $provider = (string) ($this->config['provider'] ?? 'open-meteo');

        $key = sprintf(
            'weather-summary:%s:%s:%s:%s',
            $provider,
            round($latitude, 2),
            round($longitude, 2),
            $timezone,
        );

        return Cache::remember($key, now()->addMinutes(30), function () use ($latitude, $longitude, $timezone, $hours, $provider) {
            $data = $this->openMeteo->conditions($latitude, $longitude, $timezone, $hours);
            $data['source'] = $provider;
            $data['updated_at'] = now()->toIso8601String();

            return $data;
        });
    }
}
