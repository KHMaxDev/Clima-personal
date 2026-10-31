<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'preferred_start_time',
    'preferred_end_time',
    'notifications_enabled',
    'email_notifications_enabled',
    'max_wind_kmh',
    'avoid_rain',
    'uv_protection',
    'notes',
])]
class UserPreference extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'notifications_enabled' => 'boolean',
            'email_notifications_enabled' => 'boolean',
            'max_wind_kmh' => 'integer',
            'avoid_rain' => 'boolean',
            'uv_protection' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
