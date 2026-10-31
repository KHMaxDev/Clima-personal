<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'category_id',
    'code',
    'variable',
    'operator',
    'threshold_a',
    'threshold_b',
    'severity',
    'score_impact',
    'essential',
    'message_es',
    'active',
    'version',
])]
class ActivityRule extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'threshold_a' => 'float',
            'threshold_b' => 'float',
            'score_impact' => 'integer',
            'essential' => 'boolean',
            'active' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ActivityCategory::class, 'category_id');
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('active', true);
    }

    #[Scope]
    protected function forCategory(Builder $query, ?int $categoryId): void
    {
        $query->where(function (Builder $query) use ($categoryId) {
            $query->where('category_id', $categoryId)->orWhereNull('category_id');
        });
    }
}
