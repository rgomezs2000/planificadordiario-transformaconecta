<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catálogo "MIS 3 OBJETIVOS": Debo hacer, Quiero hacer, Algo para mí.
 */
class GoalType extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'subtitle',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function planGoals(): HasMany
    {
        return $this->hasMany(PlanGoal::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
