<?php

namespace App\Http\Controllers;

use App\Models\ActivityAssessment;
use App\Support\AssessmentStatus;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HistoryController extends Controller
{
    public function __invoke(Request $request): View
    {
        $assessments = ActivityAssessment::query()
            ->whereHas('activity', fn ($query) => $query->where('user_id', $request->user()->id))
            ->with(['activity.category'])
            ->latest('evaluated_at')
            ->paginate(20);

        return view('history', [
            'assessments' => $assessments,
            'statusLabels' => AssessmentStatus::all(),
            'statusColors' => collect(AssessmentStatus::all())
                ->mapWithKeys(fn (string $label, string $status) => [$status => AssessmentStatus::color($status)])
                ->all(),
        ]);
    }
}
