<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\DatosDelFormulario;
use App\Helpers\Helper;

/**
 * Formulario del planificador diario.
 *
 *   index()  /diario        formulario en blanco (crear)
 *   today()  /diario/today  el mismo formulario con la fecha de hoy bloqueada
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
}
