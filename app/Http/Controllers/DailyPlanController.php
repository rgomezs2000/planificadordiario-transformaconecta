<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Models\ActionBlockDuration;
use App\Models\ActionBlockOutcome;
use App\Models\DailyPlan;
use App\Models\EnergyLevel;
use App\Models\GoalType;
use App\Models\PreparationItem;
use App\Models\ReflectionQuestion;
use App\Models\ScheduleSlot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

/**
 * Controlador del planificador diario.
 *
 * Operaciones: listar, crear, mostrar, modificar, imprimir y eliminar.
 * Las consultas y manipulaciones de base de datos viven en el modelo
 * DailyPlan (y en los modelos de catálogo), aquí solo se invocan.
 *
 * Las acciones que el sistema consume por AJAX devuelven JSON con el sobre
 * { ok, message, data } / { ok, message, errors }; las que se navegan
 * devuelven vistas o redirecciones.
 */
class DailyPlanController extends Controller
{
    /* ======================================================================
     |  Listar
     ====================================================================== */

    /** Listado paginado de días (vista). */
    public function index(Request $request)
    {
        $filters = $this->filters($request);

        return view('diario.index', [
            'plans' => DailyPlan::paginateList($filters, $this->perPage($request)),
            'filters' => $filters,
            'rangeLabel' => Helper::rangeLabel($filters['from'], $filters['to']),
            'energyLevels' => EnergyLevel::active()->get(),
        ]);
    }

    /** Listado paginado de días (JSON para AJAX). */
    public function list(Request $request): JsonResponse
    {
        $plans = DailyPlan::paginateList($this->filters($request), $this->perPage($request));

        return $this->jsonSuccess([
            'plans' => $plans->getCollection()->map->toListArray()->all(),
            'pagination' => $this->paginationMeta($plans),
        ]);
    }

    /* ======================================================================
     |  Mostrar
     ====================================================================== */

    /** Un día concreto (vista). */
    public function show(DailyPlan $dailyPlan)
    {
        return view('diario.show', [
            'plan' => $dailyPlan->loadFull(),
        ]);
    }

    /** Un día concreto (JSON). */
    public function detail(DailyPlan $dailyPlan): JsonResponse
    {
        return $this->jsonSuccess([
            'plan' => $dailyPlan->toDetailArray(),
        ]);
    }

    /**
     * El día de hoy (JSON).
     *
     * Informa si ya existe para que la interfaz ofrezca crearlo o abrirlo.
     */
    public function today(): JsonResponse
    {
        $plan = DailyPlan::findToday();

        return $this->jsonSuccess([
            'date' => Helper::date(now(), 'Y-m-d'),
            'date_label' => Helper::longDate(now(), withWeekday: true),
            'exists' => $plan !== null,
            'plan' => $plan?->toDetailArray(),
        ]);
    }

    /* ======================================================================
     |  Crear
     ====================================================================== */

    /**
     * Formulario de creación para una fecha (?fecha=Y-m-d, por defecto hoy).
     *
     * Si esa fecha ya tiene diario no se duplica: se redirige a modificar.
     */
    public function create(Request $request)
    {
        $date = Helper::toCarbon($request->input('fecha')) ?? Helper::today();

        if ($existing = DailyPlan::findByDate($date)) {
            return redirect()
                ->route('diario.edit', $existing)
                ->with('info', 'El diario del '.Helper::longDate($date, withWeekday: true).' ya existe. Puedes modificarlo.');
        }

        return view('diario.create', array_merge($this->catalogs(), [
            'plan_date' => $date->toDateString(),
            'dateLabel' => Helper::longDate($date, withWeekday: true),
            'weekLabels' => Helper::weekLabels(),
        ]));
    }

    /**
     * Guarda un día nuevo con toda su estructura (JSON).
     *
     * Una fecha ya registrada la rechaza la regla unique de plan_date,
     * así que el error llega como validación (422) con el sobre uniforme.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validateDay($request);

        $plan = DailyPlan::createDay($data);

        return $this->jsonSuccess(
            ['plan' => $plan->toDetailArray()],
            'Diario del '.Helper::longDate($plan->plan_date, withWeekday: true).' creado correctamente.',
            201
        );
    }

    /* ======================================================================
     |  Modificar
     ====================================================================== */

    /** Formulario de edición de un día. */
    public function edit(DailyPlan $dailyPlan)
    {
        return view('diario.edit', array_merge($this->catalogs(), [
            'plan' => $dailyPlan->loadFull(),
            'weekLabels' => Helper::weekLabels(),
        ]));
    }

