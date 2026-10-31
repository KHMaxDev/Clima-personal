<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Services\Assessment\ActivityAssessmentService;
use App\Services\Assessment\ActivityEvaluationService;
use Carbon\CarbonImmutable;
use Database\Seeders\ActivityCategorySeeder;
use Database\Seeders\ActivityRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ActivityEvaluationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ActivityCategorySeeder::class);
        $this->seed(ActivityRuleSeeder::class);

        Http::fake(['api.open-meteo.com/*' => Http::response($this->fixture(), 200)]);

        $this->travelTo(CarbonImmutable::parse('2026-07-01 06:00', 'America/Cancun'));
    }

    public function test_evaluates_activity_as_favorable(): void
    {
        $activity = $this->activity(
            CarbonImmutable::parse('2026-07-01 14:00', 'America/Cancun'),
            CarbonImmutable::parse('2026-07-01 15:00', 'America/Cancun'),
        );

        $assessment = app(ActivityEvaluationService::class)->evaluate($activity);

        $this->assertSame(ActivityAssessmentService::FAVORABLE, $assessment->status);
        $this->assertSame(100, $assessment->score);
        $this->assertSame(1.0, (float) $assessment->coverage);
        $this->assertDatabaseHas('activity_assessments', [
            'activity_id' => $activity->id,
            'status' => 'favorable',
        ]);
    }

    public function test_evaluates_activity_as_high_risk_in_afternoon(): void
    {
        $activity = $this->activity(
            CarbonImmutable::parse('2026-07-01 16:00', 'America/Cancun'),
            CarbonImmutable::parse('2026-07-01 17:00', 'America/Cancun'),
        );

        $assessment = app(ActivityEvaluationService::class)->evaluate($activity);

        $this->assertSame(ActivityAssessmentService::HIGH_RISK, $assessment->status);
        $this->assertNull($assessment->score);

        $codes = array_column($assessment->reasons, 'code');
        $this->assertContains('heat_extreme', $codes);
    }

    public function test_reuses_cached_snapshot_for_same_location_and_date(): void
    {
        $service = app(ActivityEvaluationService::class);

        $first = $this->activity(
            CarbonImmutable::parse('2026-07-01 14:00', 'America/Cancun'),
            CarbonImmutable::parse('2026-07-01 15:00', 'America/Cancun'),
        );

        $second = $this->activity(
            CarbonImmutable::parse('2026-07-01 09:00', 'America/Cancun'),
            CarbonImmutable::parse('2026-07-01 10:00', 'America/Cancun'),
        );

        $service->evaluate($first);
        $service->evaluate($second);

        Http::assertSentCount(1);
        $this->assertSame(1, $first->assessments()->count());
        $this->assertSame(1, $second->assessments()->count());
    }

    private function activity(CarbonImmutable $start, CarbonImmutable $end): Activity
    {
        $running = ActivityCategory::where('slug', 'running')->firstOrFail();

        return Activity::factory()->create([
            'category_id' => $running->id,
            'start_at' => $start,
            'end_at' => $end,
            'timezone' => 'America/Cancun',
            'latitude' => 21.16,
            'longitude' => -86.85,
        ]);
    }

    private function fixture(): string
    {
        return file_get_contents(base_path('tests/Fixtures/open-meteo/forecast_cancun.json'));
    }
}
