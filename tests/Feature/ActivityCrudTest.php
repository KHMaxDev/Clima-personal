<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ActivityCrudTest extends TestCase
{
    use RefreshDatabase;

    private string $timezone = 'America/Cancun';

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeWeather();
    }

    public function test_user_cannot_view_another_users_activity(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $activity = Activity::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($intruder)
            ->get(route('activities.show', $activity))
            ->assertForbidden();
    }

    public function test_user_can_create_activity_and_it_is_evaluated(): void
    {
        $user = User::factory()->create();
        $category = ActivityCategory::factory()->create();

        [$start, $end] = $this->localSlot();

        $response = $this->actingAs($user)->post(route('activities.store'), [
            'title' => 'Correr al atardecer',
            'category_id' => $category->id,
            'location_name' => 'Cancún',
            'latitude' => 21.16,
            'longitude' => -86.85,
            'timezone' => $this->timezone,
            'start_at' => $start,
            'end_at' => $end,
        ]);

        $activity = Activity::query()->firstOrFail();

        $response->assertRedirect(route('activities.show', $activity));

        $this->assertDatabaseHas('activities', [
            'id' => $activity->id,
            'user_id' => $user->id,
            'title' => 'Correr al atardecer',
        ]);

        $this->assertDatabaseHas('activity_assessments', [
            'activity_id' => $activity->id,
        ]);
    }

    public function test_end_before_start_is_rejected(): void
    {
        $user = User::factory()->create();

        [$start] = $this->localSlot();

        $this->actingAs($user)
            ->from(route('activities.create'))
            ->post(route('activities.store'), [
                'title' => 'Inválida',
                'location_name' => 'Cancún',
                'latitude' => 21.16,
                'longitude' => -86.85,
                'timezone' => $this->timezone,
                'start_at' => $start,
                'end_at' => $start,
            ])
            ->assertSessionHasErrors('end_at');

        $this->assertDatabaseCount('activities', 0);
    }

    public function test_preview_returns_assessment_without_persisting_activity(): void
    {
        $user = User::factory()->create();

        [$start, $end] = $this->localSlot();

        $response = $this->actingAs($user)->postJson(route('activities.assess'), [
            'title' => 'Preview',
            'location_name' => 'Cancún',
            'latitude' => 21.16,
            'longitude' => -86.85,
            'timezone' => $this->timezone,
            'start_at' => $start,
            'end_at' => $end,
        ]);

        $response->assertOk()
            ->assertJsonStructure(['status', 'score', 'reasons', 'coverage']);

        $this->assertDatabaseCount('activities', 0);
    }

    public function test_index_only_lists_own_activities(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Activity::factory()->create(['user_id' => $user->id, 'title' => 'Mía']);
        Activity::factory()->create(['user_id' => $other->id, 'title' => 'Ajena']);

        $this->actingAs($user)
            ->get(route('activities.index'))
            ->assertOk()
            ->assertSee('Mía')
            ->assertDontSee('Ajena');
    }

    public function test_dashboard_renders_react_island(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-react-component="Dashboard"', false);
    }

    public function test_create_page_renders_react_island(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('activities.create'))
            ->assertOk()
            ->assertSee('data-react-component="ActivityCreate"', false);
    }

    public function test_edit_page_renders_local_times(): void
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->create([
            'user_id' => $user->id,
            'timezone' => $this->timezone,
        ]);

        $this->actingAs($user)
            ->get(route('activities.edit', $activity))
            ->assertOk()
            ->assertSee('data-react-component="ActivityForm"', false);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function localSlot(int $offsetHours = 0): array
    {
        $base = CarbonImmutable::now()->setTimezone($this->timezone)->startOfHour()->addHours($offsetHours);

        return [
            $base->format('Y-m-d\TH:i'),
            $base->addHour()->format('Y-m-d\TH:i'),
        ];
    }

    private function fakeWeather(): void
    {
        $base = CarbonImmutable::now()->setTimezone($this->timezone)->startOfHour();

        $times = [];
        for ($i = -2; $i < 6; $i++) {
            $times[] = $base->addHours($i)->format('Y-m-d\TH:i');
        }

        $count = count($times);
        $fill = fn (int|float $value) => array_fill(0, $count, $value);

        Http::fake([
            'api.open-meteo.com/v1/forecast*' => Http::response([
                'latitude' => 21.16,
                'longitude' => -86.85,
                'elevation' => 10,
                'timezone' => $this->timezone,
                'utc_offset_seconds' => -18000,
                'hourly' => [
                    'time' => $times,
                    'temperature_2m' => $fill(24),
                    'apparent_temperature' => $fill(24),
                    'relative_humidity_2m' => $fill(60),
                    'precipitation_probability' => $fill(0),
                    'precipitation' => $fill(0),
                    'wind_speed_10m' => $fill(6),
                    'wind_gusts_10m' => $fill(9),
                    'uv_index' => $fill(3),
                    'weather_code' => $fill(1),
                    'cloud_cover' => $fill(15),
                    'shortwave_radiation' => $fill(120),
                ],
            ], 200),
            'geocoding-api.open-meteo.com/*' => Http::response([
                'results' => [[
                    'name' => 'Cancún',
                    'admin1' => 'Quintana Roo',
                    'country' => 'México',
                    'latitude' => 21.16,
                    'longitude' => -86.85,
                    'timezone' => $this->timezone,
                ]],
            ], 200),
        ]);
    }
}
