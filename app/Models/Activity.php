<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'user_id',
    'category_id',
    'title',
    'description',
    'start_at',
    'end_at',
    'timezone',
    'location_name',
    'latitude',
    'longitude',
    'status',
    'preferences',
])]
class Activity extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'preferences' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ActivityCategory::class, 'category_id');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(ActivityAssessment::class);
    }

    public function latestAssessment(): HasOne
    {
        return $this->hasOne(ActivityAssessment::class)->latestOfMany('evaluated_at');
    }

    public function aiRecommendations(): HasMany
    {
        return $this->hasMany(AiRecommendation::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function durationMinutes(): int
    {
        return (int) $this->start_at->diffInMinutes($this->end_at);
    }

    protected function startAt(): Attribute
    {
        return Attribute::make(set: fn (mixed $value) => $this->toUtc($value));
    }

    protected function endAt(): Attribute
    {
        return Attribute::make(set: fn (mixed $value) => $this->toUtc($value));
    }

    private function toUtc(mixed $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        return $value instanceof \DateTimeInterface
            ? CarbonImmutable::instance($value)->utc()
            : CarbonImmutable::parse($value, config('app.timezone'))->utc();
    }
}
