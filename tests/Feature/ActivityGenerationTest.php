<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ActivityCategorySeeder;
use Database\Seeders\ActivityRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ActivityGenerationTest extends TestCase
{
    use RefreshDatabase;

    private string $timezone = 'America/Cancun';

    private ?string $modelContent = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ActivityCategorySeeder::class);
        $this->seed(ActivityRuleSeeder::class);

        config(['climaquest.ai.api_key' => 'test-key']);

        $this->fakeAll();

        $this->travelTo(CarbonImmutable::parse('2026-07-01 06:00', $this->timezone));
    }

    public function test_interpret_returns_normalized_payload(): void
    {
        $this->modelContent = json_encode([
            'context_summary' => 'Escuela de lunes a viernes y gimnasio por la noche.',
            'done' => true,
            'questions' => [],
            'activities' => [[
                'title' => 'Escuela',
                'description' => 'Clases.',
                'category' => 'Otras actividades',
                'days' => [1, 2, 3, 4, 5, 9],
                'start_time' => '15:00',
                'end_time' => '22:00',
                'location_hint' => null,
                'assumed' => false,
            ]],
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->postJson(route('activities.generate.interpret'), [
                'description' => 'Voy a la escuela de lunes a viernes de 3 a 10.',
                'timezone' => $this->timezone,
            ]);

        $response->assertOk()
            ->assertJsonPath('context_summary', 'Escuela de lunes a viernes y gimnasio por la noche.')
            ->assertJsonPath('done', true)
            ->assertJsonPath('activities.0.title', 'Escuela')
            ->assertJsonPath('activities.0.start_time', '15:00')
            ->assertJsonPath('activities.0.end_time', '22:00')
            ->assertJsonCount(5, 'activities.0.days');
    }

    public function test_interpret_requires_a_description(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('activities.generate.interpret'), [
                'timezone' => $this->timezone,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('description');
    }

    public function test_interpret_returns_503_without_api_key(): void
    {
        config(['climaquest.ai.api_key' => null]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('activities.generate.interpret'), [
                'description' => 'Voy a correr por la mañana.',
                'timezone' => $this->timezone,
            ])
            ->assertStatus(503);
    }

    public function test_store_expands_recurrence_into_one_activity_per_day(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson(route('activities.generate.store'), [
                'timezone' => $this->timezone,
                'location_name' => 'Cancún',
                'latitude' => 21.16,
                'longitude' => -86.85,
                'activities' => [[
                    'title' => 'Escuela',
                    'category' => 'Otras actividades',
                    'days' => [1, 2, 3, 4, 5],
                    'start_time' => '15:00',
                    'end_time' => '20:00',
                ]],
            ]);

        $response->assertOk()
            ->assertJsonPath('created', 5)
            ->assertJsonPath('redirect', route('activities.index'));

        $this->assertSame(5, Activity::where('user_id', $user->id)->count());
        $this->assertDatabaseHas('activities', [
            'user_id' => $user->id,
            'title' => 'Escuela',
        ]);

        $activity = Activity::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('ai', $activity->preferences['generated_by']);
        $this->assertSame('Cancún', $activity->location_name);
    }

    public function test_store_resolves_category_by_name(): void
    {
        $user = User::factory()->create();
        $running = ActivityCategory::where('slug', 'running')->firstOrFail();

        $this->actingAs($user)
            ->postJson(route('activities.generate.store'), [
                'timezone' => $this->timezone,
                'location_name' => 'Cancún',
                'latitude' => 21.16,
                'longitude' => -86.85,
                'activities' => [[
                    'title' => 'Correr',
                    'category' => 'Correr',
                    'days' => [],
                    'start_time' => '07:00',
                    'end_time' => '08:00',
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('created', 1);

        $this->assertDatabaseHas('activities', [
            'user_id' => $user->id,
            'title' => 'Correr',
            'category_id' => $running->id,
        ]);
    }

    public function test_store_validates_payload(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('activities.generate.store'), [
                'activities' => [],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['timezone', 'location_name', 'latitude', 'longitude', 'activities']);
    }

    public function test_generation_requires_authentication(): void
    {
        $this->postJson(route('activities.generate.interpret'), [
            'description' => 'test',
            'timezone' => $this->timezone,
        ])->assertUnauthorized();
    }

    private function fakeAll(): void
    {
        Http::fake(function (Request $request) {
            $url = $request->url();

            if (str_contains($url, 'openrouter.ai')) {
                return Http::response([
                    'model' => 'openrouter/free',
                    'choices' => [[
                        'message' => [
                            'content' => $this->modelContent ?? '{}',
                        ],
                    ]],
                    'usage' => ['total_tokens' => 42],
                ], 200);
            }

            return Http::response($this->fixture(), 200);
        });
    }

    private function fixture(): string
    {
        return file_get_contents(base_path('tests/Fixtures/open-meteo/forecast_cancun.json'));
    }
}
