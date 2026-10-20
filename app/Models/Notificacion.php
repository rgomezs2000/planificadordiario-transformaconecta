<?php

namespace App\Models;

use App\Notificaciones\Candidata;
use App\Notificaciones\PresentadorDeNotificacion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Buzón de notificaciones en MySQL (paradigma SQL).
 *
 * Cada fila es un recordatorio del diario del día: un objetivo principal sin
 * marcar, una franja horaria que ya pasó sin cumplirse o el aviso de que el
 * día no tiene diario cargado. El uuid es la misma identidad del documento de
 * Firestore; la clave única es la huella de la regla y evita duplicados.
 *
 * Toda la consulta vive aquí, igual que en DailyPlan: el controlador invoca y
 * arma la respuesta, y el buzón Firestore replica lo que esta tabla guarda.
 */
class Notificacion extends Model
{
    /** Tipos que se pueden filtrar desde la pantalla. */
    public const ESTADOS = [
        'todas' => 'Todas',
        'no_leidas' => 'No leídas',
        'leidas' => 'Leídas',
    ];

    protected $table = 'notificaciones';

    protected $fillable = [
        'uuid',
        'clave',
        'tipo',
        'titulo',
        'mensaje',
        'prioridad',
        'fecha_referencia',
        'fecha_hora',
        'leida_en',
        'descartada_en',
        'daily_plan_id',
        'plan_goal_id',
        'schedule_entry_id',
        'meta',
        'sincronizada_en',
        'error_firestore',
    ];

    protected function casts(): array
    {
        return [
            'fecha_referencia' => 'date',
            'fecha_hora' => 'datetime',
            'leida_en' => 'datetime',
            'descartada_en' => 'datetime',
            'sincronizada_en' => 'datetime',
            'meta' => 'array',
            'daily_plan_id' => 'integer',
            'plan_goal_id' => 'integer',
            'schedule_entry_id' => 'integer',
        ];
    }

    /* ======================================================================
     |  Relaciones
     ====================================================================== */

    public function dailyPlan(): BelongsTo
    {
        return $this->belongsTo(DailyPlan::class);
    }

    public function planGoal(): BelongsTo
    {
        return $this->belongsTo(PlanGoal::class);
    }

    public function scheduleEntry(): BelongsTo
    {
        return $this->belongsTo(ScheduleEntry::class);
    }

    /* ======================================================================
     |  Scopes
     ====================================================================== */

    /** Las que siguen en la bandeja (las archivadas no se muestran). */
    public function scopeVigentes(Builder $query): Builder
    {
        return $query->whereNull('descartada_en');
    }

    public function scopeNoLeidas(Builder $query): Builder
    {
        return $query->whereNull('leida_en');
    }

    public function scopeLeidas(Builder $query): Builder
    {
        return $query->whereNotNull('leida_en');
    }

    public function scopeDeTipo(Builder $query, ?string $tipo): Builder
    {
        $tipo = trim((string) $tipo);

        return $tipo === '' ? $query : $query->where('tipo', $tipo);
    }

    /** Filtro del selector de estado: todas, no leídas o leídas. */
    public function scopeDelEstado(Builder $query, ?string $estado): Builder
    {
        return match (trim((string) $estado)) {
            'no_leidas' => $query->noLeidas(),
            'leidas' => $query->leidas(),
            default => $query,
        };
    }

