<?php

namespace App\Http\Controllers;

use App\Support\LocationResolver;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ForecastController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('forecast', [
            'forecast' => [
                'location' => LocationResolver::forUser($request->user()),
                'weatherUrl' => route('weather.summary'),
                'locationsUrl' => route('locations.search'),
            ],
        ]);
    }
}
