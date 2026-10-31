<?php

namespace App\Support;

use App\Exceptions\WeatherUnavailableException;
use App\Services\Weather\OpenMeteoService;
use Illuminate\Support\Facades\Cache;

class PlaceLabelResolver
{
    public function __construct(private OpenMeteoService $openMeteo) {}

    /**
     * Enriquecen un nombre de lugar con ciudad/estado/país usando el geocoding
     * de Open-Meteo (misma conexión ya usada por la búsqueda de ubicaciones).
     *
     * @return array{city: string, state: ?string, country: ?string}
     */
    public function resolve(?string $name, ?float $latitude = null, ?float $longitude = null): array
    {
        $name = trim((string) $name);
        $fallback = ['city' => $name !== '' ? $name : 'Ubicación actual', 'state' => null, 'country' => null];

        if ($name === '') {
            return $fallback;
        }

        $key = sprintf(
            'place-label:%s:%.2f:%.2f',
            mb_strtolower($name),
            (float) $latitude,
            (float) $longitude,
        );

        return Cache::remember($key, now()->addDay(), function () use ($name, $latitude, $longitude, $fallback) {
            try {
                $results = $this->openMeteo->geocode($name, 5);
            } catch (WeatherUnavailableException) {
                return $fallback;
            }

            $best = $this->closest($results, $latitude, $longitude) ?? ($results[0] ?? null);

            if ($best === null) {
                return $fallback;
            }

            return [
                'city' => $best['name'] ?: $fallback['city'],
                'state' => $best['admin1'] ?: null,
                'country' => $best['country'] ?: null,
            ];
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     * @return array<string, mixed>|null
     */
    private function closest(array $results, ?float $latitude, ?float $longitude): ?array
    {
        if ($latitude === null || $longitude === null) {
            return null;
        }

        $best = null;
        $bestDistance = null;

        foreach ($results as $result) {
            if ($result['latitude'] === null || $result['longitude'] === null) {
                continue;
            }

            $distance = ($result['latitude'] - $latitude) ** 2 + ($result['longitude'] - $longitude) ** 2;

            if ($bestDistance === null || $distance < $bestDistance) {
                $bestDistance = $distance;
                $best = $result;
            }
        }

        return $best;
    }
}
