<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uno de los 3 objetivos del día (ranura 1, 2 o 3) con su tipo del catálogo.
 */
class PlanGoal extends Model
{
    use HasFactory;

    /** Ranura 1: "Debo hacer". */
    public const SLOT_MUST = 1;

    /** Ranura 2: "Quiero hacer". */
    public const SLOT_WANT = 2;

    /** Ranura 3: "Algo para mí". */
    public const SLOT_FOR_ME = 3;

    protected $fillable = [
        'daily_plan_id',
        'goal_type_id',
        'slot',
        'description',
        'is_done',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'slot' => 'integer',
            'is_done' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public function dailyPlan(): BelongsTo
    {
        return $this->belongsTo(DailyPlan::class);
    }

    public function goalType(): BelongsTo
    {
        return $this->belongsTo(GoalType::class);
    }
}
