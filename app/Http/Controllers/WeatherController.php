<?php

namespace App\Http\Controllers;

use App\Exceptions\WeatherUnavailableException;
use App\Services\Weather\WeatherSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WeatherController extends Controller
{
    public function __invoke(Request $request, WeatherSummaryService $service): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'timezone' => ['required', 'string', 'timezone'],
        ]);

        try {
            $summary = $service->summary(
                (float) $validated['latitude'],
                (float) $validated['longitude'],
                (string) $validated['timezone'],
            );
        } catch (WeatherUnavailableException $exception) {
            return response()->json(['message' => $exception->getMessage()], 503);
        }

        return response()->json($summary);
    }
}
