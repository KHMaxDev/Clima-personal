<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Support\ActivityPresenter;
use App\Support\AssessmentStatus;
use App\Support\LocationResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $activities = $user->activities()
            ->with(['category', 'latestAssessment'])
            ->orderBy('start_at')
            ->get();

        $now = CarbonImmutable::now();

        $upcoming = $activities
            ->filter(fn (Activity $activity) => $activity->end_at->isFuture())
            ->values();

        $dashboard = [
            'stats' => [
                'total' => $activities->count(),
                'upcoming' => $upcoming->count(),
                'attention' => $activities->filter(
                    fn (Activity $activity) => in_array($activity->latestAssessment?->status, [AssessmentStatus::HIGH_RISK, AssessmentStatus::UNFAVORABLE], true)
                )->count(),
            ],
            'statusCounts' => collect(AssessmentStatus::all())
                ->mapWithKeys(fn (string $label, string $status) => [
                    $status => $activities->filter(
                        fn (Activity $activity) => $activity->latestAssessment?->status === $status
                    )->count(),
                ])
                ->all(),
            'statusLabels' => AssessmentStatus::all(),
            'nextActivity' => $upcoming->first()
                ? ActivityPresenter::summary($upcoming->first())
                : null,
            'dayActivities' => $this->dayActivities($activities, $now),
            'recommendations' => $this->recommendations($upcoming),
            'notifications' => $this->notifications($upcoming),
            'events' => $activities
                ->map(fn (Activity $activity) => ActivityPresenter::calendarEvent($activity))
                ->values(),
            'weatherLocation' => LocationResolver::forUser($user),
        ];

        return view('dashboard', [
            'dashboard' => $dashboard,
            'weatherUrl' => route('weather.summary'),
            'locationsUrl' => route('locations.search'),
            'alternativesUrlTemplate' => route('activities.alternatives', ['activity' => '__ID__']),
        ]);
    }

    /**
     * @param  Collection<int, Activity>  $activities
     * @return array<int, array<string, mixed>>
     */
    private function dayActivities(Collection $activities, CarbonImmutable $now): array
    {
        return $activities
            ->filter(function (Activity $activity) use ($now): bool {
                return $activity->start_at
                    ->setTimezone($activity->timezone)
                    ->isSameDay($now->setTimezone($activity->timezone));
            })
            ->map(fn (Activity $activity) => ActivityPresenter::summary($activity))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Activity>  $upcoming
     * @return array<int, array<string, mixed>>
     */
    private function recommendations(Collection $upcoming): array
    {
        return $upcoming
            ->take(4)
            ->map(function (Activity $activity): ?array {
                $reasons = ActivityPresenter::reasons($activity);

                if ($reasons === []) {
                    return null;
                }

                $worst = collect($reasons)->sortByDesc('score_impact')->first();
                $status = $activity->latestAssessment?->status;

                return [
                    'id' => $activity->id,
                    'activityTitle' => $activity->title,
                    'url' => route('activities.show', $activity),
                    'message' => $worst['message'],
                    'severity' => $worst['severity'],
                    'status' => $status,
                    'statusLabel' => AssessmentStatus::label($status),
                    'color' => AssessmentStatus::color($status),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Activity>  $upcoming
     * @return array<int, array<string, mixed>>
     */
    private function notifications(Collection $upcoming): array
    {
        return $upcoming
            ->filter(fn (Activity $activity) => in_array($activity->latestAssessment?->status, [AssessmentStatus::HIGH_RISK, AssessmentStatus::UNFAVORABLE], true))
            ->map(fn (Activity $activity): array => [
                'id' => $activity->id,
                'title' => $activity->title,
                'url' => route('activities.show', $activity),
                'status' => $activity->latestAssessment?->status,
                'statusLabel' => AssessmentStatus::label($activity->latestAssessment?->status),
                'color' => AssessmentStatus::color($activity->latestAssessment?->status),
                'when' => $activity->start_at->setTimezone($activity->timezone)->isoFormat('ddd D MMM, HH:mm'),
            ])
            ->values()
            ->all();
    }
}
