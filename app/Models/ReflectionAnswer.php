<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Respuesta del día a una pregunta de reflexión del catálogo.
 */
class ReflectionAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'daily_plan_id',
        'reflection_question_id',
        'is_checked',
        'answer',
    ];

    protected function casts(): array
    {
        return [
            'is_checked' => 'boolean',
        ];
    }

    public function dailyPlan(): BelongsTo
    {
        return $this->belongsTo(DailyPlan::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(ReflectionQuestion::class, 'reflection_question_id');
    }
}
