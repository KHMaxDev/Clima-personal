<?php

namespace App\Services\Assessment;

use App\Models\Activity;
use App\Models\ActivityRule;
use App\Services\Weather\WeatherAggregator;
use App\Services\Weather\WeatherSnapshotStore;
use App\Support\AssessmentStatus;
use Carbon\CarbonImmutable;

class ActivityAlternativeService
{
    public function __construct(
        private WeatherSnapshotStore $snapshots,
        private WeatherAggregator $aggregator,
        private ActivityAssessmentService $assessor,
        private array $config = [],
    ) {}

    /**
     * Busca horarios alternativos sobre la serie horaria ya en caché (Anexo v1.1 §B.7).
     *
     * @return array<int, array<string, mixed>>
     */
    public function for(Activity $activity, int $limit = 3): array
    {
        $snapshot = $this->snapshots->forActivity($activity);
        $hourly = $snapshot->payload['hourly'] ?? [];

        $rules = ActivityRule::query()
            ->active()
            ->forCategory($activity->category_id)
            ->get();

        $duration = max(1, $activity->durationMinutes());
        $step = (int) ($this->config['alternative_step_minutes'] ?? 30);

        $originalStart = CarbonImmutable::instance($activity->start_at);
        $dayStart = $originalStart->setTimezone($activity->timezone)->startOfDay();

        $candidates = [];
        $maxOffset = 24 * 60 - $duration;

        for ($offset = 0; $offset <= $maxOffset; $offset += $step) {
            $start = $dayStart->addMinutes($offset);
            $end = $start->addMinutes($duration);

            $aggregated = $this->aggregator->aggregate($hourly, $start, $end);
            $result = $this->assessor->evaluate($rules, $aggregated);

            if (in_array($result->status, [AssessmentStatus::HIGH_RISK, AssessmentStatus::INSUFFICIENT], true)) {
                continue;
            }

            $candidates[] = [
                'start' => $start,
                'end' => $end,
                'result' => $result,
            ];
        }

        usort($candidates, function (array $a, array $b) use ($originalStart): int {
            $score = ($b['result']->score ?? 0) <=> ($a['result']->score ?? 0);

            if ($score !== 0) {
                return $score;
            }

            return abs((int) $a['start']->diffInMinutes($originalStart)) <=> abs((int) $b['start']->diffInMinutes($originalStart));
        });

        return array_map(fn (array $candidate): array => [
            'start' => $candidate['start']->toIso8601String(),
            'end' => $candidate['end']->toIso8601String(),
            'start_local' => $candidate['start']->format('H:i'),
            'end_local' => $candidate['end']->format('H:i'),
            'score' => $candidate['result']->score,
            'status' => $candidate['result']->status,
            'status_label' => AssessmentStatus::label($candidate['result']->status),
            'coverage' => $candidate['result']->coverage,
            'reasons' => array_map(fn (Finding $finding) => $finding->toArray(), $candidate['result']->reasons),
        ], array_slice($candidates, 0, $limit));
    }
}
