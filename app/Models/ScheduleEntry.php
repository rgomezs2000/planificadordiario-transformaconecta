<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una franja del horario diario: la actividad y si se cumplió.
 */
class ScheduleEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'daily_plan_id',
        'schedule_slot_id',
        'start_time',
        'activity',
        'is_done',
    ];

    protected function casts(): array
    {
        return [
            'is_done' => 'boolean',
        ];
    }

    public function dailyPlan(): BelongsTo
    {
        return $this->belongsTo(DailyPlan::class);
    }

    public function scheduleSlot(): BelongsTo
    {
        return $this->belongsTo(ScheduleSlot::class);
    }
}
