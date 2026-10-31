<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class WeatherSnapshotFactory extends Factory
{
    public function definition(): array
    {
        return [
            'provider' => 'open-meteo',
            'latitude' => 21.1619,
            'longitude' => -86.8515,
            'forecast_for' => now()->toDateString(),
            'forecast_days' => 7,
            'fetched_at' => now(),
            'expires_at' => now()->addHour(),
            'payload' => ['hourly' => []],
        ];
    }
}
