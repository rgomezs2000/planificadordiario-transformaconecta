<?php

namespace App\Models;

use App\Documentos\DocumentoDelDiario;
use App\Errores\RegistroDeErrores;
use App\Filtros\Periodo;
use App\Helpers\Helper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Tabla eje del planificador: un registro por día.
 *
 * Agrupa la cabecera (fecha + energía), el cierre del día, y es el padre
 * de los objetivos, el horario, los bloques de acción, las notas y las
 * respuestas de reflexión.
 *
 * Toda la consulta y manipulación de la base de datos del planificador vive
 * aquí (métodos estáticos de consulta y métodos de instancia), de modo que el
 * controlador solo invoca y arma la respuesta.
 */
class DailyPlan extends Model
{
    use HasFactory;

    /** Relaciones que forman un día completo (para eager loading). */
    public const FULL_RELATIONS = [
        'energyLevel',
        'weeklyGoal',
        'goals.goalType',
        'scheduleEntries.scheduleSlot',
        'activities',
        'actionBlocks.duration',
        'actionBlocks.outcome',
        'notes',
        'preparationItems',
        'reflectionAnswers.question',
    ];

    /** Ranuras válidas de objetivos. */
    public const MAX_GOALS = 3;

    protected $fillable = [
        'plan_date',
        'energy_level_id',
        'weekly_goal_id',
        'personal_done_today',
        'personal_helped',
        'personal_hard',
        'achievements',
        'pending',
        'pending_when',
        'proud_of',
    ];

    protected function casts(): array
    {
        return [
            'plan_date' => 'date',
        ];
    }

    /* ======================================================================
     |  Relaciones
     ====================================================================== */

    public function energyLevel(): BelongsTo
    {
        return $this->belongsTo(EnergyLevel::class);
    }

    /** La meta de la semana a la que pertenece este día. */
    public function weeklyGoal(): BelongsTo
    {
        return $this->belongsTo(WeeklyGoal::class);
    }

    /**
     * La meta de la semana del día, con respaldo por fecha.
     *
     * Los diarios viejos no tienen `weekly_goal_id`, pero si otro día de su
     * misma semana ya escribió la meta, les corresponde igual. El respaldo
     * hace que el PDF y los reportes la muestren sin migrar los datos.
     */
    public function metaSemanal(): ?WeeklyGoal
    {
        return $this->weeklyGoal
            ?? ($this->plan_date ? WeeklyGoal::forDate($this->plan_date) : null);
    }

    /**
     * Las actividades diarias marcadas, con sus minutos y su nota.
     * Se ordenan por el catálogo para que la hoja y la pantalla coincidan.
     */
    public function activities(): BelongsToMany
    {
        return $this->belongsToMany(DailyActivity::class, 'daily_plan_activity')
            ->withPivot(['is_done', 'minutes', 'note'])
            ->withTimestamps()
            ->orderBy('daily_activities.sort_order')
            ->orderBy('daily_activities.id');
    }

    /**
     * La reflexión de la semana de este día.
     *
     * No es una relación de Eloquent porque la identidad de la reflexión es el
     * lunes de la semana, no el id del diario: se busca en cada consulta.
     */
    public function reflexionSemanal(): ?WeeklyReflection
    {
        return $this->plan_date ? WeeklyReflection::forDate($this->plan_date) : null;
    }

    /** Los 3 objetivos del día, siempre ordenados por su ranura 1-2-3. */
    public function goals(): HasMany
    {
        return $this->hasMany(PlanGoal::class)->orderBy('slot');
    }

    /**
     * El horario del día, ordenado cronológicamente.
     * La hora vive en la propia franja (start_time); schedule_slot_id sólo
     * recuerda de qué franja del catálogo se generó, si fue el caso.
     */
    public function scheduleEntries(): HasMany
    {
        return $this->hasMany(ScheduleEntry::class)
            ->orderBy('schedule_entries.start_time')
            ->orderBy('schedule_entries.id');
    }

    /** Los bloques de acción ejecutados durante el día. */
    public function actionBlocks(): HasMany
    {
        return $this->hasMany(ActionBlock::class);
    }

    /** Notas y recordatorios del día. */
    public function notes(): HasMany
    {
        return $this->hasMany(PlanNote::class)->orderBy('sort_order');
    }

    /** Respuestas a las preguntas de reflexión del día. */
    public function reflectionAnswers(): HasMany
    {
        return $this->hasMany(ReflectionAnswer::class);
    }

    /**
     * Checklist "ANTES DE EMPEZAR", con su estado y su descripción libre
     * ("PC, cuaderno, calculadora") desde el pivote.
     */
    public function preparationItems(): BelongsToMany
    {
        return $this->belongsToMany(PreparationItem::class, 'daily_plan_preparation')
            ->withPivot(['is_checked', 'preparation_items_description'])
            ->withTimestamps();
    }

    /* ======================================================================
     |  Atributos y scopes
     ====================================================================== */

    /**
     * El día de la semana tal como se marca en el formulario: L M M J V S D.
     * Se deriva de plan_date para no duplicar información en la base de datos.
     */
    protected function weekdayLetter(): Attribute
    {
        return Attribute::get(fn (): string => Helper::dayLetter($this->plan_date) ?? '');
    }

    public function scopeForDate(Builder $query, string|\DateTimeInterface $date): Builder
    {
        return $query->whereDate('plan_date', $date);
    }

    /** Filtra por rango de fechas; ambos extremos son opcionales. */
    public function scopeBetweenDates(Builder $query, mixed $from, mixed $to = null): Builder
    {
        return $query
            ->when($from, fn (Builder $q, mixed $value) => $q->whereDate('plan_date', '>=', $value))
            ->when($to, fn (Builder $q, mixed $value) => $q->whereDate('plan_date', '<=', $value));
    }