    /** Actualiza un día. Solo se toca lo que venga en la petición (JSON). */
    public function update(Request $request, DailyPlan $dailyPlan): JsonResponse
    {
        $data = $this->validateDay($request, $dailyPlan);

        // Una petición sin ninguna clave válida no debe provocar un UPDATE vacío.
        if ($data === []) {
            return $this->jsonError('No se recibió ningún cambio para el diario.');
        }

        $plan = $dailyPlan->updateDay($data);

        return $this->jsonSuccess(
            ['plan' => $plan->toDetailArray()],
            'Diario del '.Helper::longDate($plan->plan_date, withWeekday: true).' actualizado correctamente.'
        );
    }

    /* ======================================================================
     |  Imprimir
     ====================================================================== */

    /**
     * PDF del día.
     *
     * El paquete barryvdh/laravel-dompdf registra el servicio "dompdf.wrapper".
     * Si está instalado se devuelve el PDF descargable; si no, se entrega la
     * plantilla imprimible para usar el diálogo de impresión del navegador:
     *
     *     composer require barryvdh/laravel-dompdf
     */
    public function printPdf(DailyPlan $dailyPlan)
    {
        $plan = $dailyPlan->loadFull();
        $printView = view('diario.pdf', ['plan' => $plan]);

        if (app()->bound('dompdf.wrapper')) {
            return app('dompdf.wrapper')
                ->loadHTML($printView->render())
                ->setPaper('letter')
                ->download($this->pdfFileName($plan));
        }

        return $printView;
    }

    /* ======================================================================
     |  Eliminar
     ====================================================================== */

    /** Elimina un día y, en cascada, todas sus secciones (JSON). */
    public function destroy(DailyPlan $dailyPlan): JsonResponse
    {
        $label = Helper::longDate($dailyPlan->plan_date, withWeekday: true);

        $dailyPlan->deleteDay();

        return $this->jsonSuccess(null, 'Diario del '.$label.' eliminado correctamente.');
    }

    /* ======================================================================
     |  Internos auxiliares
     ====================================================================== */

    /** Filtros del listado, con los nombres de parámetro que usa la interfaz. */
    private function filters(Request $request): array
    {
        return [
            'from' => $request->input('desde'),
            'to' => $request->input('hasta'),
            'energy_level_id' => $request->input('energia'),
            'search' => $request->input('buscar'),
        ];
    }

    /** Tamaño de página, acotado entre 1 y 100. */
    private function perPage(Request $request): int
    {
        return min(max((int) $request->input('por_pagina', 15), 1), 100);
    }

    /** Catálogos que alimentan el formulario del día. */
    private function catalogs(): array
    {
        return [
            'energyLevels' => EnergyLevel::active()->get(),
            'goalTypes' => GoalType::active()->get(),
            'scheduleSlots' => ScheduleSlot::active()->get(),
            'preparationItems' => PreparationItem::active()->get(),
            'reflectionQuestions' => ReflectionQuestion::active()
                ->category(ReflectionQuestion::CATEGORY_PROCRASTINATION)
                ->get(),
            'actionBlockDurations' => ActionBlockDuration::active()->get(),
            'actionBlockOutcomes' => ActionBlockOutcome::active()->get(),
        ];
    }

    /** Valida la petición y devuelve solo los datos validados. */
    private function validateDay(Request $request, ?DailyPlan $plan = null): array
    {
        return $request->validate($this->rules($plan), $this->messages());
    }

