<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\AiRecommendation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ActivityCategorySeeder;
use Database\Seeders\ActivityRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AssistantTest extends TestCase
{
    use RefreshDatabase;

    private string $timezone = 'America/Cancun';

    private ?string $assistantContent = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ActivityCategorySeeder::class);
        $this->seed(ActivityRuleSeeder::class);

        config(['climaquest.ai.api_key' => null]);

        $this->fakeAll();

        $this->travelTo(CarbonImmutable::parse('2026-07-01 06:00', $this->timezone));
    }

    public function test_assistant_page_renders_island(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('assistant'))
            ->assertOk()
            ->assertSee('data-react-component="Assistant"', false);
    }

    public function test_generate_returns_ai_response(): void
    {
        config(['climaquest.ai.api_key' => 'test-key']);

        $user = User::factory()->create();
        $activity = $this->activity($user);

        $response = $this->actingAs($user)
            ->postJson(route('activities.assistant', $activity));

        $response->assertOk()
            ->assertJsonPath('generated_by', 'ai')
            ->assertJsonPath('summary', 'Resumen generado por IA.')
            ->assertJsonStructure([
                'model',
                'generated_by',
                'cached',
                'summary',
                'recommendations',
                'precautions',
                'sources',
                'assessment' => ['status', 'status_label', 'score', 'coverage'],
            ]);

        $this->assertDatabaseHas('ai_recommendations', [
            'activity_id' => $activity->id,
            'model_id' => 'openrouter/free',
        ]);
    }

    public function test_generate_falls_back_to_deterministic_without_key(): void
    {
        $user = User::factory()->create();
        $activity = $this->activity($user);

        $response = $this->actingAs($user)
            ->postJson(route('activities.assistant', $activity));

        $response->assertOk()
            ->assertJsonPath('generated_by', 'deterministic')
            ->assertJsonPath('model', 'deterministic');

        $this->assertDatabaseHas('ai_recommendations', [
            'activity_id' => $activity->id,
            'model_id' => 'deterministic',
        ]);
    }

    public function test_generate_falls_back_when_model_output_is_invalid(): void
    {
        config(['climaquest.ai.api_key' => 'test-key']);

        $this->assistantContent = 'esto no es un objeto JSON válido';

        $user = User::factory()->create();
        $activity = $this->activity($user);

        $this->actingAs($user)
            ->postJson(route('activities.assistant', $activity))
            ->assertOk()
            ->assertJsonPath('generated_by', 'deterministic');
    }

    public function test_generate_forbids_other_users(): void
    {
        config(['climaquest.ai.api_key' => 'test-key']);

        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $activity = $this->activity($owner);

        $this->actingAs($intruder)
            ->postJson(route('activities.assistant', $activity))
            ->assertForbidden();
    }

    public function test_daily_limit_returns_429(): void
    {
        config(['climaquest.ai.api_key' => 'test-key']);
        config(['climaquest.ai.daily_limit_per_user' => 1]);

        $user = User::factory()->create();
        $activity = $this->activity($user);

        AiRecommendation::create([
            'activity_id' => $activity->id,
            'user_id' => $user->id,
            'model_id' => 'openrouter/free',
            'context_version' => 'seeded',
            'response' => ['generated_by' => 'ai', 'summary' => 'previa'],
        ]);

        $this->actingAs($user)
            ->postJson(route('activities.assistant', $activity))
            ->assertStatus(429);
    }

    public function test_reuses_cached_recommendation(): void
    {
        config(['climaquest.ai.api_key' => 'test-key']);

        $user = User::factory()->create();
        $activity = $this->activity($user);

        $this->actingAs($user)
            ->postJson(route('activities.assistant', $activity))
            ->assertOk()
            ->assertJsonPath('cached', false);

        $this->actingAs($user)
            ->postJson(route('activities.assistant', $activity))
            ->assertOk()
            ->assertJsonPath('cached', true);

        $this->assertDatabaseCount('ai_recommendations', 1);
    }

    private function activity(User $user): Activity
    {
        $running = ActivityCategory::where('slug', 'running')->firstOrFail();

        return Activity::factory()->create([
            'user_id' => $user->id,
            'category_id' => $running->id,
            'start_at' => CarbonImmutable::parse('2026-07-01 14:00', $this->timezone),
            'end_at' => CarbonImmutable::parse('2026-07-01 15:00', $this->timezone),
            'timezone' => $this->timezone,
            'latitude' => 21.16,
            'longitude' => -86.85,
            'location_name' => 'Cancún',
        ]);
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
                            'content' => $this->assistantContent ?? json_encode([
                                'summary' => 'Resumen generado por IA.',
                                'recommendations' => ['Hidrátate bien.'],
                                'precautions' => ['Evita la exposición prolongada al sol.'],
                                'best_time' => '07:00-08:00',
                            ]),
                        ],
                    ]],
                    'usage' => ['total_tokens' => 42],
                ], 200);
            }

            if (str_contains($url, 'geocoding-api')) {
                return Http::response(['results' => [[
                    'name' => 'Cancún',
                    'admin1' => 'Quintana Roo',
                    'country' => 'México',
                    'latitude' => 21.16,
                    'longitude' => -86.85,
                    'timezone' => $this->timezone,
                ]]], 200);
            }

            return Http::response(json_decode($this->fixture(), true), 200);
        });
    }

    private function fixture(): string
    {
        return file_get_contents(base_path('tests/Fixtures/open-meteo/forecast_cancun.json'));
    }
}
