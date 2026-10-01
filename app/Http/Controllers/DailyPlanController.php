<?php

namespace App\Http\Controllers;

use App\Excel\ReporteDiarioExport;
use App\Helpers\Helper;
use App\Http\Controllers\Concerns\DatosDelFormulario;
use App\Models\DailyPlan;
use App\Models\EnergyLevel;
use Barryvdh\DomPDF\Facade\Pdf;
use Dompdf\Dompdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Controlador del planificador diario.
 *
 * Listar, crear, mostrar, modificar, imprimir y eliminar. Las consultas y
 * manipulaciones de base de datos viven en el modelo DailyPlan; aquí sólo se
 * invocan y se arma la respuesta.
 *
 * Las acciones de AJAX devuelven el sobre { ok, message, data } /
 * { ok, message, errors }. Los errores se atrapan con try/catch y se cuentan
 * al cliente para que los muestre en una alerta de bootbox.
 */
class DailyPlanController extends Controller
{
    use DatosDelFormulario;

    /* ======================================================================
     |  Listar
     ====================================================================== */

    /**
     * Página del listado. La tabla la llena DataTables por AJAX desde
     * diario.tabla, así que no se le pasa nada.
     */
    public function index()
    {
        return view('diario.index', [
            // Catálogos del miniformulario de filtros que va debajo de la tabla.
            'energyLevels' => EnergyLevel::active()->get(),
            'weekDays' => $this->weekDays(),
        ]);
    }

    /**
     * Todos los días registrados en JSON (GET).
     *
     * Acepta los filtros del miniformulario, que viajan como parámetros de la
     * dirección: fecha (dd/mm/aaaa), energia (baja, media o alta) y palabra
     * (basta con una letra). Se pueden combinar entre sí.
     */
    public function list(Request $request): JsonResponse
    {
        try {
            $filtros = [
                'fecha' => $request->query('fecha'),
                'energia' => $request->query('energia'),
                'palabra' => $request->query('palabra'),
            ];

            $plans = DailyPlan::allForList($filtros);

            return $this->jsonSuccess([
                'plans' => $plans->map->toListArray()->all(),
                'total' => $plans->count(),
                // Se devuelven los filtros que realmente se aplicaron, para que
                // el navegador pueda avisar qué se está mostrando.
                'filtros' => array_filter(
                    $filtros,
                    fn (mixed $valor) => Helper::strip($valor) !== ''
                ),
            ]);
        } catch (Throwable $e) {
            report($e);

            return $this->jsonError('No se pudo obtener el listado de diarios.', [], 500);
        }
    }

    /* ======================================================================
     |  Mostrar y modificar (reutilizan el formulario)
     ====================================================================== */

    /** Un día en sólo lectura. */
    public function show(int $dailyPlan)
    {
        $plan = DailyPlan::find($dailyPlan);

        if (! $plan) {
            return redirect()
                ->route('diario.listado')
                ->with('error', 'El diario que intentas ver no existe.');
        }

        try {
            return view('diario.formulario', $this->datosFormulario($plan->loadFull(), 'ver'));
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('diario.listado')
                ->with('error', 'No se pudo abrir el diario.');
        }
    }

    /** Un día listo para modificar. */
    public function edit(int $dailyPlan)
    {
        $plan = DailyPlan::find($dailyPlan);

        if (! $plan) {
            return redirect()
                ->route('diario.listado')
                ->with('error', 'El diario que intentas modificar no existe.');
        }

        try {
            return view('diario.formulario', $this->datosFormulario($plan->loadFull(), 'editar'));
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('diario.listado')
                ->with('error', 'No se pudo abrir el diario para modificarlo.');
        }
    }

    /**
     * Un día en JSON (GET).
     *
     * Lo usa el flujo de impresión para comprobar que el diario existe antes de
     * generar el PDF, así el aviso sale en una alerta y no en una pestaña nueva.
     */
    public function detail(int $dailyPlan): JsonResponse
    {
        try {
            $plan = DailyPlan::find($dailyPlan);

            if (! $plan) {
                return $this->jsonError('El diario que buscas no existe.', [], 404);
            }

            return $this->jsonSuccess(['plan' => $plan->toDetailArray()]);
        } catch (Throwable $e) {
            report($e);

            return $this->jsonError('No se pudo consultar el diario.', [], 500);
        }
    }

    /* ======================================================================
     |  Crear
     ====================================================================== */

    /**
     * Guarda un día nuevo con toda su estructura (POST).
     *
     * La validación va fuera del try/catch a propósito: si falla, Laravel
     * responde 422 con el detalle por campo y el formulario lo muestra.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validateDay($request);

        try {
            $plan = DailyPlan::createDay($data);

            return $this->jsonSuccess(
                ['plan' => $plan->toDetailArray()],
                'Diario del '.Helper::longDate($plan->plan_date, withWeekday: true).' registrado correctamente.',
                201
            );
        } catch (Throwable $e) {
            report($e);

            return $this->jsonError('No se pudo guardar el diario. Intenta de nuevo.', [], 500);
        }
    }

    /* ======================================================================
     |  Modificar
     ====================================================================== */

