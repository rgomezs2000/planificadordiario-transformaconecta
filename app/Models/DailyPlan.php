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
        'goals.goalType',
        'scheduleEntries.scheduleSlot',
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
                'achievements' => $data['achievements'] ?? null,
                'pending' => $data['pending'] ?? null,
                'pending_when' => $data['pending_when'] ?? null,
                'proud_of' => $data['proud_of'] ?? null,
            ]);

            $plan->refreshDayStructure();
            $plan->applyDayData($data);

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

            foreach (['energy_level_id', 'achievements', 'pending', 'pending_when', 'proud_of'] as $key) {
                if (array_key_exists($key, $data)) {
                    $attributes[$key] = $data[$key];
                }
            }

            if ($attributes !== []) {
                $this->update($attributes);
            }

            $this->applyDayData($data);

            // El PDF y el JPG guardados ya no representan al día: se borran y
            // se vuelven a generar la próxima vez que se pidan.
            DocumentoDelDiario::de($this)->limpiar();

            return $this->fresh(self::FULL_RELATIONS);
        }, 'modificar el día', ['diario_id' => $this->id]);
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
    }

    /* ======================================================================
     |  Resúmenes para las respuestas
     ====================================================================== */

    /** Resumen de avance del día (objetivos, horario y minutos de foco). */
    public function progressSummary(): array
    {
        $goalsDone = $this->goals->where('is_done', true)->count();
        $goalsTotal = $this->goals->count();
        $entriesDone = $this->scheduleEntries->where('is_done', true)->count();
        $entriesTotal = $this->scheduleEntries->count();
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

        return [
            // Se usa para el formato de fecha de la primera columna.
            'date' => $this->plan_date,

            // Las celdas, en el mismo orden que las columnas del reporte.
            'cells' => [
                Helper::dayName($this->plan_date, capitalize: true),
                $this->energyLevel?->name,

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

    /* ======================================================================
     |  Internos: sincronización con el formulario
     ====================================================================== */

    /** Aplica los datos del formulario. Solo procesa las claves presentes. */
    protected function applyDayData(array $data): void
    {
        if (array_key_exists('goals', $data)) {
            $this->syncGoals((array) $data['goals']);
        }

        if (array_key_exists('schedule', $data)) {
            $this->syncScheduleActivities((array) $data['schedule']);
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
