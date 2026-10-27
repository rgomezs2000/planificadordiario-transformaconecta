<?php

namespace App\Http\Controllers;

use App\Documentos\DocumentoDelDiario;
use App\Errores\RegistroDeErrores;
use App\Excel\ReporteDiarioExport;
use App\Filtros\Periodo;
use App\Graficos\AgendaDelDia;
use App\Graficos\AgendaPng;
use App\Helpers\Helper;
use App\Http\Controllers\Concerns\DatosDelFormulario;
use App\Models\DailyPlan;
use App\Models\EnergyLevel;
use App\Models\ScheduleEntry;
use App\Reportes\AnalisisDeDesempeno;
use App\Reportes\GraficosDelResumen;
use App\Reportes\RedaccionDelResumen;
use Barryvdh\DomPDF\Facade\Pdf;
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
            $filtros = $this->filtrosDeLaPeticion($request);

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
            $this->registrarFallo($e, 'listar los diarios', ['filtros' => $filtros]);

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
            $this->registrarFallo($e, 'mostrar el diario', ['diario_id' => $dailyPlan]);

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
            $this->registrarFallo($e, 'abrir el diario para modificar', ['diario_id' => $dailyPlan]);

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
            $this->registrarFallo($e, 'consultar el detalle del diario', ['diario_id' => $dailyPlan]);

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
            $this->registrarFallo($e, 'guardar el diario', [
                'plan_date' => $data['plan_date'] ?? null,
                'energy_level_id' => $data['energy_level_id'] ?? null,
            ]);

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
            $this->registrarFallo($e, 'buscar el diario antes de modificarlo', ['diario_id' => $dailyPlan]);
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
            $this->registrarFallo($e, 'modificar el diario', ['diario_id' => $dailyPlan]);

            return $this->jsonError('No se pudo actualizar el diario. Intenta de nuevo.', [], 500);
        }
    }

    /* ======================================================================
     |  Cumplimiento del horario (vista de sólo lectura)
     ====================================================================== */

    /**
     * Marca o desmarca las franjas del horario de un día (PATCH).
     *
     * Lo llama el botón del final de "Mi horario de hoy" en la vista de sólo
     * lectura. El navegador manda todas las franjas con su estado (1 o 0) y
     * aquí se guardan; no se crean ni se borran franjas. La respuesta lleva el
     * avance del día y el estado final de cada franja, para que la pantalla
     * quede igual que la base de datos.
     *
     * La validación va fuera del try/catch a propósito: si falla, Laravel
     * responde 422 con el detalle y la alerta de bootbox muestra el motivo.
     */
    public function updateSchedule(Request $request, int $dailyPlan): JsonResponse
    {
        $plan = null;

        try {
            $plan = DailyPlan::find($dailyPlan);
        } catch (Throwable $e) {
            $this->registrarFallo($e, 'buscar el diario antes de marcar el horario', ['diario_id' => $dailyPlan]);
        }

        if (! $plan) {
            return $this->jsonError('El diario que intentas marcar no existe.', [], 404);
        }

        // Cada franja tiene que ser de este día: así una petición armada a mano
        // no puede tocar el horario de otro diario.
        $data = $request->validate([
            'franjas' => ['required', 'array', 'min:1'],
            'franjas.*.id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('schedule_entries', 'id')
                    ->where(fn ($query) => $query->where('daily_plan_id', $plan->id)),
            ],
            'franjas.*.is_done' => ['required', 'boolean'],
        ], [
            'franjas.required' => 'No se recibió ninguna franja del horario.',
            'franjas.array' => 'El horario enviado no tiene un formato válido.',
            'franjas.min' => 'No se recibió ninguna franja del horario.',
            'franjas.*.id.required' => 'Cada franja del horario necesita su identificador.',
            'franjas.*.id.integer' => 'El identificador de la franja del horario no es válido.',
            'franjas.*.id.distinct' => 'No se puede repetir la misma franja del horario.',
            'franjas.*.id.exists' => 'Alguna de las franjas no pertenece a este diario.',
            'franjas.*.is_done.required' => 'Cada franja necesita indicar si se cumplió.',
            'franjas.*.is_done.boolean' => 'El estado de cumplimiento de la franja no es válido.',
        ]);

        try {
            $resumen = $plan->markScheduleDone($data['franjas']);

            $mensaje = $resumen['updated'] > 0
                ? 'Se guardó el cumplimiento del horario: '.$resumen['done'].' de '
                    .$resumen['total'].' franjas marcadas como cumplidas.'
                : 'El horario ya estaba como lo enviaste: no hubo cambios que guardar.';

            return $this->jsonSuccess($resumen, $mensaje);
        } catch (Throwable $e) {
            $this->registrarFallo($e, 'marcar el cumplimiento del horario', ['diario_id' => $dailyPlan]);

            return $this->jsonError('No se pudo guardar el cumplimiento del horario. Intenta de nuevo.', [], 500);
        }
    }

    /* ======================================================================
     |  Cumplimiento de las actividades (vista de sólo lectura)
     ====================================================================== */

    /**
     * Marca o desmarca las actividades diarias de un día (PATCH).
     *
     * Es el botón del final de "Actividades diarias" en la vista de sólo
     * lectura. Recibe las actividades visibles con su estado y, cuando
     * corresponde, sus minutos. No crea actividades fuera del catálogo ni
     * acepta las que no aplican al día.
     */
    public function updateActivities(Request $request, int $dailyPlan): JsonResponse
    {
        $plan = null;

        try {
            $plan = DailyPlan::find($dailyPlan);
        } catch (Throwable $e) {
            $this->registrarFallo($e, 'buscar el diario antes de marcar las actividades', ['diario_id' => $dailyPlan]);
        }

        if (! $plan) {
            return $this->jsonError('El diario que intentas marcar no existe.', [], 404);
        }

        $data = $request->validate([
            'actividades' => ['required', 'array', 'min:1'],
            'actividades.*.daily_activity_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('daily_activities', 'id'),
            ],
            'actividades.*.is_done' => ['required', 'boolean'],
            'actividades.*.minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
        ], [
            'actividades.required' => 'No se recibió ninguna actividad.',
            'actividades.array' => 'Las actividades enviadas no tienen un formato válido.',
            'actividades.min' => 'No se recibió ninguna actividad.',
            'actividades.*.daily_activity_id.required' => 'Cada actividad necesita su identificador.',
            'actividades.*.daily_activity_id.integer' => 'El identificador de la actividad no es válido.',
            'actividades.*.daily_activity_id.distinct' => 'No se puede repetir la misma actividad.',
            'actividades.*.daily_activity_id.exists' => 'Alguna de las actividades no existe en el catálogo.',
            'actividades.*.is_done.required' => 'Cada actividad necesita indicar si se cumplió.',
            'actividades.*.is_done.boolean' => 'El estado de la actividad no es válido.',
            'actividades.*.minutes.integer' => 'Los minutos de la actividad no son válidos.',
            'actividades.*.minutes.min' => 'Los minutos de la actividad no pueden ser negativos.',
            'actividades.*.minutes.max' => 'Los minutos de la actividad no pueden superar 1440.',
        ]);

        try {
            $resumen = $plan->markActivitiesDone($data['actividades']);

            $mensaje = $resumen['updated'] > 0
                ? 'Se guardaron las actividades: '.$resumen['done'].' de '.$resumen['total']
                    .' marcadas como cumplidas.'
                : 'Las actividades ya estaban como las enviaste: no hubo cambios que guardar.';

            return $this->jsonSuccess($resumen, $mensaje);
        } catch (Throwable $e) {
            $this->registrarFallo($e, 'marcar las actividades del día', ['diario_id' => $dailyPlan]);

            return $this->jsonError('No se pudieron guardar las actividades. Intenta de nuevo.', [], 500);
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
            $this->registrarFallo($e, 'eliminar el diario', ['diario_id' => $dailyPlan]);

            return $this->jsonError('No se pudo eliminar el diario. Intenta de nuevo.', [], 500);
        }
    }

    /**
     * Los filtros del buscador que llegan por la dirección.
     *
     * Se leen en un solo lugar porque los usan tanto la tabla como el reporte:
     * así los dos filtran exactamente lo mismo.
     *
     * @return array<string, mixed>
     */
    private function filtrosDeLaPeticion(Request $request): array
    {
        return [
            'fecha' => $request->query('fecha'),
            'energia' => $request->query('energia'),
            'palabra' => $request->query('palabra'),
            // Filtro por período: el tipo y lo que se eligió para ese tipo.
            'periodo' => $request->query('periodo'),
            'semana' => $request->query('semana'),
            'mes' => $request->query('mes'),
            'mitad' => $request->query('mitad'),
            'anio' => $request->query('anio'),
            'trimestre' => $request->query('trimestre'),
            'semestre' => $request->query('semestre'),
            'desde' => $request->query('desde'),
            'hasta' => $request->query('hasta'),
        ];
    }

    /* ======================================================================
     |  Resumen de desempeño
     ====================================================================== */

    /**
     * Resumen de desempeño en PDF.
     *
     * Usa los mismos filtros que la tabla y el reporte detallado, así el
     * documento habla exactamente de lo que se está viendo en pantalla. Con
     * ?marca=1 sale con la marca de agua de muestra, igual que el PDF del día.
     */
    public function resumen(Request $request)
    {
        try {
            $filtros = $this->filtrosDeLaPeticion($request);
            $plans = DailyPlan::forReport($filtros);

            if ($plans->isEmpty()) {
                return $this->jsonError(
                    'No hay diarios que coincidan con la búsqueda, así que no hay resumen que generar.',
                    [],
                    404
                );
            }

            $analisis = AnalisisDeDesempeno::de($plans, $this->alcanceDelResumen($filtros));
            $marca = $request->boolean('marca');

            $html = view('diario.resumen', [
                'analisis' => $analisis,
                'textos' => RedaccionDelResumen::observaciones($analisis),
                'titulos' => RedaccionDelResumen::titulos(),
                'marca' => $marca,
                'logo' => DocumentoDelDiario::logoDataUri(),
                'graficos' => [
                    'procrastinacion' => GraficosDelResumen::dataUri(
                        GraficosDelResumen::performanceVsProcrastinacion($analisis)
                    ),
                    'dias' => GraficosDelResumen::dataUri(GraficosDelResumen::rendimientoPorDia($analisis)),
                    'energia' => GraficosDelResumen::dataUri(GraficosDelResumen::rendimientoPorEnergia($analisis)),
                    'actividades' => GraficosDelResumen::dataUri(
                        GraficosDelResumen::cumplimientoActividades($analisis)
                    ),
                    'impacto' => GraficosDelResumen::dataUri(
                        GraficosDelResumen::impactoActividades($analisis)
                    ),
                ],
                // Con un solo día también va la línea de tiempo de ese día.
                'agenda' => $plans->count() === 1 ? $this->agendaDelResumen($plans->first()) : null,
            ])->render();

            $dompdf = Pdf::loadHTML($html)->setPaper('letter')->getDomPDF();
            $dompdf->render();

            if ($marca) {
                $this->marcarComoMuestra($dompdf);
            }

            return response($dompdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$this->resumenFileName($analisis, $marca).'"',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
            ]);
        } catch (Throwable $e) {
            $this->registrarFallo($e, 'generar el resumen de desempeño', ['filtros' => $filtros]);

            return $this->jsonError('No se pudo generar el resumen. Intenta de nuevo.', [], 500);
        }
    }

    /** La línea de tiempo de un solo día, para el resumen. */
    private function agendaDelResumen(DailyPlan $plan): string
    {
        return AgendaPng::dataUri(AgendaDelDia::datos(
            $plan->scheduleEntries->map(fn (ScheduleEntry $franja) => [
                'start_time' => $franja->start_time,
                'activity' => $franja->activity,
                'is_done' => (bool) $franja->is_done,
            ])->all()
        ));
    }

    /** Cómo se llama el archivo, según lo que cubra. */
    private function resumenFileName(array $analisis, bool $marca): string
    {
        $partes = ['resumen-desempeno'];

        if ($analisis['desde']) {
            $partes[] = $analisis['desde'];
        }

        if ($analisis['hasta'] && $analisis['hasta'] !== $analisis['desde']) {
            $partes[] = $analisis['hasta'];
        }

        if ($marca) {
            $partes[] = 'muestra';
        }

        return implode('-', $partes).'.pdf';
    }

    /** Describe en palabras qué filtros se aplicaron. */
    private function alcanceDelResumen(array $filtros): string
    {
        $partes = [];

        if (($palabra = Helper::strip($filtros['palabra'] ?? null)) !== '') {
            $partes[] = 'búsqueda por "'.$palabra.'"';
        }

        if (($fecha = Helper::toCarbon($filtros['fecha'] ?? null)) !== null) {
            $partes[] = 'el día '.Helper::longDate($fecha, withWeekday: true);
        }

        if (($energia = Helper::strip($filtros['energia'] ?? null)) !== '') {
            $partes[] = 'energía '.$energia;
        }

        if (($rango = Periodo::rango($filtros['periodo'] ?? null, $filtros)) !== null) {
            $partes[] = 'el período '.$rango['etiqueta'];
        }

        return $partes === [] ? 'Todos los diarios' : ucfirst(implode(', ', $partes));
    }

    /* ======================================================================
     |  Imprimir
     ====================================================================== */

    /**
     * PDF del día.
     *
     * El documento se guarda en el almacenamiento privado la primera vez y a
     * partir de ahí se sirve desde ahí: si ya está generado no se vuelve a
     * renderizar. Cuando falta, se arma desde la base de datos y se guarda.
     *
     * Con ?marca=1 sale con la marca de agua diagonal SPECIMEN, y esa variante
     * se guarda aparte, así marcar y desmarcar no se pisan entre sí.
     *
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
            $documento = DocumentoDelDiario::de($plan);

            // Se genera y se guarda si no estaba; si ya estaba, no se toca.
            $ruta = $documento->asegurarPdf($marca);

            // Sin caché de navegador: la dirección es siempre la misma, y si el
            // navegador guardara el archivo mostraría una versión anterior.
            return response()->file($ruta, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$documento->nombre('pdf', $marca).'"',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
            ]);
        } catch (Throwable $e) {
            $this->registrarFallo($e, 'imprimir el diario', ['diario_id' => $dailyPlan]);

            return $this->jsonError('No se pudo generar el PDF del diario.', [], 500);
        }
    }

    /**
     * Imagen del día: el PDF convertido a JPG.
     *
     * Si el PDF tiene una sola página se descarga un JPG; si tiene dos o más,
     * se descarga un ZIP con una imagen por página. Igual que el PDF, todo sale
     * del almacenamiento privado cuando ya está hecho: si falta la imagen se
     * convierte el PDF guardado y, si el PDF tampoco está, se genera primero.
     *
     * Con ?marca=1 la imagen sale del PDF con la marca de agua SPECIMEN.
     */
    public function image(Request $request, int $dailyPlan)
    {
        try {
            $plan = DailyPlan::find($dailyPlan);

            if (! $plan) {
                return $this->jsonError('El diario que intentas convertir no existe.', [], 404);
            }

            $archivo = DocumentoDelDiario::de($plan)->asegurarImagen($request->boolean('marca'));

            return response()->download($archivo['ruta'], $archivo['nombre'], [
                'Content-Type' => $archivo['tipo'],
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
            ]);
        } catch (Throwable $e) {
            $this->registrarFallo($e, 'generar la imagen del diario', ['diario_id' => $dailyPlan]);

            return $this->jsonError(
                'No se pudo generar la imagen del diario. Revisa que el conversor de PDF a JPG esté instalado.',
                [],
                500
            );
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
            $filtros = $this->filtrosDeLaPeticion($request);

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
            $this->registrarFallo($e, 'generar el reporte detallado en Excel', ['filtros' => $filtros]);

            return $this->jsonError('No se pudo generar el reporte detallado.', [], 500);
        }
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

            // Mi meta semanal: obligatoria. Se registra una sola vez por semana
            // y los demás días la reciben cargada. El campo «actualizar» sólo
            // llega cuando se pulsa «Cambiar meta»: sin él, la meta registrada
            // no se vuelve a tocar.
            'weekly_goal' => ['required', 'array'],
            'weekly_goal.goal' => ['required', 'string', 'max:2000'],
            'weekly_goal.why_important' => ['nullable', 'string', 'max:2000'],
            'weekly_goal.actualizar' => ['nullable', 'boolean'],

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

            // Actividades diarias (hábitos). Son opcionales: un día puede no
            // marcar ninguna y eso también es un dato.
            'activities' => ['nullable', 'array'],
            'activities.*.daily_activity_id' => ['required_with:activities', 'integer', 'exists:daily_activities,id', 'distinct'],
            'activities.*.is_done' => ['nullable', 'boolean'],
            'activities.*.minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'activities.*.note' => ['nullable', 'string', 'max:500'],

            // Mi meta personal: el seguimiento del día.
            'personal_done_today' => ['nullable', 'string', 'max:2000'],
            'personal_helped' => ['nullable', 'string', 'max:2000'],
            'personal_hard' => ['nullable', 'string', 'max:2000'],

            // Reflexión de la semana: sólo se guarda el domingo.
            'weekly_reflection' => ['nullable', 'array'],
            'weekly_reflection.how_was_week' => ['nullable', 'string', 'max:2000'],
            'weekly_reflection.what_achieved' => ['nullable', 'string', 'max:2000'],
            'weekly_reflection.what_was_hard' => ['nullable', 'string', 'max:2000'],
            'weekly_reflection.what_learned' => ['nullable', 'string', 'max:2000'],
            'weekly_reflection.how_improved' => ['nullable', 'string', 'max:2000'],
            'weekly_reflection.what_different' => ['nullable', 'string', 'max:2000'],

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

            'weekly_goal.required' => 'Escribe la meta de esta semana.',
            'weekly_goal.goal.required' => 'Escribe la meta de esta semana: es un campo obligatorio.',
            'weekly_goal.goal.max' => 'La meta semanal es demasiado larga.',
            'weekly_goal.why_important.max' => 'El porqué de tu meta es demasiado largo.',

            'activities.*.daily_activity_id.exists' => 'La actividad enviada no existe en el catálogo.',
            'activities.*.daily_activity_id.distinct' => 'No se puede repetir la misma actividad.',
            'activities.*.minutes.max' => 'Los minutos de una actividad no pueden superar 1440.',
            'activities.*.note.max' => 'La nota de la actividad es demasiado larga.',

            'personal_done_today.max' => 'El texto de tu seguimiento personal es demasiado largo.',
            'personal_helped.max' => 'El texto de tu seguimiento personal es demasiado largo.',
            'personal_hard.max' => 'El texto de tu seguimiento personal es demasiado largo.',

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

    /**
     * Deja constancia del fallo antes de responderle al navegador.
     *
     * El error se anota en el log general (como se hacía con report()) y, con
     * mucho más detalle —dirección, IP, ruta, navegador, traza recortada y el
     * contexto de la operación—, en el log de errores del sistema:
     *
     *     storage/logs/errores-AAAA-MM-DD.log
     *
     * El código de incidente que devuelve no se le muestra al usuario en estas
     * respuestas AJAX (el mensaje ya es claro); queda en el log para cruzarlo.
     *
     * @param  array<string, mixed>  $contexto  Datos de la operación que falló.
     */
    private function registrarFallo(Throwable $e, string $operacion, array $contexto = []): void
    {
        report($e);

        RegistroDeErrores::deControlador($e, $operacion, $contexto);
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
}