    /** Reglas de validación del día completo (cabecera, secciones y cierre). */
    private function rules(?DailyPlan $plan = null): array
    {
        $dateRules = [$plan ? 'sometimes' : 'required', 'date'];

        $dateRules[] = $plan
            ? Rule::unique('daily_plans', 'plan_date')->ignore($plan->id)
            : Rule::unique('daily_plans', 'plan_date');

        return [
            // Cabecera y cierre del día
            'plan_date' => $dateRules,
            'energy_level_id' => ['nullable', 'integer', 'exists:energy_levels,id'],
            'achievements' => ['nullable', 'string', 'max:2000'],
            'pending' => ['nullable', 'string', 'max:2000'],
            'pending_when' => ['nullable', 'string', 'max:255'],
            'proud_of' => ['nullable', 'string', 'max:2000'],

            // Mis 3 objetivos principales
            'goals' => ['nullable', 'array', 'max:'.DailyPlan::MAX_GOALS],
            'goals.*.slot' => ['required_with:goals', 'integer', 'between:1,'.DailyPlan::MAX_GOALS, 'distinct'],
            'goals.*.goal_type_id' => ['nullable', 'integer', 'exists:goal_types,id'],
            'goals.*.description' => ['nullable', 'string', 'max:255'],
            'goals.*.is_done' => ['nullable', 'boolean'],

            // Mi horario de hoy
            'schedule' => ['nullable', 'array'],
            'schedule.*.schedule_slot_id' => ['required_with:schedule', 'integer', 'exists:schedule_slots,id', 'distinct'],
            'schedule.*.activity' => ['nullable', 'string', 'max:255'],
            'schedule.*.is_done' => ['nullable', 'boolean'],

            // Antes de empezar
            'preparation' => ['nullable', 'array'],
            'preparation.*.preparation_item_id' => ['required_with:preparation', 'integer', 'exists:preparation_items,id', 'distinct'],
            'preparation.*.is_checked' => ['nullable', 'boolean'],

            // Si estoy procrastinando
            'reflections' => ['nullable', 'array'],
            'reflections.*.reflection_question_id' => ['required_with:reflections', 'integer', 'exists:reflection_questions,id', 'distinct'],
            'reflections.*.is_checked' => ['nullable', 'boolean'],
            'reflections.*.answer' => ['nullable', 'string', 'max:2000'],

            // Bloque de acción
            'action_blocks' => ['nullable', 'array'],
            'action_blocks.*.id' => ['nullable', 'integer', 'exists:action_blocks,id'],
            'action_blocks.*.action_block_duration_id' => ['nullable', 'integer', 'exists:action_block_durations,id'],
            'action_blocks.*.action_block_outcome_id' => ['nullable', 'integer', 'exists:action_block_outcomes,id'],
            'action_blocks.*.task' => ['nullable', 'string', 'max:255'],
            'action_blocks.*.started_at' => ['nullable', 'date'],
            'action_blocks.*.finished_at' => ['nullable', 'date', 'after_or_equal:action_blocks.*.started_at'],

            // Notas y recordatorios
            'notes' => ['nullable', 'array'],
            'notes.*.content' => ['nullable', 'string', 'max:1000'],
            'notes.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /** Mensajes de validación en español. */
    private function messages(): array
    {
        return [
            'plan_date.required' => 'Indica la fecha del diario.',
            'plan_date.date' => 'La fecha no tiene un formato válido.',
            'plan_date.unique' => 'Ya existe un diario con esa fecha.',

            'energy_level_id.exists' => 'El nivel de energía seleccionado no existe.',

            'goals.max' => 'Solo se permiten 3 objetivos principales.',
            'goals.*.slot.required_with' => 'Cada objetivo necesita su ranura (1, 2 o 3).',
            'goals.*.slot.between' => 'Las ranuras de objetivo van de 1 a 3.',
            'goals.*.slot.distinct' => 'No se puede repetir la misma ranura de objetivo.',
            'goals.*.goal_type_id.exists' => 'El tipo de objetivo seleccionado no existe.',
            'goals.*.description.max' => 'La descripción del objetivo es demasiado larga.',

            'schedule.*.schedule_slot_id.required_with' => 'Cada fila del horario necesita su franja.',
            'schedule.*.schedule_slot_id.exists' => 'La franja horaria seleccionada no existe.',
            'schedule.*.schedule_slot_id.distinct' => 'No se puede repetir la misma franja horaria.',

            'preparation.*.preparation_item_id.required_with' => 'Cada ítem del checklist necesita su identificador.',
            'preparation.*.preparation_item_id.exists' => 'El ítem del checklist no existe.',

            'reflections.*.reflection_question_id.required_with' => 'Cada reflexión necesita su pregunta.',
            'reflections.*.reflection_question_id.exists' => 'La pregunta de reflexión no existe.',

            'action_blocks.*.id.exists' => 'El bloque de acción que intentas modificar no existe.',
            'action_blocks.*.action_block_duration_id.exists' => 'La duración del bloque no existe.',
            'action_blocks.*.action_block_outcome_id.exists' => 'El resultado del bloque no existe.',
            'action_blocks.*.finished_at.after_or_equal' => 'El bloque no puede terminar antes de empezar.',

            'notes.*.content.max' => 'La nota es demasiado larga.',
        ];
    }

    /** Sobre de respuesta correcta. */
    private function jsonSuccess(
        mixed $data = null,
        string $message = 'Operación realizada correctamente.',
        int $status = 200
    ): JsonResponse {
        return response()->json([
            'ok' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /** Sobre de respuesta con error. */
    private function jsonError(string $message, array $errors = [], int $status = 422): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }

    /** Metadatos de paginación para las respuestas JSON. */
    private function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }

    /** Nombre del archivo PDF del día. */
    private function pdfFileName(DailyPlan $plan): string
    {
        return 'planificador-diario-'.Helper::date($plan->plan_date, 'Y-m-d').'.pdf';
    }
}
