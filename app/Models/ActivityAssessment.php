<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'activity_id',
    'weather_snapshot_id',
    'score',
    'status',
    'reasons',
    'coverage',
    'rule_version',
    'evaluated_at',
])]
class ActivityAssessment extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'reasons' => 'array',
            'coverage' => 'decimal:3',
            'evaluated_at' => 'datetime',
        ];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function weatherSnapshot(): BelongsTo
    {
        return $this->belongsTo(WeatherSnapshot::class, 'weather_snapshot_id');
    }

    public function aiRecommendations(): HasMany
    {
        return $this->hasMany(AiRecommendation::class, 'assessment_id');
    }
}
