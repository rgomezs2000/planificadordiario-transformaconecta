<?php

namespace App\Models;

use App\Helpers\Helper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lo que pasó con una hoja del libro en la última corrida.
 *
 * Las acciones son las mismas que devuelve el importador: creado (el día entró
 * a la base), omitido (ya estaba guardado y no se toca), saltado (le faltaba un
 * campo obligatorio), ignorado (no es una fecha) o error.
 */
class PlanificacionHoja extends Model
{
    use HasFactory;

    /** La tabla del detalle de cada hoja. */
    protected $table = 'planificacion_hojas';

    public const CREADO = 'creado';

    public const OMITIDO = 'omitido';

    public const SALTADO = 'saltado';

    public const IGNORADO = 'ignorado';

    public const ERROR = 'error';

    /** Etiqueta y color de cada acción, para el módulo. */
    public const ACCIONES = [
        self::CREADO => ['etiqueta' => 'Creado', 'color' => 'turquesa'],
        self::OMITIDO => ['etiqueta' => 'Ya estaba', 'color' => 'azul'],
        self::SALTADO => ['etiqueta' => 'Saltado', 'color' => 'naranja'],
        self::IGNORADO => ['etiqueta' => 'Ignorado', 'color' => 'gris'],
        self::ERROR => ['etiqueta' => 'Error', 'color' => 'rojo'],
    ];

    protected $fillable = [
        'importacion_id',
        'hoja',
        'fecha',
        'accion',
        'motivo',
        'avisos',
        'detalle',
        'procesado_en',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'avisos' => 'integer',
            'procesado_en' => 'datetime',
        ];
    }

    public function importacion(): BelongsTo
    {
        return $this->belongsTo(PlanificacionImportacion::class, 'importacion_id');
    }

    /** Cómo se llama la acción en pantalla. */
    public function etiquetaDeAccion(): string
    {
        return self::ACCIONES[$this->accion]['etiqueta'] ?? $this->accion;
    }

    /** El color de la insignia de la acción. */
    public function colorDeAccion(): string
    {
        return self::ACCIONES[$this->accion]['color'] ?? 'gris';
    }

    /** El día en palabras, o el nombre de la hoja si no es una fecha. */
    public function diaEnPalabras(): string
    {
        return $this->fecha
            ? Helper::longDate($this->fecha, withWeekday: true)
            : '«'.$this->hoja.'»';
    }

    /** Los avisos, uno por línea. */
    public function listaDeAvisos(): array
    {
        if (! $this->detalle) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode("\n", $this->detalle))));
    }
}
