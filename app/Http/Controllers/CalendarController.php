<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Support\ActivityPresenter;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function __invoke(Request $request): View
    {
        $events = $request->user()->activities()
            ->with(['category', 'latestAssessment'])
            ->get()
            ->map(fn (Activity $activity) => ActivityPresenter::calendarEvent($activity))
            ->values();

        return view('calendar', [
            'calendarUrl' => route('activities.calendar'),
            'createUrl' => route('activities.create'),
            'events' => $events,
        ]);
    }
}
