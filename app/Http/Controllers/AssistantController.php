<?php

namespace App\Http\Controllers;

use App\Exceptions\AiRateLimitedException;
use App\Exceptions\WeatherUnavailableException;
use App\Models\Activity;
use App\Services\Ai\AssistantService;
use App\Services\Ai\OpenRouterService;
use App\Support\ActivityPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AssistantController extends Controller
{
    public function __construct(
        private AssistantService $assistant,
        private OpenRouterService $openRouter,
    ) {}

    public function show(Request $request): View
    {
        $activities = $request->user()->activities()
            ->with(['category', 'latestAssessment'])
            ->where('end_at', '>=', now())
            ->orderBy('start_at')
            ->get()
            ->map(fn (Activity $activity) => ActivityPresenter::summary($activity))
            ->values();

        return view('assistant', [
            'assistant' => [
                'activities' => $activities,
                'generateUrlTemplate' => route('activities.assistant', ['activity' => '__ID__']),
                'aiEnabled' => $this->openRouter->enabled(),
                'model' => $this->openRouter->model(),
            ],
        ]);
    }

    public function generate(Activity $activity): JsonResponse
    {
        $this->authorize('view', $activity);

        try {
            $result = $this->assistant->forActivity($activity);
        } catch (AiRateLimitedException $exception) {
            return response()->json(['message' => $exception->getMessage()], 429);
        } catch (WeatherUnavailableException $exception) {
            return response()->json(['message' => $exception->getMessage()], 503);
        }

        return response()->json($result);
    }
}
