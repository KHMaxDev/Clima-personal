<?php

namespace App\Services\Weather;

use App\Exceptions\WeatherUnavailableException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Response;

class OpenMeteoService
{
    public function __construct(
        private Factory $http,
        private array $config,
    ) {}

    public function geocode(string $name, int $count = 8): array
    {
        $response = $this->http
            ->timeout((int) ($this->config['timeout'] ?? 10))
            ->get(rtrim((string) $this->config['geocoding_url'], '/').'/search', [
                'name' => $name,
                'count' => $count,
                'language' => 'es',
                'format' => 'json',
            ]);

        if ($response->failed()) {
            throw new WeatherUnavailableException('El servicio de búsqueda de ubicaciones no está disponible.');
        }

        $results = $response->json('results') ?? [];

        return array_map(fn (array $item) => [
            'name' => $item['name'] ?? null,
            'admin1' => $item['admin1'] ?? null,
            'country' => $item['country'] ?? null,
            'latitude' => $item['latitude'] ?? null,
            'longitude' => $item['longitude'] ?? null,
            'timezone' => $item['timezone'] ?? 'UTC',
        ], $results);
    }

    public function forecast(float $latitude, float $longitude, string $timezone, ?int $days = null): array
    {
        $days ??= (int) ($this->config['forecast_days'] ?? 7);

        $response = $this->http
            ->timeout((int) ($this->config['timeout'] ?? 10))
            ->get(rtrim((string) $this->config['base_url'], '/').'/forecast', [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'hourly' => implode(',', $this->config['hourly_variables']),
                'timezone' => $timezone,
                'forecast_days' => $days,
            ]);

        if ($response->failed()) {
            throw new WeatherUnavailableException('El servicio de pronóstico no está disponible.');
        }

        return [
            'latitude' => $response->json('latitude'),
            'longitude' => $response->json('longitude'),
            'elevation' => $response->json('elevation'),
            'timezone' => $response->json('timezone'),
            'utc_offset_seconds' => $response->json('utc_offset_seconds'),
            'hourly' => $this->normalizeHourly($response, $timezone, $this->config['hourly_variables']),
        ];
    }

    /**
     * Condiciones actuales y próximas horas para una ubicación (tarjeta de clima).
     *
     * @return array<string, mixed>
     */
    public function conditions(float $latitude, float $longitude, string $timezone, int $hours = 24): array
    {
        $currentVariables = [
            'temperature_2m',
            'apparent_temperature',
            'relative_humidity_2m',
            'precipitation',
            'weather_code',
            'wind_speed_10m',
            'wind_gusts_10m',
            'wind_direction_10m',
            'uv_index',
            'cloud_cover',
        ];

        $hourlyVariables = [
            'temperature_2m',
            'apparent_temperature',
            'precipitation_probability',
            'precipitation',
            'weather_code',
            'wind_speed_10m',
            'uv_index',
        ];

        $response = $this->http
            ->timeout((int) ($this->config['timeout'] ?? 10))
            ->get(rtrim((string) $this->config['base_url'], '/').'/forecast', [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'current' => implode(',', $currentVariables),
                'hourly' => implode(',', $hourlyVariables),
                'timezone' => $timezone,
                'forecast_days' => 3,
            ]);

        if ($response->failed()) {
            throw new WeatherUnavailableException('El servicio de pronóstico no está disponible.');
        }

        $current = [];
        foreach ($currentVariables as $variable) {
            $current[$variable] = $response->json('current.'.$variable);
        }

        $currentTime = $response->json('current.time');

        return [
            'latitude' => $response->json('latitude'),
            'longitude' => $response->json('longitude'),
            'timezone' => $response->json('timezone'),
            'utc_offset_seconds' => $response->json('utc_offset_seconds'),
            'current' => [
                'time' => $currentTime !== null
                    ? CarbonImmutable::parse($currentTime, $timezone)->utc()->toIso8601String()
                    : null,
                'values' => $current,
            ],
            'hourly' => $this->normalizeHourly($response, $timezone, $hourlyVariables),
            'hours' => $hours,
        ];
    }

    /**
     * @param  array<int, string>  $variables
     * @return array<int, array<string, mixed>>
     */
    private function normalizeHourly(Response $response, string $timezone, array $variables): array
    {
        $times = $response->json('hourly.time') ?? [];

        $columns = [];
        foreach ($variables as $variable) {
            $columns[$variable] = $response->json('hourly.'.$variable) ?? [];
        }

        $entries = [];

        foreach ($times as $index => $localTime) {
            $entry = [
                'time' => CarbonImmutable::parse($localTime, $timezone)->utc()->toIso8601String(),
            ];

            foreach ($columns as $variable => $series) {
                $entry[$variable] = $series[$index] ?? null;
            }

            $entries[] = $entry;
        }

        return $entries;
    }
}
