<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catálogo "BLOQUE DE ACCIÓN": duraciones de 5, 10, 15 y 20 minutos.
 */
class ActionBlockDuration extends Model
{
    use HasFactory;

    protected $fillable = [
        'minutes',
        'label',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'minutes' => 'integer',
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
