<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\User;
use App\Services\Assessment\ActivityEvaluationService;
use Carbon\CarbonImmutable;
use Database\Seeders\ActivityCategorySeeder;
use Database\Seeders\ActivityRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private string $timezone = 'America/Cancun';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ActivityCategorySeeder::class);
        $this->seed(ActivityRuleSeeder::class);

        Http::fake(function (Request $request) {
            if (str_contains($request->url(), 'geocoding-api')) {
                return Http::response(['results' => [[
                    'name' => 'Cancún',
                    'admin1' => 'Quintana Roo',
                    'country' => 'México',
                    'latitude' => 21.16,
                    'longitude' => -86.85,
                    'timezone' => $this->timezone,
                ]]], 200);
            }

            if (str_contains($request->url(), 'current=')) {
                return Http::response($this->conditions(), 200);
            }

            return Http::response(json_decode($this->fixture(), true), 200);
        });

        $this->travelTo(CarbonImmutable::parse('2026-07-01 06:00', $this->timezone));
    }

    public function test_weather_endpoint_returns_current_and_hourly(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson(route('weather.summary', [
            'latitude' => 21.16,
            'longitude' => -86.85,
            'timezone' => $this->timezone,
        ]));

        $response->assertOk()
            ->assertJsonStructure([
                'current' => ['time', 'values'],
                'hourly',
                'timezone',
                'updated_at',
                'source',
            ])
            ->assertJsonPath('current.values.weather_code', 2);
    }

    public function test_weather_endpoint_validates_input(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('weather.summary', ['latitude' => 200, 'longitude' => 0, 'timezone' => 'Nope/Zone']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['latitude', 'timezone']);
    }

    public function test_weather_endpoint_requires_authentication(): void
    {
        $this->getJson(route('weather.summary', [
            'latitude' => 21.16,
            'longitude' => -86.85,
            'timezone' => $this->timezone,
        ]))->assertUnauthorized();
    }

    public function test_dashboard_renders_island_with_default_location(): void
    {
        $user = User::factory()->create();
        $this->activity($user, '2026-07-01 08:00', '2026-07-01 09:00');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-react-component="Dashboard"', false)
            ->assertSee('weatherLocation', false);
    }

    public function test_forecast_and_calendar_pages_render(): void
    {
        $user = User::factory()->create();
        $this->activity($user, '2026-07-01 08:00', '2026-07-01 09:00');

        $this->actingAs($user)
            ->get(route('forecast'))
            ->assertOk()
            ->assertSee('data-react-component="Forecast"', false);

        $this->actingAs($user)
            ->get(route('calendar'))
            ->assertOk()
            ->assertSee('data-react-component="ActivityCalendar"', false);
    }

    public function test_history_lists_assessments(): void
    {
        $user = User::factory()->create();
        $activity = $this->activity($user, '2026-07-01 14:00', '2026-07-01 15:00');

        app(ActivityEvaluationService::class)->evaluate($activity);

        $this->actingAs($user)
            ->get(route('history'))
            ->assertOk()
            ->assertSee($activity->title);
    }

    public function test_alternatives_endpoint_returns_better_slots(): void
    {
        $user = User::factory()->create();
        $activity = $this->activity($user, '2026-07-01 16:00', '2026-07-01 17:00');

        $response = $this->actingAs($user)->getJson(route('activities.alternatives', $activity));

        $response->assertOk()->assertJsonPath('found', true);

        $alternatives = $response->json('alternatives');

        $this->assertNotEmpty($alternatives);

        foreach ($alternatives as $alternative) {
            $this->assertNotSame('high_risk', $alternative['status']);
            $this->assertNotSame('insufficient', $alternative['status']);
        }
    }

    public function test_alternatives_endpoint_forbids_other_users(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $activity = $this->activity($owner, '2026-07-01 16:00', '2026-07-01 17:00');

        $this->actingAs($intruder)
            ->getJson(route('activities.alternatives', $activity))
            ->assertForbidden();
    }

    private function activity(User $user, string $startLocal, string $endLocal): Activity
    {
        $running = ActivityCategory::where('slug', 'running')->firstOrFail();

        return Activity::factory()->create([
            'user_id' => $user->id,
            'category_id' => $running->id,
            'start_at' => CarbonImmutable::parse($startLocal, $this->timezone),
            'end_at' => CarbonImmutable::parse($endLocal, $this->timezone),
            'timezone' => $this->timezone,
            'latitude' => 21.16,
            'longitude' => -86.85,
            'location_name' => 'Cancún',
        ]);
    }

    private function fixture(): string
    {
        return file_get_contents(base_path('tests/Fixtures/open-meteo/forecast_cancun.json'));
    }

    /**
     * @return array<string, mixed>
     */
    private function conditions(): array
    {
        return [
            'latitude' => 21.16,
            'longitude' => -86.85,
            'timezone' => $this->timezone,
            'utc_offset_seconds' => -18000,
            'current' => [
                'time' => '2026-07-01T14:15',
                'temperature_2m' => 31.5,
                'apparent_temperature' => 36.0,
                'relative_humidity_2m' => 60,
                'precipitation' => 0,
                'weather_code' => 2,
                'wind_speed_10m' => 6,
                'wind_gusts_10m' => 9,
                'wind_direction_10m' => 90,
                'uv_index' => 3,
                'cloud_cover' => 15,
            ],
            'hourly' => [
                'time' => ['2026-07-01T14:00', '2026-07-01T15:00', '2026-07-01T16:00'],
                'temperature_2m' => [30.1, 31.5, 33.0],
                'apparent_temperature' => [34.0, 36.0, 38.5],
                'precipitation_probability' => [0, 0, 0],
                'precipitation' => [0, 0, 0],
                'weather_code' => [2, 2, 3],
                'wind_speed_10m' => [6, 6, 6],
                'uv_index' => [3, 3, 4],
            ],
        ];
    }
}
