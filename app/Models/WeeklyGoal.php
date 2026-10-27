<?php

namespace App\Models;

use App\Helpers\Helper;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * La meta de una semana.
 *
 * Se identifica por el lunes de esa semana. Al crear cualquier diario de la
 * misma semana, el formulario la encuentra y la trae cargada; si todavía no
 * existe, la pide y la guarda una sola vez.
 */
class WeeklyGoal extends Model
{
    use HasFactory;

    protected $fillable = [
        'week_start',
        'goal',
        'why_important',
    ];

    /**
     * El lunes de la semana, guardado como fecha simple (AAAA-MM-DD).
     *
     * El setter lo deja sin hora a propósito: así la clave única de la semana
     * coincide en MySQL y en SQLite, y dos días de la misma semana encuentran
     * siempre la misma fila.
     */
    protected function weekStart(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value): ?Carbon => $value ? Helper::toCarbon($value) : null,
            set: fn (mixed $value): ?string => $value ? Helper::toCarbon($value)?->toDateString() : null,
        );
    }

    public function reflection(): HasOne
    {
        return $this->hasOne(WeeklyReflection::class, 'week_start', 'week_start');
    }

    /* ======================================================================
     |  Consultas
     ====================================================================== */

    /** La meta de la semana a la que pertenece la fecha. Null si no existe. */
    public static function forDate(mixed $fecha): ?self
    {
        $weekStart = Helper::startOfWeek($fecha);

        if (! $weekStart) {
            return null;
        }

        return static::query()
            ->whereDate('week_start', $weekStart->toDateString())
            ->first();
    }

    /**
     * Guarda (o corrige) la meta de la semana de la fecha indicada.
     * Devuelve null cuando el texto de la meta viene vacío.
     */
    public static function guardarParaFecha(mixed $fecha, ?string $goal, ?string $whyImportant): ?self
    {
        $weekStart = Helper::startOfWeek($fecha);

        if (! $weekStart) {
            return null;
        }

        $goal = Helper::strip($goal);

        if ($goal === '') {
            return null;
        }

        return static::query()->updateOrCreate(
            ['week_start' => $weekStart->toDateString()],
            ['goal' => $goal, 'why_important' => Helper::strip($whyImportant) ?: null]
        );
    }

    /* ======================================================================
     |  Presentación
     ====================================================================== */

    /** "Semana del 19 al 25 de octubre de 2026". */
    public function etiqueta(): string
    {
        $inicio = $this->week_start?->copy();
        $fin = $inicio?->copy()->endOfWeek(\Carbon\Carbon::SUNDAY);

        return 'Semana del '.Helper::rangeLabel($inicio, $fin);
    }
}
