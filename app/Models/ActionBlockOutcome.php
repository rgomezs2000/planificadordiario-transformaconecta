<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catálogo "CUANDO TERMINE ESTE BLOQUE": el resultado del bloque de acción.
 */
class ActionBlockOutcome extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
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

    public function actionBlocks(): HasMany
    {
        return $this->hasMany(ActionBlock::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
