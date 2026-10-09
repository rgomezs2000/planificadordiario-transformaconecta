<?php

namespace App\Models;

use App\Helpers\Helper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un libro de Excel montado en "Planificación periódica".
 *
 * Guarda el estado de la última corrida y sus contadores. La verdad de los
 * diarios vive en daily_plans: acá sólo se cuenta lo que hizo el cron, que
 * únicamente crea los días que faltan y nunca pisa uno ya cargado.
 */
class PlanificacionImportacion extends Model
{
    use HasFactory;

    /** Laravel no pluraliza bien "importación": la tabla se llama así. */
    protected $table = 'planificacion_importaciones';

    /* Los estados posibles del libro. */
    public const EN_ESPERA = 'en_espera';

    public const PROCESADO = 'procesado';

    public const PARCIAL = 'parcial';

    public const ERROR = 'error';

    public const DESCARTADO = 'descartado';

    /** Etiqueta y color de cada estado, para el módulo. */
    public const ESTADOS = [
        self::EN_ESPERA => ['etiqueta' => 'En espera de la corrida', 'color' => 'naranja'],
        self::PROCESADO => ['etiqueta' => 'Procesado', 'color' => 'turquesa'],
        self::PARCIAL => ['etiqueta' => 'Parcial', 'color' => 'naranja'],
        self::ERROR => ['etiqueta' => 'Error', 'color' => 'rojo'],
        self::DESCARTADO => ['etiqueta' => 'Descartado', 'color' => 'gris'],
    ];

    protected $fillable = [
        'archivo',
        'nombre_original',
        'periodo_desde',
        'periodo_hasta',
        'estado',
        'hojas_total',
        'hojas_creadas',
        'hojas_omitidas',
        'hojas_saltadas',
        'hojas_ignoradas',
        'hojas_error',
        'cubierto_hasta',
        'en_bd_hasta',
        'dias_sin_hoja',
        'subido_en',
        'procesado_en',
        'descartado_en',
        'mensaje',
    ];

    protected function casts(): array
    {
        return [
            'periodo_desde' => 'date',
            'periodo_hasta' => 'date',
            'cubierto_hasta' => 'date',
            'en_bd_hasta' => 'date',
            'hojas_total' => 'integer',
            'hojas_creadas' => 'integer',
            'hojas_omitidas' => 'integer',
            'hojas_saltadas' => 'integer',
            'hojas_ignoradas' => 'integer',
            'hojas_error' => 'integer',
            'dias_sin_hoja' => 'integer',
            'subido_en' => 'datetime',
            'procesado_en' => 'datetime',
            'descartado_en' => 'datetime',
        ];
    }

    /* ======================================================================
     |  Relaciones
     ====================================================================== */

    /** Las hojas del libro, por fecha. */
    public function hojas(): HasMany
    {
        return $this->hasMany(PlanificacionHoja::class, 'importacion_id')->orderBy('fecha');
    }

    /* ======================================================================
     |  Consultas
     ====================================================================== */

    /** Los libros que todavía tienen algo por procesar. */
    public function scopePendientes(Builder $query): Builder
    {
        return $query->whereIn('estado', [self::EN_ESPERA, self::PARCIAL]);
    }

    /** El último libro que se procesó. */
    public static function ultimaCorrida(): ?static
    {
        return static::query()->whereNotNull('procesado_en')->latest('procesado_en')->first();
    }

    /* ======================================================================
     |  Presentación
     ====================================================================== */

    /** Cómo se llama el estado en pantalla. */
    public function etiquetaDeEstado(): string
    {
        return self::ESTADOS[$this->estado]['etiqueta'] ?? $this->estado;
    }

    /** El color de la insignia del estado. */
    public function colorDeEstado(): string
    {
        return self::ESTADOS[$this->estado]['color'] ?? 'gris';
    }

    /** El período que cubre el libro, en palabras. */
    public function periodoEnPalabras(): string
    {
        if (! $this->periodo_desde && ! $this->periodo_hasta) {
            return 'Sin procesar todavía';
        }

        return (string) Helper::rangeLabel($this->periodo_desde, $this->periodo_hasta);
    }

    /** Hasta dónde está cubierto sin huecos. */
    public function cubiertoEnPalabras(): string
    {
        return $this->cubierto_hasta
            ? Helper::longDate($this->cubierto_hasta, withWeekday: true)
            : '—';
    }

    /** Cuántos días quedaron por cargar (huecos más hojas saltadas). */
    public function diasPendientes(): int
    {
        return max(0, $this->hojas_saltadas);
    }

    /** El resumen de la última corrida, en una línea. */
    public function resumenEnPalabras(): string
    {
        if (! $this->procesado_en) {
            return 'Todavía no ha corrido.';
        }

        $partes = [];

        foreach ([
            'hojas_creadas' => 'creado',
            'hojas_omitidas' => 'omitido',
            'hojas_saltadas' => 'saltado',
            'hojas_ignoradas' => 'ignorado',
            'hojas_error' => 'con error',
        ] as $campo => $etiqueta) {
            if ($this->{$campo} > 0) {
                $partes[] = $this->{$campo}.' '.$etiqueta.($this->{$campo} === 1 ? '' : 's');
            }
        }

        return $partes === [] ? 'Sin hojas que mirar.' : Helper::listToText($partes);
    }
}
