<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Models\DailyPlan;

/**
 * Menú principal del planificador.
 */
class HomeController extends Controller
{
    /**
     * Portada con el menú principal.
     *
     * Si ya existe el diario de hoy se muestra su avance para poder entrar
     * directo; si no existe, la portada ofrece crearlo.
     */
    public function index()
    {
        return view('home', [
            'today' => DailyPlan::findToday(),
            'todayLabel' => Helper::longDate(now(), withWeekday: true),
        ]);
    }
}
