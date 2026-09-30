<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catálogo "MI HORARIO DE HOY": las franjas horarias de 7:00 a 21:00.
 */
class ScheduleSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'start_time',
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

    public function scheduleEntries(): HasMany
    {
        return $this->hasMany(ScheduleEntry::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('start_time');
    }
}
