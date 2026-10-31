<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'provider',
    'latitude',
    'longitude',
    'forecast_for',
    'forecast_days',
    'fetched_at',
    'expires_at',
    'payload',
])]
class WeatherSnapshot extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'forecast_for' => 'date',
            'forecast_days' => 'integer',
            'fetched_at' => 'datetime',
            'expires_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(ActivityAssessment::class, 'weather_snapshot_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
