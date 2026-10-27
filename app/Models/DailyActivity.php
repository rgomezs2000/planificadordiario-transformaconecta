<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Catálogo de actividades diarias: los hábitos que se marcan cada día.
 *
 * La tabla manda sobre el formulario y sobre las notificaciones. Una actividad
 * puede pedir minutos (caminata, respiración, meditación), puede estar atada a
 * un día de la semana (el sábado tiene el cambio de sábanas y toallas) y puede
 * tener su propia hora y su propia pregunta de recordatorio.
 */
class DailyActivity extends Model
{
    use HasFactory;

    /** Familias de actividades, para el análisis del resumen. */
    public const CATEGORIAS = [
        'cuidado' => 'Cuidado personal',
        'cuerpo' => 'Cuerpo y movimiento',
        'mente' => 'Mente y regulación',
        'productividad' => 'Estudio y trabajo',
        'hogar' => 'Hogar y entorno',
        'vinculo' => 'Vínculo y tiempo personal',
        'meta' => 'Meta semanal',
    ];

    protected $fillable = [
        'clave',
        'name',
        'icon',
        'requires_minutes',
        'minutes_label',
        'weekday',
        'reminder_time',
        'reminder_question',
        'category',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'requires_minutes' => 'boolean',
            'weekday' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /* ======================================================================
     |  Relaciones
     ====================================================================== */

    public function dailyPlans(): BelongsToMany
    {
        return $this->belongsToMany(DailyPlan::class, 'daily_plan_activity')
            ->withPivot(['is_done', 'minutes', 'note'])
            ->withTimestamps();
    }

    /* ======================================================================
     |  Scopes
     ====================================================================== */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Las que aplican a un día concreto: las de todos los días y las propias
     * de ese día de la semana (0 domingo … 6 sábado).
     */
    public function scopeForWeekday(Builder $query, int $weekday): Builder
    {
        return $query->where(function (Builder $q) use ($weekday) {
            $q->whereNull('weekday')->orWhere('weekday', $weekday);
        });
    }

    /* ======================================================================
     |  Presentación
     ====================================================================== */

    public function categoriaLabel(): string
    {
        return self::CATEGORIAS[$this->category] ?? ucfirst((string) $this->category);
    }

    /** La pregunta del recordatorio, con una de reserva si el catálogo no la trae. */
    public function preguntaRecordatorio(): string
    {
        if (trim((string) $this->reminder_question) !== '') {
            return $this->reminder_question;
        }

        return '¿Has realizado '.mb_strtolower($this->name, 'UTF-8').'?';
    }

    /** ¿Esta actividad aplica a la fecha indicada? */
    public function aplicaA(CarbonInterface $fecha): bool
    {
        return $this->weekday === null || (int) $this->weekday === (int) $fecha->dayOfWeek;
    }
}
