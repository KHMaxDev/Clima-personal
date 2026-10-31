<?php

namespace Tests\Feature;

use App\Exceptions\WeatherUnavailableException;
use App\Services\Weather\OpenMeteoService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenMeteoServiceTest extends TestCase
{
    public function test_normalizes_hourly_forecast_to_utc_entries(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response($this->fixture(), 200)]);

        $result = app(OpenMeteoService::class)->forecast(21.16, -86.85, 'America/Cancun', 7);

        $this->assertCount(3, $result['hourly']);
        $this->assertSame('2026-07-01T19:00:00+00:00', $result['hourly'][0]['time']);
        $this->assertSame(33.0, $result['hourly'][2]['temperature_2m']);
        $this->assertNull($result['hourly'][0]['relative_humidity_2m']);
    }

    public function test_forecast_sends_expected_parameters(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response($this->fixture(), 200)]);

        app(OpenMeteoService::class)->forecast(21.16, -86.85, 'America/Cancun', 7);

        Http::assertSent(function (Request $request) {
            $hourly = 'temperature_2m,apparent_temperature,relative_humidity_2m,precipitation_probability,precipitation,wind_speed_10m,wind_gusts_10m,uv_index,weather_code,cloud_cover,shortwave_radiation';

            return str_contains($request->url(), '/forecast')
                && $request['hourly'] === $hourly
                && $request['timezone'] === 'America/Cancun'
                && (int) $request['forecast_days'] === 7;
        });
    }

    public function test_forecast_throws_when_service_fails(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response('error', 500)]);

        $this->expectException(WeatherUnavailableException::class);

        app(OpenMeteoService::class)->forecast(21.16, -86.85, 'America/Cancun', 7);
    }

    public function test_geocode_maps_results(): void
    {
        Http::fake([
            'geocoding-api.open-meteo.com/*' => Http::response([
                'results' => [[
                    'name' => 'Cancún',
                    'admin1' => 'Quintana Roo',
                    'country' => 'México',
                    'latitude' => 21.1619,
                    'longitude' => -86.8515,
                    'timezone' => 'America/Cancun',
                ]],
            ], 200),
        ]);

        $results = app(OpenMeteoService::class)->geocode('Cancún');

        $this->assertCount(1, $results);
        $this->assertSame('Cancún', $results[0]['name']);
        $this->assertSame('America/Cancun', $results[0]['timezone']);
    }

    private function fixture(): string
    {
        return file_get_contents(base_path('tests/Fixtures/open-meteo/forecast_cancun.json'));
    }
}
