<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un bloque de acción ejecutado: cuánto duró y cómo terminó.
 * Es la unidad de medida del foco real del día.
 */
class ActionBlock extends Model
{
    use HasFactory;

    protected $fillable = [
        'daily_plan_id',
        'action_block_duration_id',
        'action_block_outcome_id',
        'task',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function dailyPlan(): BelongsTo
    {
        return $this->belongsTo(DailyPlan::class);
    }

    public function duration(): BelongsTo
    {
        return $this->belongsTo(ActionBlockDuration::class, 'action_block_duration_id');
    }

    public function outcome(): BelongsTo
    {
        return $this->belongsTo(ActionBlockOutcome::class, 'action_block_outcome_id');
    }
}
