<?php

namespace App\Http\Controllers;

use App\Exceptions\WeatherUnavailableException;
use App\Services\Weather\OpenMeteoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function __invoke(Request $request, OpenMeteoService $openMeteo): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:120'],
        ]);

        try {
            $results = $openMeteo->geocode($validated['q']);
        } catch (WeatherUnavailableException $exception) {
            return response()->json(['message' => $exception->getMessage()], 503);
        }

        return response()->json(['results' => $results]);
    }
}