    /** Búsqueda libre en el cierre del día, los objetivos, el horario y las notas. */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = Helper::strip($term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('achievements', 'like', $like)
                ->orWhere('pending', 'like', $like)
                ->orWhere('pending_when', 'like', $like)
                ->orWhere('proud_of', 'like', $like)
                ->orWhere('personal_done_today', 'like', $like)
                ->orWhere('personal_helped', 'like', $like)
                ->orWhere('personal_hard', 'like', $like)
                ->orWhereHas('weeklyGoal', fn (Builder $meta) => $meta
                    ->where('goal', 'like', $like)
                    ->orWhere('why_important', 'like', $like))
                ->orWhereHas('activities', fn (Builder $actividades) => $actividades
                    ->where('daily_activities.name', 'like', $like))
                ->orWhereHas('goals', fn (Builder $goals) => $goals->where('description', 'like', $like))
                ->orWhereHas('scheduleEntries', fn (Builder $franjas) => $franjas->where('activity', 'like', $like))
                ->orWhereHas('actionBlocks', fn (Builder $bloques) => $bloques->where('task', 'like', $like))
                ->orWhereHas('notes', fn (Builder $notes) => $notes->where('content', 'like', $like));
        });
    }

    /**
     * Filtros del buscador de diarios: fecha exacta, energía y palabra clave.
     *
     * Los usan el listado y el reporte detallado, para que el Excel salga con
     * los mismos diarios que se están viendo en pantalla.
     *
     * @param  array{fecha?: mixed, energia?: mixed, palabra?: mixed}  $filtros
     */
    public function scopeFiltrado(Builder $query, array $filtros = []): Builder
    {
        $energia = Helper::strip($filtros['energia'] ?? null);
        $fecha = Helper::toCarbon($filtros['fecha'] ?? null);

        // Filtro por período: una semana, una quincena, un mes, un trimestre,
        // un semestre, un año o un rango de fechas.
        $periodo = Periodo::rango($filtros['periodo'] ?? null, $filtros);

        return $query
            // Si se eligió un día concreto manda ese día; si no, el período.
            ->when($fecha, fn (Builder $q, mixed $dia) => $q->whereDate('plan_date', $dia->toDateString()))
            ->when(
                ! $fecha && $periodo,
                fn (Builder $q) => $q->whereBetween('plan_date', [
                    $periodo['inicio']->toDateString(),
                    $periodo['fin']->toDateString(),
                ])
            )
            ->when(
                $energia !== '',
                fn (Builder $q) => $q->whereHas(
                    'energyLevel',
                    fn (Builder $nivel) => $nivel->where('slug', $energia)
                )
            )
            ->search($filtros['palabra'] ?? null);
    }

    /* ======================================================================
     |  Consultas
     ====================================================================== */

    /** Carga todas las relaciones del día sobre la instancia actual. */
    public function loadFull(): static
    {
        return $this->load(self::FULL_RELATIONS);
    }

    /** El día de una fecha concreta, con todo cargado. Null si no existe. */
    public static function findByDate(mixed $date): ?static
    {
        $carbon = Helper::toCarbon($date);

        if (! $carbon) {
            return null;
        }

        return static::query()
            ->whereDate('plan_date', $carbon->toDateString())
            ->with(self::FULL_RELATIONS)
            ->first();
    }

    /** El día de hoy, con todo cargado. */
    public static function findToday(): ?static
    {
        return static::findByDate(now());
    }

    /**
     * Listado paginado para la tabla del planificador.
     *
     * @param  array{from?: mixed, to?: mixed, energy_level_id?: mixed, search?: mixed}  $filters
     */
    public static function paginateList(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return static::query()
            ->with('energyLevel')
            ->withCount([
                'goals',
                'goals as goals_done_count' => fn (Builder $query) => $query->where('is_done', true),
                'actionBlocks',
                'notes',
            ])
            ->betweenDates($filters['from'] ?? null, $filters['to'] ?? null)
            ->when(
                $filters['energy_level_id'] ?? null,
                fn (Builder $query, mixed $energy) => $query->where('energy_level_id', $energy)
            )
            ->search($filters['search'] ?? null)
            ->orderBy('plan_date')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Todos los días registrados, del más reciente al más antiguo.
     *
     * Es lo que alimenta la tabla del listado: se entregan todas las filas y
     * DataTables se encarga de buscar, ordenar y paginar en el navegador.
     *
     * Acepta los filtros del buscador, que se pueden combinar: una fecha con
     * una energía, una palabra clave con una fecha, etc. La palabra clave se
     * busca dentro del cierre del día, los objetivos, el horario, las notas y
     * el bloque de acción, y basta con una sola letra.
     *
     * @param  array{fecha?: mixed, energia?: mixed, palabra?: mixed}  $filtros
     */
    public static function allForList(array $filtros = []): Collection
    {
        return static::query()
            ->with('energyLevel')
            ->withCount([
                'activities',
                'activities as activities_done_count' => fn (Builder $query) => $query
                    ->where('daily_plan_activity.is_done', true),
            ])
            ->filtrado($filtros)
            ->orderBy('plan_date')
            ->get();
    }

    /**
     * Los días que van al reporte detallado, con todo lo que se desglosa.
     *
     * Se cargan las relaciones de una vez para que el reporte no dispare una
     * consulta por cada diario.
     *
     * @param  array{fecha?: mixed, energia?: mixed, palabra?: mixed}  $filtros
     */
    public static function forReport(array $filtros = []): Collection
    {
        return static::query()
            ->with([
                'energyLevel',
                'weeklyGoal',
                'activities',
                'preparationItems',
                'reflectionAnswers.question',
                'actionBlocks.duration',
                'actionBlocks.outcome',
                'notes',
            ])
            ->filtrado($filtros)
            ->orderBy('plan_date')
            ->get();
    }

    /* ======================================================================
     |  Altas, cambios y bajas
     ====================================================================== */

    /**
     * Ejecuta la operación dentro de una transacción.
     *
     * Si algo falla se deshace todo, queda constancia en el log general y en el
     * log detallado de errores —con el nombre de la operación y el contexto que
     * se pase— y el error sube al controlador, que decide qué responder al
     * cliente.
     *
     * @param  array<string, mixed>  $contexto  Datos para el registro del error.
     */
    protected static function enTransaccion(
        callable $operacion,
        string $nombre = 'operación del diario',
        array $contexto = []
    ): mixed {
        try {
            return DB::transaction($operacion);
        } catch (Throwable $e) {
            report($e);

            RegistroDeErrores::deModelo($e, $nombre, $contexto);

            throw $e;
        }
    }

    /**
     * Crea un día completo: la cabecera, su estructura hija sacada de los
     * catálogos y los datos que vengan del formulario.
     */
    public static function createDay(array $data): static
    {
        return static::enTransaccion(function () use ($data) {
            $plan = static::create([
                'plan_date' => Helper::toCarbon($data['plan_date']),
                'energy_level_id' => $data['energy_level_id'] ?? null,
                'personal_done_today' => $data['personal_done_today'] ?? null,
                'personal_helped' => $data['personal_helped'] ?? null,
                'personal_hard' => $data['personal_hard'] ?? null,
                'achievements' => $data['achievements'] ?? null,
                'pending' => $data['pending'] ?? null,
                'pending_when' => $data['pending_when'] ?? null,
                'proud_of' => $data['proud_of'] ?? null,
            ]);

            $plan->refreshDayStructure();
            $plan->applyDayData($data);

            // Si la semana ya tenía meta (por ejemplo, el diario del lunes), el
            // día nuevo queda vinculado a ella aunque el formulario no la repita.
            $plan->vincularMetaSemanal();

            return $plan->fresh(self::FULL_RELATIONS);
        }, 'crear el día', ['plan_date' => $data['plan_date'] ?? null]);
    }

    /** Modifica el día. Solo se tocan las claves que vienen en $data. */
    public function updateDay(array $data): static
    {
        return static::enTransaccion(function () use ($data) {
            $attributes = [];

            if (($data['plan_date'] ?? null) !== null) {
                $attributes['plan_date'] = Helper::toCarbon($data['plan_date']);
            }

            foreach ([
                'energy_level_id',
                'personal_done_today',
                'personal_helped',
                'personal_hard',
                'achievements',
                'pending',
                'pending_when',
                'proud_of',
            ] as $key) {
                if (array_key_exists($key, $data)) {
                    $attributes[$key] = $data[$key];
                }
            }

            if ($attributes !== []) {
                $this->update($attributes);
            }

            $this->applyDayData($data);

            // Si el día cambió de semana, la meta semanal tiene que cambiar con
            // él; el formulario la manda de nuevo, pero una petición parcial
            // (o la importación) no debería dejar el vínculo viejo.
            $this->vincularMetaSemanal();

            // El PDF y el JPG guardados ya no representan al día: se borran y
            // se vuelven a generar la próxima vez que se pidan.
            DocumentoDelDiario::de($this)->limpiar();

            return $this->fresh(self::FULL_RELATIONS);
        }, 'modificar el día', ['diario_id' => $this->id]);
    }

    /**
     * Marca o desmarca el cumplimiento de las franjas del horario.
     *
     * Es la acción del botón que va al final de "Mi horario de hoy" en la
     * vista de sólo lectura: recibe las franjas con su estado y sólo toca las
     * que pertenecen a este día. No crea ni borra franjas; las que no vengan
     * en la lista se quedan como estaban.
     *
     * Si algo cambió, se borran el PDF y la imagen guardados: el documento
     * mostraba el cumplimiento anterior y tiene que volver a generarse.
     *
     * @param  array<int, array{id?: mixed, is_done?: mixed}>  $franjas
     * @return array<string, mixed>
     */
    public function markScheduleDone(array $franjas): array
    {
        return static::enTransaccion(function () use ($franjas) {
            $ids = collect($franjas)
                ->pluck('id')
                ->map(fn (mixed $id) => (int) $id)
                ->unique()
                ->all();

            $entries = $this->scheduleEntries()
                ->whereIn('id', $ids)
                ->get()
                ->keyBy('id');

            $cambios = 0;

            foreach ($franjas as $franja) {
                $entry = $entries->get((int) ($franja['id'] ?? 0));

                if (! $entry) {
                    continue;
                }

                $hecho = (bool) ($franja['is_done'] ?? false);

                if ((bool) $entry->is_done === $hecho) {
                    continue;
                }

                $entry->is_done = $hecho;
                $entry->save();
                $cambios++;
            }

            // El PDF y la imagen del día enseñan las franjas cumplidas: si el
            // cumplimiento cambió, lo guardado dejó de representar al día.
            if ($cambios > 0) {
                DocumentoDelDiario::de($this)->limpiar();
            }

            // Se recargan las relaciones que usa el resumen de avance, así la
            // respuesta lleva el estado real que quedó en la base de datos.
            $this->load(['goals', 'scheduleEntries', 'actionBlocks.duration']);

            return [
                'updated' => $cambios,
                'done' => $this->scheduleEntries->where('is_done', true)->count(),
                'total' => $this->scheduleEntries->count(),
                'franjas' => $this->scheduleEntries
                    ->map(fn (ScheduleEntry $franja) => [
                        'id' => $franja->id,
                        'is_done' => (bool) $franja->is_done,
                    ])
                    ->values()
                    ->all(),
                'progress' => $this->progressSummary(),
            ];
        }, 'marcar el cumplimiento del horario', ['diario_id' => $this->id]);
    }

    /**
     * Marca o desmarca las actividades diarias desde la vista de sólo lectura.
     *
     * Recibe las actividades con su estado y, cuando corresponde, sus minutos.
     * Sólo toca las que pertenecen a este día. Si algo cambió, borra el PDF y
     * la imagen guardados para que se vuelvan a generar con el estado nuevo.
     *
     * @param  array<int, array{daily_activity_id?: mixed, is_done?: mixed, minutes?: mixed}>  $filas
     * @return array<string, mixed>
     */
    public function markActivitiesDone(array $filas): array
    {
        return static::enTransaccion(function () use ($filas) {
            $ids = collect($filas)
                ->pluck('daily_activity_id')
                ->map(fn (mixed $id) => (int) $id)
                ->unique()
                ->all();

            $catalogo = DailyActivity::query()->whereIn('id', $ids)->get()->keyBy('id');
            $actuales = $this->activities()->get()->keyBy('id');
            $cambios = 0;

            foreach ($filas as $fila) {
                $id = (int) ($fila['daily_activity_id'] ?? 0);
                /** @var DailyActivity|null $actividad */
                $actividad = $catalogo->get($id);

                // Sólo se aceptan actividades del catálogo que apliquen al día:
                // el cambio de sábanas no se puede marcar un martes.
                if (! $actividad || ! $actividad->aplicaA($this->plan_date)) {
                    continue;
                }

                $hecho = (bool) ($fila['is_done'] ?? false);

                $minutos = $actividad->requires_minutes && $hecho
                    ? max(0, min(1440, (int) ($fila['minutes'] ?? 0)))
                    : null;

                $pivote = $actuales->get($id)?->pivot;

                // Un diario viejo puede no tener la fila de la actividad: se
                // crea al marcarla, sin tocar el resto del día.
                if (! $pivote) {
                    $this->activities()->attach($id, [
                        'is_done' => $hecho,
                        'minutes' => $minutos ?: null,
                    ]);

                    $cambios++;

                    continue;
                }

                $antesHecho = (bool) $pivote->is_done;
                $antesMinutos = (int) ($pivote->minutes ?? 0);

                if ($antesHecho === $hecho && (! $hecho || $antesMinutos === (int) $minutos)) {
                    continue;
                }

                $this->activities()->updateExistingPivot($id, [
                    'is_done' => $hecho,
                    'minutes' => $minutos ?: null,
                ]);

                $cambios++;
            }

            if ($cambios > 0) {
                DocumentoDelDiario::de($this)->limpiar();
            }

            $this->load(['activities']);

            $hechas = $this->activities->filter(fn (DailyActivity $a) => (bool) $a->pivot->is_done);
            $total = $this->activities->count();

            return [
                'updated' => $cambios,
                'done' => $hechas->count(),
                'total' => $total,
                'percent' => Helper::percentageOf($hechas->count(), $total),
                'activities' => $this->activities->map(fn (DailyActivity $actividad) => [
                    'daily_activity_id' => $actividad->id,
                    'is_done' => (bool) $actividad->pivot->is_done,
                    'minutes' => $actividad->pivot->minutes !== null
                        ? (int) $actividad->pivot->minutes
                        : null,
                ])->values()->all(),
                'progress' => $this->progressSummary(),
            ];
        }, 'marcar las actividades del día', ['diario_id' => $this->id]);
    }

    /**
     * Elimina el día; las claves foráneas en cascada limpian sus hijos.
     *
     * También se lleva los archivos generados en el almacenamiento privado (el
     * PDF y su imagen), para no dejar documentos de un diario que ya no existe.
     */
    public function deleteDay(): bool
    {
        return (bool) static::enTransaccion(function () {
            DocumentoDelDiario::de($this)->limpiar();

            return $this->delete();
        }, 'eliminar el día', ['diario_id' => $this->id]);
    }

    /** ¿Se registró el cierre del día? */
    public function isClosed(): bool
    {
        return ! Helper::isBlank($this->achievements)
            || ! Helper::isBlank($this->pending)
            || ! Helper::isBlank($this->proud_of);
    }

    /**
     * Genera las filas hijas del día a partir de los catálogos activos:
     * las 15 franjas del horario, el checklist y las preguntas de reflexión.
     * Es idempotente: no duplica lo que ya existe.
     */
    public function refreshDayStructure(): void
    {
        $this->generateScheduleEntries();
        $this->generatePreparationItems();
        $this->generateReflectionAnswers();
        $this->generateActivities();
    }

    /* ======================================================================
     |  Resúmenes para las respuestas
     ====================================================================== */

    /** Resumen de avance del día (objetivos, horario, actividades y minutos). */
    public function progressSummary(): array
    {
        $goalsDone = $this->goals->where('is_done', true)->count();
        $goalsTotal = $this->goals->count();
        $entriesDone = $this->scheduleEntries->where('is_done', true)->count();
        $entriesTotal = $this->scheduleEntries->count();

        $activities = $this->relationLoaded('activities') ? $this->activities : $this->activities()->get();
        $activitiesDone = $activities->filter(fn (DailyActivity $a) => (bool) $a->pivot->is_done)->count();
        $activitiesTotal = $activities->count();
        $activityMinutes = (int) $activities->sum(fn (DailyActivity $a) => (int) ($a->pivot->minutes ?? 0));

        $focusMinutes = (int) $this->actionBlocks->sum(
            fn (ActionBlock $block) => $block->duration?->minutes ?? 0
        );

        return [
            'goals_done' => $goalsDone,
            'goals_total' => $goalsTotal,
            'goals_percent' => Helper::percentageOf($goalsDone, $goalsTotal),
            'schedule_done' => $entriesDone,
            'schedule_total' => $entriesTotal,
            'schedule_percent' => Helper::percentageOf($entriesDone, $entriesTotal),
            'activities_done' => $activitiesDone,
            'activities_total' => $activitiesTotal,
            'activities_percent' => Helper::percentageOf($activitiesDone, $activitiesTotal),
            'activity_minutes' => $activityMinutes,
            'activity_minutes_label' => $activityMinutes > 0 ? Helper::minutesToHuman($activityMinutes) : null,
            'focus_minutes' => $focusMinutes,
            'focus_label' => Helper::minutesToHuman($focusMinutes),
            'is_closed' => $this->isClosed(),
        ];
    }

    /** Fila del listado (JSON). */
    public function toListArray(): array
    {
        return [
            'id' => $this->id,
            'date' => Helper::date($this->plan_date, 'Y-m-d'),
            'date_label' => Helper::longDate($this->plan_date),
            'day_letter' => $this->weekday_letter,
            'energy' => $this->energyLevel?->name,
            'energy_slug' => $this->energyLevel?->slug,
            'energy_emoji' => $this->energyLevel?->emoji,
            'achievements' => Helper::limit($this->achievements, 80),
            'goals_count' => (int) ($this->goals_count ?? 0),
            'goals_done_count' => (int) ($this->goals_done_count ?? 0),
            'action_blocks_count' => (int) ($this->action_blocks_count ?? 0),
            'activities_count' => (int) ($this->activities_count ?? 0),
            'activities_done_count' => (int) ($this->activities_done_count ?? 0),
            'notes_count' => (int) ($this->notes_count ?? 0),
            'is_closed' => $this->isClosed(),
        ];
    }

    /**
     * Una fila del reporte detallado: un diario por fila.
     *
     * Devuelve los textos ya armados para el Excel: los ítems del "antes de
     * empezar", las preguntas de procrastinación con su respuesta, el bloque de
     * acción, el cierre del día y las notas.
     */
    public function toReportArray(): array
    {
        $this->loadMissing([
            'energyLevel',
            'weeklyGoal',
            'activities',
            'preparationItems',
            'reflectionAnswers.question',
            'actionBlocks.duration',
            'actionBlocks.outcome',
            'notes',
        ]);

        $objetivos = $this->goals->keyBy('slot');
        $objetivo = fn (int $slot): string => Helper::strip($objetivos->get($slot)?->description);

        $preparativos = $this->preparationItems->sortBy(fn (PreparationItem $item) => $item->id);
        $preguntas = $this->reflectionAnswers->sortBy(fn (ReflectionAnswer $r) => $r->question?->id ?? 0);
        $bloques = $this->actionBlocks;

        $siNo = fn (bool $marcado): string => $marcado ? 'Sí' : 'No';

        $meta = $this->metaSemanal();

        // Actividades: una línea por actividad, con sus minutos cuando los pide.
        $actividades = $this->activities
            ->map(function (DailyActivity $actividad) {
                $texto = ((bool) $actividad->pivot->is_done ? 'Sí' : 'No').' · '.$actividad->name;

                if ($actividad->pivot->minutes !== null && (int) $actividad->pivot->minutes > 0) {
                    $texto .= ' ('.(int) $actividad->pivot->minutes.' min)';
                }

                return $texto;
            })
            ->implode("\n");

        $minutosActividades = (int) $this->activities->sum(fn (DailyActivity $a) => (int) ($a->pivot->minutes ?? 0));
        $actividadesHechas = $this->activities->filter(fn (DailyActivity $a) => (bool) $a->pivot->is_done)->count();
        $actividadesTotal = $this->activities->count();

        // Reflexión semanal: es del domingo. En los demás días del período la
        // columna queda vacía, aunque la semana tenga su reflexión escrita.
        $reflexion = $this->plan_date->isSunday() ? $this->reflexionSemanal() : null;
        $reflexionTexto = $reflexion
            ? collect($reflexion->respuestas())
                ->filter(fn (array $fila) => ! Helper::isBlank($fila['respuesta']))
                ->map(fn (array $fila) => $fila['pregunta'].' '.Helper::strip($fila['respuesta']))
                ->implode("\n")
            : '';

        return [
            // Se usa para el formato de fecha de la primera columna.
            'date' => $this->plan_date,

            // Las celdas, en el mismo orden que las columnas del reporte.
            'cells' => [
                Helper::dayName($this->plan_date, capitalize: true),
                $this->energyLevel?->name,

                // Mi meta semanal.
                Helper::strip($meta?->goal),
                Helper::strip($meta?->why_important),

                // Mis 3 objetivos principales de hoy.
                $objetivo(1),
                $objetivo(2),
                $objetivo(3),

                // Antes de empezar: un Sí/No por ítem, en el orden del catálogo.
                ...$preparativos->map(fn (PreparationItem $item) => $siNo((bool) $item->pivot->is_checked))->values()->all(),

                // Si estoy procrastinando: un Sí/No por pregunta.
                ...$preguntas->map(fn (ReflectionAnswer $r) => $siNo((bool) $r->is_checked))->values()->all(),

                // Bloque de acción: el tiempo elegido, cómo terminó y en qué se trabajó.
                $bloques->map(fn (ActionBlock $b) => $b->duration?->label)->filter()->implode("\n"),
                $bloques->map(fn (ActionBlock $b) => $b->outcome?->name)->filter()->implode("\n"),
                $bloques->map(fn (ActionBlock $b) => Helper::strip($b->task))->filter()->implode("\n"),

                // Actividades diarias y su avance.
                $actividades,
                $minutosActividades > 0 ? $minutosActividades : '',
                Helper::percentageOf($actividadesHechas, $actividadesTotal),

                // Mi meta personal: el seguimiento del día.
                Helper::strip($this->personal_done_today),
                Helper::strip($this->personal_helped),
                Helper::strip($this->personal_hard),

                // Reflexión de la semana (sólo domingo).
                $reflexionTexto,

                // Cierre del día: cada pregunta en su propia columna.
                Helper::strip($this->achievements),
                Helper::strip($this->pending),
                Helper::strip($this->pending_when),
                Helper::strip($this->proud_of),

                $this->notes
                    ->map(fn (PlanNote $nota) => Helper::strip($nota->content))
                    ->filter()
                    ->implode("\n"),
            ],
        ];
    }

    /** Día completo con todas sus secciones (JSON de mostrar/detalle). */
    public function toDetailArray(): array
    {
        $this->loadMissing(self::FULL_RELATIONS);
        $reflexion = $this->plan_date->isSunday() ? $this->reflexionSemanal() : null;
        $meta = $this->metaSemanal();

        return [
            'id' => $this->id,
            'date' => Helper::date($this->plan_date, 'Y-m-d'),
            'date_label' => Helper::longDate($this->plan_date, withWeekday: true),
            'day_letter' => $this->weekday_letter,
            'energy' => $this->energyLevel ? [
                'id' => $this->energyLevel->id,
                'name' => $this->energyLevel->name,
                'emoji' => $this->energyLevel->emoji,
            ] : null,
            'closure' => [
                'achievements' => $this->achievements,
                'pending' => $this->pending,
                'pending_when' => $this->pending_when,
                'proud_of' => $this->proud_of,
            ],
            'weekly_goal' => $meta ? [
                'id' => $meta->id,
                'week_start' => Helper::date($meta->week_start, 'Y-m-d'),
                'label' => $meta->etiqueta(),
                'goal' => $meta->goal,
                'why_important' => $meta->why_important,
            ] : null,
            'personal_followup' => [
                'done_today' => $this->personal_done_today,
                'helped' => $this->personal_helped,
                'hard' => $this->personal_hard,
            ],
            'activities' => $this->activities->map(fn (DailyActivity $actividad) => [
                'id' => $actividad->id,
                'clave' => $actividad->clave,
                'name' => $actividad->name,
                'icon' => $actividad->icon,
                'category' => $actividad->category,
                'category_label' => $actividad->categoriaLabel(),
                'requires_minutes' => (bool) $actividad->requires_minutes,
                'is_done' => (bool) $actividad->pivot->is_done,
                'minutes' => $actividad->pivot->minutes !== null ? (int) $actividad->pivot->minutes : null,
                'note' => $actividad->pivot->note,
            ])->all(),
            'weekly_reflection' => $reflexion ? [
                'week_start' => Helper::date($reflexion->week_start, 'Y-m-d'),
                'label' => $reflexion->etiqueta(),
                'answers' => $reflexion->respuestas(),
            ] : null,
            'goals' => $this->goals->map(fn (PlanGoal $goal) => [
                'id' => $goal->id,
                'slot' => $goal->slot,
                'type' => $goal->goalType?->name,
                'subtitle' => $goal->goalType?->subtitle,
                'description' => $goal->description,
                'is_done' => (bool) $goal->is_done,
                'completed_at' => Helper::dateTime($goal->completed_at),
            ])->all(),
            'schedule' => $this->scheduleEntries->map(fn (ScheduleEntry $entry) => [
                'id' => $entry->id,
                'slot_id' => $entry->schedule_slot_id,
                'hour' => Helper::timeLabel($entry->start_time ?? $entry->scheduleSlot?->start_time),
                'start_time' => Helper::time($entry->start_time, 'H:i'),
                'activity' => $entry->activity,
                'is_done' => (bool) $entry->is_done,
            ])->all(),
            'preparation' => $this->preparationItems->map(fn (PreparationItem $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'is_checked' => (bool) $item->pivot->is_checked,
                'description' => $item->pivot->preparation_items_description,
            ])->all(),
            'reflections' => $this->reflectionAnswers->map(fn (ReflectionAnswer $answer) => [
                'id' => $answer->id,
                'question_id' => $answer->reflection_question_id,
                'question' => $answer->question?->question,
                'is_checked' => (bool) $answer->is_checked,
                'answer' => $answer->answer,
            ])->all(),
            'action_blocks' => $this->actionBlocks->map(fn (ActionBlock $block) => [
                'id' => $block->id,
                'minutes' => $block->duration?->minutes,
                'duration' => $block->duration?->label,
                'outcome' => $block->outcome?->name,
                'task' => $block->task,
                'started_at' => Helper::dateTime($block->started_at),
                'finished_at' => Helper::dateTime($block->finished_at),
            ])->all(),
            'notes' => $this->notes->map(fn (PlanNote $note) => [
                'id' => $note->id,
                'content' => $note->content,
                'sort_order' => $note->sort_order,
            ])->all(),
            'progress' => $this->progressSummary(),
            'created_at' => Helper::dateTime($this->created_at),
            'updated_at' => Helper::dateTime($this->updated_at),
        ];
    }

    /* ======================================================================
     |  Internos: estructura hija
     ====================================================================== */

    /**
     * Crea las franjas del horario que falten, copiando la hora del catálogo.
     * Es sólo un punto de partida: el formulario permite añadir y quitar filas
     * con la hora que se quiera.
     */
    protected function generateScheduleEntries(): void
    {
        $slots = ScheduleSlot::active()->get(['id', 'start_time']);

        if ($slots->isEmpty()) {
            return;
        }

        $existing = $this->scheduleEntries()->pluck('schedule_slot_id')->filter()->all();

        $missing = $slots
            ->reject(fn (ScheduleSlot $slot) => in_array($slot->id, $existing, true))
            ->map(fn (ScheduleSlot $slot) => [
                'schedule_slot_id' => $slot->id,
                'start_time' => $slot->start_time,
            ])
            ->values();

        if ($missing->isNotEmpty()) {
            $this->scheduleEntries()->createMany($missing->all());
        }
    }

    /* ======================================================================
     |  Internos: meta semanal, actividades y reflexión de la semana
     ====================================================================== */

    /**
     * Guarda la meta de la semana y vincula el día con ella.
     *
     * La meta es de la semana, no del día: si el lunes ya la escribió, el
     * martes la reutiliza en vez de crear otra.
     */
    /**
     * Guarda la meta de la semana y vincula el día con ella.
     *
     * La meta se registra **una sola vez por semana**. Si la semana ya tiene
     * una, los diarios siguientes se limitan a vincularse: no la vuelven a
     * registrar ni la pisan. Para cambiarla hay que pedirlo explícitamente con
     * `weekly_goal[actualizar]` (lo hace el botón «Cambiar meta» del formulario).
     * Al empezar una semana nueva no existe fila para ese lunes, así que se
     * registra una meta nueva.
     */
    protected function syncWeeklyGoal(array $meta): void
    {
        if (! $this->plan_date) {
            return;
        }

        $existente = WeeklyGoal::forDate($this->plan_date);
        $actualizar = (bool) ($meta['actualizar'] ?? false);

        if ($existente) {
            if ($actualizar) {
                $goal = Helper::strip($meta['goal'] ?? null);

                if ($goal !== '') {
                    $cambios = ['goal' => $goal];

                    if (array_key_exists('why_important', $meta)) {
                        $cambios['why_important'] = Helper::strip($meta['why_important']) ?: null;
                    }

                    $existente->update($cambios);
                }
            }

            $this->vincularMetaSemanal();

            return;
        }

        if (array_key_exists('goal', $meta) || array_key_exists('why_important', $meta)) {
            $guardada = WeeklyGoal::guardarParaFecha(
                $this->plan_date,
                $meta['goal'] ?? null,
                $meta['why_important'] ?? null
            );

            if ($guardada) {
                if ((int) $this->weekly_goal_id !== (int) $guardada->id) {
                    $this->forceFill(['weekly_goal_id' => $guardada->id])->save();
                }

                $this->setRelation('weeklyGoal', $guardada);

                return;
            }
        }

        $this->vincularMetaSemanal();
    }

    /** Deja el vínculo apuntando a la meta que corresponde a la semana del día. */
    protected function vincularMetaSemanal(): void
    {
        if (! $this->plan_date) {
            return;
        }

        $meta = WeeklyGoal::forDate($this->plan_date);
        $id = $meta?->id;

        if ((int) $this->weekly_goal_id !== (int) ($id ?? 0)) {
            $this->forceFill(['weekly_goal_id' => $id])->save();
        }

        $this->setRelation('weeklyGoal', $meta);
    }

    /**
     * Actividades del día: marca, minutos y nota.
     *
     * La lista enviada no es obligatoria: lo que no venga se queda como estaba
     * (así el botón de la vista de sólo lectura puede mandar sólo una parte).
     * Lo que sí se limpia es lo que dejó de aplicar al día si cambió la fecha:
     * el cambio de sábanas sólo pertenece al sábado.
     */
    protected function syncActivities(array $actividades): void
    {
        if (! $this->plan_date) {
            return;
        }

        $ids = collect($actividades)
            ->pluck('daily_activity_id')
            ->filter()
            ->map(fn (mixed $id) => (int) $id)
            ->unique()
            ->all();

        $catalogo = DailyActivity::query()->whereIn('id', $ids)->get()->keyBy('id');

        foreach ($actividades as $fila) {
            $id = (int) ($fila['daily_activity_id'] ?? 0);
            /** @var DailyActivity|null $actividad */
            $actividad = $catalogo->get($id);

            if (! $actividad) {
                continue;
            }

            $hecho = (bool) ($fila['is_done'] ?? false);
            $pivote = ['is_done' => $hecho];

            if (array_key_exists('minutes', $fila)) {
                $minutos = $hecho && $actividad->requires_minutes
                    ? max(0, min(1440, (int) ($fila['minutes'] ?: 0)))
                    : null;

                $pivote['minutes'] = $minutos ?: null;
            }

            if (array_key_exists('note', $fila)) {
                $pivote['note'] = Helper::strip($fila['note']) ?: null;
            }

            $this->activities()->syncWithoutDetaching([$id => $pivote]);
        }

        // Si el día cambió de fecha, las actividades que ya no le tocan se van.
        $sobrantes = $this->activities()
            ->get()
            ->reject(fn (DailyActivity $actividad) => $actividad->aplicaA($this->plan_date))
            ->pluck('id')
            ->all();

        if ($sobrantes !== []) {
            $this->activities()->detach($sobrantes);
        }
    }

    /**
     * Reflexión de la semana: sólo se guarda el domingo, que es cuando se
     * cierra la semana. Se identifica por el lunes de esa semana.
     */
    protected function syncWeeklyReflection(array $respuestas): void
    {
        if (! $this->plan_date || ! $this->plan_date->isSunday()) {
            return;
        }

        $semana = Helper::startOfWeek($this->plan_date);

        if (! $semana) {
            return;
        }

        $valores = [];

        foreach (array_keys(WeeklyReflection::PREGUNTAS) as $clave) {
            if (array_key_exists($clave, $respuestas)) {
                $texto = trim((string) ($respuestas[$clave] ?? ''));
                $valores[$clave] = $texto !== '' ? $texto : null;
            }
        }

        if ($valores === []) {
            return;
        }

        $existente = WeeklyReflection::forDate($this->plan_date);
        $tieneTexto = collect($valores)->contains(fn (mixed $valor) => $valor !== null);

        if (! $existente && ! $tieneTexto) {
            return;
        }

        WeeklyReflection::updateOrCreate(['week_start' => $semana->toDateString()], $valores);
    }

    /** Crea las filas del checklist "ANTES DE EMPEZAR" que falten. */
    protected function generatePreparationItems(): void
    {
        $items = PreparationItem::active()
            ->pluck('id')
            ->mapWithKeys(fn (int $id) => [$id => ['is_checked' => false]]);

        if ($items->isNotEmpty()) {
            $this->preparationItems()->syncWithoutDetaching($items->all());
        }
    }

    /** Crea las respuestas vacías de las preguntas de reflexión activas. */
    protected function generateReflectionAnswers(): void
    {
        ReflectionQuestion::active()->each(function (ReflectionQuestion $question) {
            $this->reflectionAnswers()->firstOrCreate(
                ['reflection_question_id' => $question->id],
                ['is_checked' => false]
            );
        });
    }

    /**
     * Crea las filas de las actividades que aplican a este día.
     *
     * Las de todos los días van siempre; las de la semana (el cambio de sábanas
     * y toallas del sábado) sólo el día que les toca. Es idempotente: no repite
     * la que ya está.
     */
    protected function generateActivities(): void
    {
        $fecha = $this->plan_date;

        if (! $fecha) {
            return;
        }

        $actividades = DailyActivity::active()
            ->forWeekday((int) $fecha->dayOfWeek)
            ->get(['id']);

        if ($actividades->isEmpty()) {
            return;
        }

        $existentes = $this->activities()->pluck('daily_activities.id')->all();

        $faltantes = $actividades
            ->reject(fn (DailyActivity $actividad) => in_array($actividad->id, $existentes, true))
            ->mapWithKeys(fn (DailyActivity $actividad) => [$actividad->id => ['is_done' => false]]);

        if ($faltantes->isNotEmpty()) {
            $this->activities()->syncWithoutDetaching($faltantes->all());
        }
    }

    /* ======================================================================
     |  Internos: sincronización con el formulario
     ====================================================================== */

    /** Aplica los datos del formulario. Solo procesa las claves presentes. */
    protected function applyDayData(array $data): void
    {
        if (array_key_exists('weekly_goal', $data)) {
            $this->syncWeeklyGoal((array) $data['weekly_goal']);
        }

        if (array_key_exists('goals', $data)) {
            $this->syncGoals((array) $data['goals']);
        }

        if (array_key_exists('schedule', $data)) {
            $this->syncScheduleActivities((array) $data['schedule']);
        }

        if (array_key_exists('activities', $data)) {
            $this->syncActivities((array) $data['activities']);
        }

        if (array_key_exists('weekly_reflection', $data)) {
            $this->syncWeeklyReflection((array) $data['weekly_reflection']);
        }

        if (array_key_exists('preparation', $data)) {
            $this->syncPreparationItems((array) $data['preparation']);
        }

        if (array_key_exists('reflections', $data)) {
            $this->syncReflections((array) $data['reflections']);
        }

        if (array_key_exists('notes', $data)) {
            $this->syncNotes((array) $data['notes']);
        }

        if (array_key_exists('action_blocks', $data)) {
            $this->syncActionBlocks((array) $data['action_blocks']);
        }
    }

    /** Objetivos: uno por ranura. Descripción vacía = la ranura se libera. */
    protected function syncGoals(array $goals): void
    {
        foreach ($goals as $goal) {
            $slot = (int) ($goal['slot'] ?? 0);

            if ($slot < 1 || $slot > self::MAX_GOALS) {
                continue;
            }

            $description = Helper::strip($goal['description'] ?? null);

            if ($description === '') {
                $this->goals()->where('slot', $slot)->delete();

                continue;
            }

            $isDone = (bool) ($goal['is_done'] ?? false);

            $model = $this->goals()->firstOrNew(['slot' => $slot]);
            $model->goal_type_id = $goal['goal_type_id'] ?? null;
            $model->description = $description;
            $model->is_done = $isDone;
            $model->completed_at = $isDone ? ($model->completed_at ?? now()) : null;
            $model->save();
        }
    }

    /**
     * Horario: la lista enviada es la definitiva. Se actualizan las franjas que
     * traen id y se crean las nuevas; las que ya no vienen en la lista se borran.
     */
    protected function syncScheduleActivities(array $entries): void
    {
        $kept = [];

        foreach ($entries as $entry) {
            $attributes = [];

            if (array_key_exists('start_time', $entry)) {
                $attributes['start_time'] = $entry['start_time'] ?: null;
            }

            if (array_key_exists('schedule_slot_id', $entry)) {
                $attributes['schedule_slot_id'] = $entry['schedule_slot_id'] ?: null;
            }

            if (array_key_exists('activity', $entry)) {
                $attributes['activity'] = Helper::strip($entry['activity']) ?: null;
            }

            if (array_key_exists('is_done', $entry)) {
                $attributes['is_done'] = (bool) $entry['is_done'];
            }

            $model = ! empty($entry['id'])
                ? $this->scheduleEntries()->find($entry['id'])
                : null;

            if ($model) {
                $model->update($attributes);
            } else {
                $model = $this->scheduleEntries()->create($attributes);
            }

            $kept[] = $model->id;
        }

        $this->scheduleEntries()->whereNotIn('id', $kept)->delete();
    }

    /**
     * Checklist "ANTES DE EMPEZAR": marca o desmarca cada ítem y guarda su
     * descripción libre ("PC, cuaderno, calculadora").
     */
    protected function syncPreparationItems(array $items): void
    {
        foreach ($items as $item) {
            $itemId = $item['preparation_item_id'] ?? null;

            if (! $itemId) {
                continue;
            }

            $pivot = ['is_checked' => (bool) ($item['is_checked'] ?? false)];

            if (array_key_exists('preparation_items_description', $item)) {
                $pivot['preparation_items_description'] =
                    Helper::strip($item['preparation_items_description']) ?: null;
            }

            $this->preparationItems()->syncWithoutDetaching([$itemId => $pivot]);
        }
    }

    /** Reflexiones: guarda el check y la respuesta corta de cada pregunta. */
    protected function syncReflections(array $reflections): void
    {
        foreach ($reflections as $reflection) {
            $questionId = $reflection['reflection_question_id'] ?? null;

            if (! $questionId) {
                continue;
            }

            $model = $this->reflectionAnswers()->firstOrNew(['reflection_question_id' => $questionId]);

            if (array_key_exists('is_checked', $reflection)) {
                $model->is_checked = (bool) $reflection['is_checked'];
            }

            if (array_key_exists('answer', $reflection)) {
                $model->answer = Helper::strip($reflection['answer']) ?: null;
            }

            $model->save();
        }
    }

    /**
     * Notas: la lista enviada es la definitiva, se reemplaza la del día.
     * Se recorta sin colapsar los saltos de línea, porque el formulario manda
     * un único campo de texto múltiple.
     */
    protected function syncNotes(array $notes): void
    {
        $this->notes()->delete();

        $order = 1;

        foreach ($notes as $note) {
            $content = trim((string) ($note['content'] ?? ''));

            if ($content === '') {
                continue;
            }

            $this->notes()->create([
                'content' => $content,
                'sort_order' => (int) ($note['sort_order'] ?? $order),
            ]);

            $order++;
        }
    }

    /** Bloques de acción: se actualizan los que traen id y se crean los nuevos. */
    protected function syncActionBlocks(array $blocks): void
    {
        $kept = [];

        foreach ($blocks as $block) {
            // El formulario siempre envía la estructura action_blocks[0], aun
            // cuando el usuario no complete esta sección opcional. No creemos
            // una fila vacía por ese mero hecho.
            $hasContent = collect([
                $block['action_block_duration_id'] ?? null,
                $block['action_block_outcome_id'] ?? null,
                Helper::strip($block['task'] ?? null),
                $block['started_at'] ?? null,
                $block['finished_at'] ?? null,
            ])->contains(fn (mixed $value) => $value !== null && $value !== '');

            if (! $hasContent) {
                continue;
            }

            $attributes = array_filter([
                'action_block_duration_id' => $block['action_block_duration_id'] ?? null,
                'action_block_outcome_id' => $block['action_block_outcome_id'] ?? null,
                'task' => Helper::strip($block['task'] ?? null) ?: null,
                'started_at' => $block['started_at'] ?? null,
                'finished_at' => $block['finished_at'] ?? null,
            ], fn (mixed $value) => $value !== null);

            $model = ! empty($block['id'])
                ? $this->actionBlocks()->find($block['id'])
                : null;

            if ($model) {
                $model->update($attributes);
            } else {
                $model = $this->actionBlocks()->create($attributes);
            }

            $kept[] = $model->id;
        }

        // Los bloques que ya no vienen en la lista se eliminan.
        $this->actionBlocks()->whereNotIn('id', $kept)->delete();
    }
}
