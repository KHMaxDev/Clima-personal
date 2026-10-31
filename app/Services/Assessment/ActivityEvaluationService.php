<?php

namespace App\Services\Assessment;

use App\Models\Activity;
use App\Models\ActivityAssessment;
use App\Models\ActivityRule;
use App\Models\WeatherSnapshot;
use App\Services\Weather\WeatherAggregator;
use App\Services\Weather\WeatherSnapshotStore;
use Carbon\CarbonImmutable;

class ActivityEvaluationService
{
    public function __construct(
        private WeatherSnapshotStore $snapshots,
        private WeatherAggregator $aggregator,
        private ActivityAssessmentService $assessor,
    ) {}

    public function evaluate(Activity $activity): ActivityAssessment
    {
        $snapshot = $this->snapshots->forActivity($activity);

        $result = $this->assessAgainst($snapshot, $activity);

        return ActivityAssessment::create([
            'activity_id' => $activity->id,
            'weather_snapshot_id' => $snapshot->id,
            'score' => $result->score,
            'status' => $result->status,
            'reasons' => array_map(fn (Finding $finding) => $finding->toArray(), $result->reasons),
            'coverage' => $result->coverage,
            'rule_version' => $result->ruleVersion,
            'evaluated_at' => CarbonImmutable::now(),
        ]);
    }

    public function assess(Activity $activity): AssessmentResult
    {
        return $this->assessAgainst($this->snapshots->forActivity($activity), $activity);
    }

    private function assessAgainst(WeatherSnapshot $snapshot, Activity $activity): AssessmentResult
    {
        $hourly = $snapshot->payload['hourly'] ?? [];

        $aggregated = $this->aggregator->aggregate(
            $hourly,
            $activity->start_at,
            $activity->end_at,
        );

        $rules = ActivityRule::query()
            ->active()
            ->forCategory($activity->category_id)
            ->get();

        return $this->assessor->evaluate($rules, $aggregated);
    }
}
