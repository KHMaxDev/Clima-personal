<?php

namespace App\Http\Controllers;

use App\Exceptions\WeatherUnavailableException;
use App\Http\Requests\ActivityRequest;
use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Services\Assessment\ActivityAlternativeService;
use App\Services\Assessment\ActivityEvaluationService;
use App\Support\ActivityPresenter;
use App\Support\AssessmentStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityController extends Controller
{
    public function __construct(
        private ActivityEvaluationService $evaluator,
        private ActivityAlternativeService $alternatives,
    ) {}

    public function index(Request $request): View
    {
        $activities = $request->user()->activities()
            ->with(['category', 'latestAssessment'])
            ->orderByDesc('start_at')
            ->paginate(12);

        return view('activities.index', [
            'activities' => $activities,
            'statusLabels' => AssessmentStatus::all(),
        ]);
    }

    public function create(Request $request): View
    {
        $categories = $this->categories();

        return view('activities.create', [
            'categories' => $categories,
            'generator' => [
                'interpretUrl' => route('activities.generate.interpret'),
                'storeUrl' => route('activities.generate.store'),
                'locationsUrl' => route('locations.search'),
                'categories' => array_map(fn (ActivityCategory $category) => $category->name, $categories),
                'defaultTimezone' => $request->user()->timezone ?: (string) config('app.timezone'),
                'aiEnabled' => filled(config('climaquest.ai.api_key')),
            ],
        ]);
    }

    public function store(ActivityRequest $request): RedirectResponse|JsonResponse
    {
        $activity = $request->user()->activities()->create($request->activityData());

        $warning = $this->evaluate($activity);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Actividad creada y evaluada.',
                'redirect' => route('activities.show', $activity),
                'warning' => $warning,
            ]);
        }

        return redirect()
            ->route('activities.show', $activity)
            ->with('status', 'Actividad creada y evaluada.');
    }

    public function show(Activity $activity): View
    {
        $this->authorize('view', $activity);

        $activity->load(['category', 'latestAssessment']);

        return view('activities.show', [
            'activity' => $activity,
            'statusLabels' => AssessmentStatus::all(),
        ]);
    }

    public function edit(Activity $activity): View
    {
        $this->authorize('update', $activity);

        return view('activities.edit', [
            'activity' => $activity,
            'categories' => $this->categories(),
        ]);
    }

    public function update(ActivityRequest $request, Activity $activity): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $activity);

        $activity->update($request->activityData());

        $warning = $this->evaluate($activity);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Actividad actualizada y reevaluada.',
                'redirect' => route('activities.show', $activity),
                'warning' => $warning,
            ]);
        }

        return redirect()
            ->route('activities.show', $activity)
            ->with('status', 'Actividad actualizada y reevaluada.');
    }

    public function destroy(Activity $activity): RedirectResponse
    {
        $this->authorize('delete', $activity);

        $activity->delete();

        return redirect()
            ->route('activities.index')
            ->with('status', 'Actividad eliminada.');
    }

    public function assess(ActivityRequest $request): JsonResponse
    {
        $activity = new Activity($request->activityData());
        $activity->user_id = $request->user()->id;

        try {
            $result = $this->evaluator->assess($activity);
        } catch (WeatherUnavailableException $exception) {
            return response()->json(['message' => $exception->getMessage()], 503);
        }

        return response()->json($result->toArray());
    }

    public function calendar(Request $request): JsonResponse
    {
        $activities = $request->user()->activities()
            ->with(['category', 'latestAssessment'])
            ->get();

        return response()->json([
            'events' => $activities
                ->map(fn (Activity $activity) => ActivityPresenter::calendarEvent($activity))
                ->values(),
        ]);
    }

    public function alternatives(Activity $activity): JsonResponse
    {
        $this->authorize('view', $activity);

        try {
            $alternatives = $this->alternatives->for(
                $activity,
                (int) config('climaquest.assessment.alternatives_limit', 3),
            );
        } catch (WeatherUnavailableException $exception) {
            return response()->json(['message' => $exception->getMessage()], 503);
        }

        return response()->json([
            'activity_id' => $activity->id,
            'found' => count($alternatives) > 0,
            'alternatives' => $alternatives,
        ]);
    }

    private function evaluate(Activity $activity): ?string
    {
        try {
            $this->evaluator->evaluate($activity);

            return null;
        } catch (WeatherUnavailableException) {
            session()->flash('warning', 'La actividad se guardó, pero no se pudo obtener el pronóstico para evaluarla.');

            return 'No se pudo obtener el pronóstico para evaluar la actividad.';
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function categories(): array
    {
        return ActivityCategory::query()
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->all();
    }
}
