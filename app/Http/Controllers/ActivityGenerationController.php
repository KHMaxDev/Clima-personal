<?php

namespace App\Http\Controllers;

use App\Exceptions\AiRateLimitedException;
use App\Exceptions\AiUnavailableException;
use App\Exceptions\WeatherUnavailableException;
use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Services\Ai\ActivityGenerationService;
use App\Services\Assessment\ActivityEvaluationService;
use App\Support\ActivityScheduleExpander;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ActivityGenerationController extends Controller
{
    public function __construct(
        private ActivityGenerationService $generator,
        private ActivityScheduleExpander $expander,
        private ActivityEvaluationService $evaluator,
    ) {}

    public function interpret(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'description' => ['required', 'string', 'min:3', 'max:4000'],
            'timezone' => ['required', 'string', 'timezone'],
            'answers' => ['nullable', 'array', 'max:10'],
            'answers.*.question' => ['required', 'string', 'max:300'],
            'answers.*.answer' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $result = $this->generator->interpret(
                (string) $validated['description'],
                $this->answers($validated['answers'] ?? []),
                (string) $validated['timezone'],
                $this->categoryNames(),
                (int) $request->user()->id,
            );
        } catch (AiRateLimitedException $exception) {
            return response()->json(['message' => $exception->getMessage()], 429);
        } catch (AiUnavailableException $exception) {
            return response()->json(['message' => $exception->getMessage()], 503);
        }

        return response()->json($result);
    }

    public function store(Request $request): JsonResponse
    {
        $max = (int) config('climaquest.ai.generation.max_activities', 12);

        $validated = $request->validate([
            'timezone' => ['required', 'string', 'timezone'],
            'location_name' => ['required', 'string', 'max:160'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'activities' => ['required', 'array', 'min:1', 'max:'.$max],
            'activities.*.title' => ['required', 'string', 'max:160'],
            'activities.*.description' => ['nullable', 'string', 'max:2000'],
            'activities.*.category' => ['nullable', 'string', 'max:120'],
            'activities.*.days' => ['nullable', 'array'],
            'activities.*.days.*' => ['integer', 'between:1,7'],
            'activities.*.start_time' => ['required', 'regex:/^\d{1,2}:\d{2}$/'],
            'activities.*.end_time' => ['required', 'regex:/^\d{1,2}:\d{2}$/'],
            'activities.*.location_hint' => ['nullable', 'string', 'max:160'],
        ]);

        $timezone = (string) $validated['timezone'];
        $categories = $this->categoryMap();
        $horizon = (int) config('climaquest.ai.generation.horizon_days', 7);

        $created = [];
        $weatherWarnings = 0;

        foreach ($validated['activities'] as $draft) {
            $occurrences = $this->expander->expand(
                $draft['days'] ?? [],
                (string) $draft['start_time'],
                (string) $draft['end_time'],
                $timezone,
                null,
                $horizon,
            );

            $categoryId = $this->resolveCategoryId($draft['category'] ?? null, $categories);

            foreach ($occurrences as $occurrence) {
                $activity = $request->user()->activities()->create([
                    'category_id' => $categoryId,
                    'title' => $draft['title'],
                    'description' => $draft['description'] ?? null,
                    'start_at' => $occurrence['start'],
                    'end_at' => $occurrence['end'],
                    'timezone' => $timezone,
                    'location_name' => $validated['location_name'],
                    'latitude' => $validated['latitude'],
                    'longitude' => $validated['longitude'],
                    'status' => 'planned',
                    'preferences' => [
                        'generated_by' => 'ai',
                        'recurrence' => [
                            'days' => array_values($draft['days'] ?? []),
                            'start_time' => $draft['start_time'],
                            'end_time' => $draft['end_time'],
                        ],
                        'location_hint' => $draft['location_hint'] ?? null,
                    ],
                ]);

                if (! $this->evaluate($activity)) {
                    $weatherWarnings++;
                }

                $created[] = [
                    'id' => $activity->id,
                    'title' => $activity->title,
                    'start' => $activity->start_at->setTimezone($timezone)->toIso8601String(),
                    'url' => route('activities.show', $activity),
                ];
            }
        }

        return response()->json([
            'message' => count($created).' actividad(es) creada(s) y evaluada(s).',
            'created' => count($created),
            'activities' => $created,
            'weather_warning' => $weatherWarnings > 0,
            'redirect' => route('activities.index'),
        ]);
    }

    private function evaluate(Activity $activity): bool
    {
        try {
            $this->evaluator->evaluate($activity);

            return true;
        } catch (WeatherUnavailableException) {
            return false;
        }
    }

    /**
     * @param  array<int, mixed>  $answers
     * @return array<int, array{question: string, answer: string}>
     */
    private function answers(array $answers): array
    {
        $normalized = [];

        foreach ($answers as $answer) {
            if (! is_array($answer)) {
                continue;
            }

            $normalized[] = [
                'question' => (string) ($answer['question'] ?? ''),
                'answer' => (string) ($answer['answer'] ?? ''),
            ];
        }

        return $normalized;
    }

    /**
     * @return string[]
     */
    private function categoryNames(): array
    {
        return ActivityCategory::query()
            ->where('active', true)
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function categoryMap(): array
    {
        $map = [];

        ActivityCategory::query()
            ->where('active', true)
            ->get(['id', 'name', 'slug'])
            ->each(function (ActivityCategory $category) use (&$map) {
                $map[mb_strtolower($category->name)] = $category->id;
                $map[$category->slug] = $category->id;
            });

        return $map;
    }

    /**
     * @param  array<string, int>  $categories
     */
    private function resolveCategoryId(?string $category, array $categories): ?int
    {
        if ($category === null || trim($category) === '') {
            return null;
        }

        $name = mb_strtolower(trim($category));

        return $categories[$name]
            ?? $categories[Str::slug($category, '_')]
            ?? null;
    }
}