    /** Búsqueda libre en el título y el mensaje. */
    public function scopeBuscar(Builder $query, ?string $termino): Builder
    {
        $termino = trim((string) $termino);

        if ($termino === '') {
            return $query;
        }

        $like = '%'.addcslashes($termino, '%_\\').'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('titulo', 'like', $like)->orWhere('mensaje', 'like', $like);
        });
    }

    /* ======================================================================
     |  Consultas
     ====================================================================== */

    /** El listado de la bandeja, de la más reciente a la más antigua. */
    public static function listado(array $filtros = []): Collection
    {
        return static::query()
            ->vigentes()
            ->delEstado($filtros['estado'] ?? null)
            ->deTipo($filtros['tipo'] ?? null)
            ->buscar($filtros['q'] ?? null)
            ->orderByDesc('fecha_hora')
            ->orderByDesc('id')
            ->limit((int) ($filtros['limite'] ?? config('notificaciones.limite_listado', 300)))
            ->get();
    }

    /** Las últimas no leídas: lo que asoma en el aviso de la pantalla. */
    public static function ultimasNoLeidas(int $limite = 5): Collection
    {
        return static::query()
            ->vigentes()
            ->noLeidas()
            ->orderByDesc('fecha_hora')
            ->orderByDesc('id')
            ->limit(max(1, $limite))
            ->get();
    }

    /**
     * Los tres números que alimentan la campanita y la bandeja.
     *
     * @return array{total: int, no_leidas: int, leidas: int}
     */
    public static function contar(): array
    {
        $base = static::query()->vigentes();

        $total = (clone $base)->count();
        $noLeidas = (clone $base)->whereNull('leida_en')->count();

        return [
            'total' => (int) $total,
            'no_leidas' => (int) $noLeidas,
            'leidas' => (int) ($total - $noLeidas),
        ];
    }

    public static function buscarPorUuid(string $uuid): ?self
    {
        $uuid = trim($uuid);

        if ($uuid === '') {
            return null;
        }

        return static::query()->vigentes()->where('uuid', $uuid)->first();
    }

    /** Las que todavía no tienen su documento en Firestore (o fallaron). */
    public static function pendientesDeEspejo(int $limite = 100): Collection
    {
        return static::query()
            ->vigentes()
            ->where(function (Builder $q) {
                $q->whereNull('sincronizada_en')->orWhereNotNull('error_firestore');
            })
            ->orderBy('id')
            ->limit(max(1, $limite))
            ->get();
    }

    /* ======================================================================
     |  Altas
     ====================================================================== */

    /**
     * Guarda la candidata si la regla todavía no había avisado.
     *
     * @return array{0: self, 1: bool} La notificación y si se creó ahora.
     */
    public static function desdeCandidata(Candidata $candidata): array
    {
        $existente = static::query()->where('clave', $candidata->clave)->first();

        if ($existente) {
            return [$existente, false];
        }

        try {
            return [static::create($candidata->paraGuardar()), true];
        } catch (QueryException $e) {
            // Otra petición pudo crearla entre la consulta y el insert: la
            // clave única manda y se devuelve la que ya está guardada.
            $existente = static::query()->where('clave', $candidata->clave)->first();

            if ($existente) {
                return [$existente, false];
            }

            throw $e;
        }
    }

    /* ======================================================================
     |  Cambios de estado
     ====================================================================== */

    /** Marca la notificación como leída. Es idempotente. */
    public function marcarLeida(): bool
    {
        if ($this->descartada_en !== null) {
            return false;
        }

        if ($this->leida_en === null) {
            $this->forceFill(['leida_en' => now()])->save();
        }

        return true;
    }

    /** La saca de la bandeja sin borrar la fila (la clave sigue reservada). */
    public function descartar(): bool
    {
        if ($this->descartada_en !== null) {
            return true;
        }

        $this->forceFill([
            'descartada_en' => now(),
            'leida_en' => $this->leida_en ?? now(),
        ])->save();

        return true;
    }

    /** Marca un lote; devuelve cuántas cambiaron de estado. */
    public static function marcarVarias(array $uuids): int
    {
        $uuids = self::limpiarUuids($uuids);

        if ($uuids === []) {
            return 0;
        }

        return static::query()
            ->vigentes()
            ->whereIn('uuid', $uuids)
            ->whereNull('leida_en')
            ->update(['leida_en' => now()]);
    }

    /** Archiva un lote; devuelve cuántas se sacaron de la bandeja. */
    public static function descartarVarias(array $uuids): int
    {
        $uuids = self::limpiarUuids($uuids);

        if ($uuids === []) {
            return 0;
        }

        return DB::transaction(function () use ($uuids) {
            $pendientes = static::query()
                ->vigentes()
                ->whereIn('uuid', $uuids)
                ->get();

            foreach ($pendientes as $notificacion) {
                $notificacion->descartar();
            }

            return $pendientes->count();
        });
    }

    /* ======================================================================
     |  Estado del espejo NoSQL
     ====================================================================== */

    public function marcarSincronizada(?Throwable $error = null): void
    {
        $this->forceFill([
            'sincronizada_en' => $error === null ? now() : null,
            'error_firestore' => $error?->getMessage(),
        ])->save();
    }

    /* ======================================================================
     |  Respuestas
     ====================================================================== */

    public function aArreglo(): array
    {
        return PresentadorDeNotificacion::formatear($this->aDatos());
    }

    public function aResumen(): array
    {
        return PresentadorDeNotificacion::resumen($this->aDatos());
    }

    /**
     * Los campos canónicos de la notificación, con las fechas todavía como
     * Carbon. Es la base del JSON de pantalla y del documento de Firestore.
     */
    public function aDatos(): array
    {
        return [
            'uuid' => $this->uuid,
            'clave' => $this->clave,
            'tipo' => $this->tipo,
            'titulo' => $this->titulo,
            'mensaje' => $this->mensaje,
            'prioridad' => $this->prioridad,
            'fecha_referencia' => $this->fecha_referencia,
            'fecha_hora' => $this->fecha_hora,
            'leida_en' => $this->leida_en,
            'descartada_en' => $this->descartada_en,
            'daily_plan_id' => $this->daily_plan_id,
            'plan_goal_id' => $this->plan_goal_id,
            'schedule_entry_id' => $this->schedule_entry_id,
            'meta' => $this->meta ?? [],
            'creado_en' => $this->created_at,
            'actualizado_en' => $this->updated_at,
        ];
    }

    /**
     * El documento que se replica en Firestore, con los mismos nombres de
     * campo que la tabla. Las fechas van como Carbon para que el codificador
     * de Firestore las convierta a timestamp.
     */
    public function aDatosFirestore(): array
    {
        $datos = $this->aDatos();

        // La fecha de referencia es un día, no un instante: en Firestore se
        // guarda como texto (2026-10-20) para que no arrastre una hora.
        $datos['fecha_referencia'] = $this->fecha_referencia?->toDateString();

        return $datos;
    }

    /** Descarta uuids vacíos o repetidos que llegan del navegador. */
    public static function limpiarUuids(array $uuids): array
    {
        return array_values(array_unique(array_filter(
            array_map(fn (mixed $uuid) => trim((string) $uuid), $uuids),
            fn (string $uuid) => Str::isUuid($uuid)
        )));
    }
}
