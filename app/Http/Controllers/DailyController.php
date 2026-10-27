<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Http\Controllers\Concerns\DatosDelFormulario;
use App\Models\WeeklyGoal;
use App\Models\WeeklyReflection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Formulario del planificador diario.
 *
 *   index()        /diario          formulario en blanco (crear)
 *   today()        /diario/today    el mismo formulario con la fecha de hoy
 *   metaSemanal()  /diario/meta-semanal  la meta de la semana de una fecha
 *
 * Ver y modificar reutilizan esta misma vista desde DailyPlanController.
 */
class DailyController extends Controller
{
    use DatosDelFormulario;

    /** Formulario en blanco. */
    public function index()
    {
        return view('diario.formulario', $this->datosFormulario(
            plan: null,
            modo: 'crear',
            planDate: null,
        ));
    }

    /**
     * El mismo formulario, con la fecha de hoy ya puesta y bloqueada.
     * El día de la semana lo marca public/js/script.js a partir de la fecha.
     */
    public function today()
    {
        return view('diario.formulario', $this->datosFormulario(
            plan: null,
            modo: 'crear',
            planDate: Helper::date(now(), 'Y-m-d'),
            dateLocked: true,
        ));
    }

    /**
     * La meta de la semana para una fecha (GET, JSON).
     *
     * La usa el formulario cuando se elige otra fecha: si esa semana ya tiene
     * meta escrita, la trae; si no, la deja vacía para que se complete. También
     * devuelve la reflexión de esa semana, para que el domingo la retome.
     */
    public function metaSemanal(Request $request): JsonResponse
    {
        $fecha = Helper::toCarbon($request->query('fecha')) ?? Helper::today();
        $meta = WeeklyGoal::forDate($fecha);
        $reflexion = WeeklyReflection::forDate($fecha);

        return response()->json([
            'ok' => true,
            'message' => 'Meta semanal consultada.',
            'data' => [
                'fecha' => Helper::date($fecha, 'Y-m-d'),
                'week_start' => $meta?->week_start ? Helper::date($meta->week_start, 'Y-m-d') : null,
                'label' => $meta?->etiqueta(),
                'goal' => $meta?->goal,
                'why_important' => $meta?->why_important,
                'reflection' => $reflexion ? [
                    'week_start' => Helper::date($reflexion->week_start, 'Y-m-d'),
                    'label' => $reflexion->etiqueta(),
                    'answers' => collect($reflexion->respuestas())
                        ->mapWithKeys(fn (array $fila) => [$fila['clave'] => $fila['respuesta']])
                        ->all(),
                ] : null,
            ],
        ]);
    }
}
