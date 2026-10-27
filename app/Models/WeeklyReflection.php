<?php

namespace App\Models;

use App\Helpers\Helper;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * La reflexión de la semana: las seis preguntas del domingo.
 *
 * Se guarda por semana (el lunes como identidad) para que el domingo cierre la
 * misma semana que vienen trabajando los días anteriores. Las claves de las
 * preguntas son estables: así el análisis del resumen puede citarlas sin
 * depender del texto exacto que se muestre.
 */
class WeeklyReflection extends Model
{
    use HasFactory;

    /** Las seis preguntas, en el orden de la hoja impresa. */
    public const PREGUNTAS = [
        'how_was_week' => '¿Cómo me fue esta semana?',
        'what_achieved' => '¿Qué logré?',
        'what_was_hard' => '¿Qué me costó o fue difícil?',
        'what_learned' => '¿Qué aprendí sobre mí?',
        'how_improved' => '¿Cómo mejoré?',
        'what_different' => '¿Qué haré diferente la próxima semana?',
    ];

    protected $fillable = [
        'week_start',
        ...['how_was_week', 'what_achieved', 'what_was_hard', 'what_learned', 'how_improved', 'what_different'],
    ];

    /**
     * El lunes de la semana, como fecha simple (AAAA-MM-DD).
     * Mismo criterio que WeeklyGoal: la identidad de la semana es el lunes.
     */
    protected function weekStart(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value): ?Carbon => $value ? Helper::toCarbon($value) : null,
            set: fn (mixed $value): ?string => $value ? Helper::toCarbon($value)?->toDateString() : null,
        );
    }

    /* ======================================================================
     |  Consultas
     ====================================================================== */

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

    /** ¿Tiene alguna respuesta escrita? */
    public function tieneContenido(): bool
    {
        foreach (array_keys(self::PREGUNTAS) as $clave) {
            if (! Helper::isBlank($this->{$clave})) {
                return true;
            }
        }

        return false;
    }

    /** Las respuestas con el texto de su pregunta, en orden. */
    public function respuestas(): array
    {
        $filas = [];

        foreach (self::PREGUNTAS as $clave => $pregunta) {
            $filas[] = [
                'clave' => $clave,
                'pregunta' => $pregunta,
                'respuesta' => $this->{$clave},
            ];
        }

        return $filas;
    }

    /** "Semana del 19 al 25 de octubre de 2026". */
    public function etiqueta(): string
    {
        $inicio = $this->week_start?->copy();
        $fin = $inicio?->copy()->endOfWeek(\Carbon\Carbon::SUNDAY);

        return 'Semana del '.Helper::rangeLabel($inicio, $fin);
    }
}