    /** Actualiza un día (PUT). */
    public function update(Request $request, int $dailyPlan): JsonResponse
    {
        $plan = null;

        try {
            $plan = DailyPlan::find($dailyPlan);
        } catch (Throwable $e) {
            report($e);
        }

        if (! $plan) {
            return $this->jsonError('El diario que intentas modificar no existe.', [], 404);
        }

        // Se valida después de comprobar que existe: así un id inexistente
        // responde 404 y no un error de campos.
        $data = $this->validateDay($request, $dailyPlan);

        try {
            $plan = $plan->updateDay($data);

            return $this->jsonSuccess(
                ['plan' => $plan->toDetailArray()],
                'Diario del '.Helper::longDate($plan->plan_date, withWeekday: true).' actualizado correctamente.'
            );
        } catch (Throwable $e) {
            report($e);

            return $this->jsonError('No se pudo actualizar el diario. Intenta de nuevo.', [], 500);
        }
    }

    /* ======================================================================
     |  Eliminar
     ====================================================================== */

    /** Elimina un día y, en cascada, todas sus secciones (DELETE). */
    public function destroy(int $dailyPlan): JsonResponse
    {
        try {
            $plan = DailyPlan::find($dailyPlan);

            if (! $plan) {
                return $this->jsonError('El diario que intentas eliminar no existe.', [], 404);
            }

            $etiqueta = Helper::longDate($plan->plan_date, withWeekday: true);
            $plan->deleteDay();

            return $this->jsonSuccess(null, 'Diario del '.$etiqueta.' eliminado correctamente.');
        } catch (Throwable $e) {
            report($e);

            return $this->jsonError('No se pudo eliminar el diario. Intenta de nuevo.', [], 500);
        }
    }

    /* ======================================================================
     |  Imprimir
     ====================================================================== */

