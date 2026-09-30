<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catálogo de preguntas de reflexión ("SI ESTOY PROCRASTINANDO ME PREGUNTO").
 */
class ReflectionQuestion extends Model
{
    use HasFactory;

    public const CATEGORY_PROCRASTINATION = 'procrastinacion';

    protected $fillable = [
        'category',
        'question',
        'input_type',
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

    public function reflectionAnswers(): HasMany
    {
        return $this->hasMany(ReflectionAnswer::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    public function scopeCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }
}
