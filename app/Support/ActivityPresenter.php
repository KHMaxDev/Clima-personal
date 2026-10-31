<?php

namespace App\Support;

use App\Models\Activity;

class ActivityPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function summary(Activity $activity): array
    {
        $status = $activity->latestAssessment?->status;

        return [
            'id' => $activity->id,
            'title' => $activity->title,
            'url' => route('activities.show', $activity),
            'start' => $activity->start_at->toIso8601String(),
            'end' => $activity->end_at->toIso8601String(),
            'startLocal' => $activity->start_at->setTimezone($activity->timezone)->format('H:i'),
            'endLocal' => $activity->end_at->setTimezone($activity->timezone)->format('H:i'),
            'timezone' => $activity->timezone,
            'location' => $activity->location_name,
            'category' => $activity->category?->name,
            'categorySlug' => $activity->category?->slug,
            'status' => $status,
            'statusLabel' => AssessmentStatus::label($status),
            'color' => AssessmentStatus::color($status),
            'icon' => AssessmentStatus::icon($status),
            'score' => $activity->latestAssessment?->score,
            'coverage' => $activity->latestAssessment?->coverage,
            'reasons' => self::reasons($activity),
        ];
    }

    /**
     * @return array<int, array{code: ?string, severity: string, message: string, score_impact: int, missing: bool}>
     */
    public static function reasons(Activity $activity): array
    {
        $reasons = $activity->latestAssessment?->reasons;

        if (! is_array($reasons)) {
            return [];
        }

        return array_map(fn (array $reason): array => [
            'code' => $reason['code'] ?? null,
            'severity' => $reason['severity'] ?? 'info',
            'message' => $reason['message'] ?? '',
            'score_impact' => (int) ($reason['score_impact'] ?? 0),
            'missing' => (bool) ($reason['missing'] ?? false),
        ], $reasons);
    }

    /**
     * @return array<string, mixed>
     */
    public static function calendarEvent(Activity $activity): array
    {
        $status = $activity->latestAssessment?->status;
        $color = AssessmentStatus::color($status);

        return [
            'id' => (string) $activity->id,
            'title' => $activity->title,
            'start' => $activity->start_at->toIso8601String(),
            'end' => $activity->end_at->toIso8601String(),
            'url' => route('activities.show', $activity),
            'backgroundColor' => $color,
            'borderColor' => $color,
            'extendedProps' => [
                'status' => $status,
                'statusLabel' => AssessmentStatus::label($status),
                'category' => $activity->category?->name,
                'location' => $activity->location_name,
                'score' => $activity->latestAssessment?->score,
            ],
        ];
    }
}