    /**
     * PDF del día.
     *
     * Con ?marca=1 sale con la marca de agua diagonal SPECIMEN.
     * Si el diario no existe responde 404 en JSON, y el listado lo avisa en una
     * alerta antes de abrir esta dirección.
     */
    public function printPdf(Request $request, int $dailyPlan)
    {
        try {
            $plan = DailyPlan::find($dailyPlan);

            if (! $plan) {
                return $this->jsonError('El diario que intentas imprimir no existe.', [], 404);
            }

            $marca = $request->boolean('marca');
            $plan->loadFull();

            $html = view('diario.pdf', [
                'plan' => $plan,
                'marca' => $marca,
            ])->render();

            $dompdf = Pdf::loadHTML($html)->setPaper('letter')->getDomPDF();
            $dompdf->render();

            if ($marca) {
                $this->marcarComoMuestra($dompdf);
            }

            // Sin caché: el PDF se pide siempre con la misma dirección, y si el
            // navegador lo guardara mostraría la versión anterior del diario.
            return response($dompdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$this->pdfFileName($plan, $marca).'"',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
            ]);
        } catch (Throwable $e) {
            report($e);

            return $this->jsonError('No se pudo generar el PDF del diario.', [], 500);
        }
    }

    /**
     * Reporte detallado en Excel (.xlsx) · GET.
     *
     * Sale con los mismos filtros del buscador, así el archivo trae exactamente
     * los diarios que se están viendo en la tabla. Un diario por fila, con la
     * fecha, la energía, el "antes de empezar", las preguntas de
     * procrastinación, el bloque de acción, el cierre del día y las notas.
     *
     * Si no hay diarios que coincidan contesta en JSON con el aviso, para que
     * el navegador lo muestre en una alerta en vez de descargar un archivo
     * vacío.
     */
    public function reporte(Request $request)
    {
        try {
            $filtros = [
                'fecha' => $request->query('fecha'),
                'energia' => $request->query('energia'),
                'palabra' => $request->query('palabra'),
            ];

            $plans = DailyPlan::forReport($filtros);

            if ($plans->isEmpty()) {
                return $this->jsonError(
                    'No hay diarios que coincidan con la búsqueda, así que no hay nada que exportar.',
                    [],
                    404
                );
            }

            $contenido = ReporteDiarioExport::generar($plans, $filtros);

            return response($contenido, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'
                    .ReporteDiarioExport::nombreArchivo($filtros).'"',
                'Content-Length' => (string) strlen($contenido),
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
            ]);
        } catch (Throwable $e) {
            report($e);

            return $this->jsonError('No se pudo generar el reporte detallado.', [], 500);
        }
    }

    /**
     * Marca de agua SPECIMEN, centrada y en diagonal, sobre todas las páginas.
     *
     * Se dibuja con el lienzo de dompdf porque el PDF no admite rotar texto con
     * CSS. El ángulo va en negativo a propósito: dompdf compone la matriz de
     * rotación como [cos, -sin, sin, cos], así que un ángulo positivo haría
     * bajar la marca de izquierda a derecha. Con -45 sube, como se pidió.
     *
     * La posición se calcula a mano: se resta media longitud del texto en la
     * dirección de la diagonal para que el centro caiga en el centro de la
     * página, y se descuenta la altura de la fuente porque el texto se coloca
     * por su parte de arriba, no por su línea base.
     *
     * El color es gris oscuro, pero con muy poca opacidad: así sobre el papel
     * blanco queda un gris clarísimo y sobre el texto negro casi no se nota. Si
     * se pintara un gris claro opaco, taparía las letras del documento.
     */
    private function marcarComoMuestra(Dompdf $dompdf): void
    {
        $canvas = $dompdf->getCanvas();
        $metricas = $dompdf->getFontMetrics();
        $fuente = $metricas->getFont('Helvetica', 'bold');

        $texto = 'SPECIMEN';
        $tamano = 88;
        $angulo = -45;
        $color = [0.28, 0.28, 0.28];
        $transparencia = 0.16;

        $ancho = $canvas->get_width();
        $alto = $canvas->get_height();

        $largo = $metricas->getTextWidth($texto, $fuente, $tamano);

        // Ojo: CPDF coloca el texto descontando la altura de fuente SIN el
        // factor de interlineado que aplica getFontHeight(). Se divide por ese
        // factor para descontar exactamente lo mismo y no quedar descentrado.
        $altura = $metricas->getFontHeight($fuente, $tamano)
            / $dompdf->getOptions()->getFontHeightRatio();

        $radianes = deg2rad(abs($angulo));
        $avanceX = cos($radianes);
        $avanceY = sin($radianes);

        // El centro óptico de las mayúsculas va media altura por encima de la
        // línea base, así que la base baja ese tanto para quedar centrada.
        $centroX = $ancho / 2;
        $centroY = ($alto / 2) + ($tamano * 0.36);

        $x = $centroX - ($largo / 2) * $avanceX;
        $y = $centroY + ($largo / 2) * $avanceY - $altura;

        // Se dibuja con page_script y no con page_text porque en un PDF la
        // transparencia es un estado de dibujo: afecta a lo que se pinta
        // DESPUÉS de fijarla. Aquí se fija en cada página, justo antes de la
        // marca, y se devuelve a 1 para no afectar al resto.
        $canvas->page_script(
            function ($numero, $total, $lienzo) use ($x, $y, $texto, $fuente, $tamano, $color, $angulo, $transparencia) {
                $lienzo->set_opacity($transparencia);
                $lienzo->text($x, $y, $texto, $fuente, $tamano, $color, 0, 0, $angulo);
                $lienzo->set_opacity(1);
            }
        );
    }

    /* ======================================================================
     |  Internos auxiliares
     ====================================================================== */

    /** Valida la petición y devuelve sólo los datos validados. */
    private function validateDay(Request $request, ?int $ignorarId = null): array
    {
        return $request->validate($this->rules($ignorarId), $this->messages());
    }

    /**
     * Reglas de validación del día completo.
     *
     * Son obligatorios: la fecha, la energía, los 3 objetivos, al menos una
     * franja del horario (con su hora y su actividad) y el cierre del día.
     * El resto de secciones son opcionales.
     *
     * @param  int|null  $ignorarId  Id del día que se está modificando, para que
     *                               su propia fecha no cuente como duplicada.
     */
    private function rules(?int $ignorarId = null): array
    {
        $fechaUnica = Rule::unique('daily_plans', 'plan_date');

        if ($ignorarId) {
            $fechaUnica->ignore($ignorarId);
        }

        return [
            // Cabecera
            'plan_date' => ['required', 'date', $fechaUnica],
            'energy_level_id' => ['required', 'integer', 'exists:energy_levels,id'],

            // Mis 3 objetivos principales: la sección entera es obligatoria
            'goals' => ['required', 'array', 'size:'.DailyPlan::MAX_GOALS],
            'goals.*.slot' => ['required', 'integer', 'between:1,'.DailyPlan::MAX_GOALS, 'distinct'],
            'goals.*.goal_type_id' => ['nullable', 'integer', 'exists:goal_types,id'],
            'goals.*.description' => ['required', 'string', 'max:255'],
            'goals.*.is_done' => ['nullable', 'boolean'],

            // Mi horario de hoy: al menos una franja, con hora y actividad
            'schedule' => ['required', 'array', 'min:1'],
            'schedule.*.id' => ['nullable', 'integer', 'exists:schedule_entries,id'],
            'schedule.*.schedule_slot_id' => ['nullable', 'integer', 'exists:schedule_slots,id'],
            'schedule.*.start_time' => ['required', 'date_format:H:i'],
            'schedule.*.activity' => ['required', 'string', 'max:255'],
            'schedule.*.is_done' => ['nullable', 'boolean'],

            // Cierre del día
            'achievements' => ['required', 'string', 'max:2000'],
            'pending' => ['required', 'string', 'max:2000'],
            'pending_when' => ['required', 'string', 'max:255'],
            'proud_of' => ['required', 'string', 'max:2000'],

            // Antes de empezar (opcional; si se marca, puede llevar descripción)
            'preparation' => ['nullable', 'array'],
            'preparation.*.preparation_item_id' => ['required_with:preparation', 'integer', 'exists:preparation_items,id', 'distinct'],
            'preparation.*.is_checked' => ['nullable', 'boolean'],
            'preparation.*.preparation_items_description' => ['nullable', 'string', 'max:2000'],

            // Si estoy procrastinando (opcional)
            'reflections' => ['nullable', 'array'],
            'reflections.*.reflection_question_id' => ['required_with:reflections', 'integer', 'exists:reflection_questions,id', 'distinct'],
            'reflections.*.is_checked' => ['nullable', 'boolean'],
            'reflections.*.answer' => ['nullable', 'string', 'max:2000'],

            // Bloque de acción (opcional)
            'action_blocks' => ['nullable', 'array'],
            'action_blocks.*.id' => ['nullable', 'integer', 'exists:action_blocks,id'],
            'action_blocks.*.action_block_duration_id' => ['nullable', 'integer', 'exists:action_block_durations,id'],
            'action_blocks.*.action_block_outcome_id' => ['nullable', 'integer', 'exists:action_block_outcomes,id'],
            'action_blocks.*.task' => ['nullable', 'string', 'max:255'],
            'action_blocks.*.started_at' => ['nullable', 'date'],
            'action_blocks.*.finished_at' => ['nullable', 'date', 'after_or_equal:action_blocks.*.started_at'],

            // Notas y recordatorios (opcional)
            'notes' => ['nullable', 'array'],
            'notes.*.content' => ['nullable', 'string', 'max:1000'],
            'notes.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /** Mensajes de validación en español. */
    private function messages(): array
    {
        return [
            'plan_date.required' => 'Selecciona la fecha del diario.',
            'plan_date.date' => 'La fecha no tiene un formato válido.',
            'plan_date.unique' => 'Ya existe un diario con esa fecha.',

            'energy_level_id.required' => 'Selecciona tu nivel de energía de hoy.',
            'energy_level_id.exists' => 'El nivel de energía seleccionado no existe.',

            'goals.required' => 'Completa tus 3 objetivos principales de hoy.',
            'goals.size' => 'Debes registrar exactamente 3 objetivos principales.',
            'goals.*.slot.required' => 'Cada objetivo necesita su ranura (1, 2 o 3).',
            'goals.*.slot.between' => 'Las ranuras de objetivo van de 1 a 3.',
            'goals.*.slot.distinct' => 'No se puede repetir la misma ranura de objetivo.',
            'goals.*.description.required' => 'Escribe los 3 objetivos principales de hoy.',
            'goals.*.goal_type_id.exists' => 'El tipo de objetivo seleccionado no existe.',

            'schedule.required' => 'Agrega al menos una franja en tu horario de hoy.',
            'schedule.min' => 'Agrega al menos una franja en tu horario de hoy.',
            'schedule.*.start_time.required' => 'Cada franja del horario necesita su hora.',
            'schedule.*.start_time.date_format' => 'La hora de la franja no es válida.',
            'schedule.*.activity.required' => 'Cada franja del horario necesita su actividad.',
            'schedule.*.id.exists' => 'La franja del horario que intentas modificar no existe.',

            'achievements.required' => 'Cuéntanos qué lograste hoy.',
            'pending.required' => 'Indica qué quedó pendiente.',
            'pending_when.required' => 'Indica cuándo harás lo pendiente.',
            'proud_of.required' => 'Escribe por qué estás orgulloso/a de ti hoy.',

            'preparation.*.preparation_item_id.exists' => 'El ítem del checklist no existe.',
            'preparation.*.preparation_items_description.max' => 'La descripción del ítem es demasiado larga.',

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

    /** Nombre del archivo PDF del día. */
    private function pdfFileName(DailyPlan $plan, bool $marca = false): string
    {
        $sufijo = $marca ? '-muestra' : '';

        return 'planificador-diario-'.Helper::date($plan->plan_date, 'Y-m-d').$sufijo.'.pdf';
    }
}
