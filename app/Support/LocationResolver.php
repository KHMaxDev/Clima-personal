<?php

namespace App\Support;

use App\Models\User;

class LocationResolver
{
    /**
     * Ubicación por defecto del panel: la próxima actividad (o la más reciente).
     *
     * @return array{name: ?string, latitude: float, longitude: float, timezone: string}|null
     */
    public static function forUser(User $user): ?array
    {
        $activity = $user->activities()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('end_at', '>=', now())
            ->orderBy('start_at')
            ->first()
            ?? $user->activities()
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->orderByDesc('start_at')
                ->first();

        if ($activity === null) {
            return null;
        }

        return [
            'name' => $activity->location_name,
            'latitude' => (float) $activity->latitude,
            'longitude' => (float) $activity->longitude,
            'timezone' => $activity->timezone,
        ];
    }
}
