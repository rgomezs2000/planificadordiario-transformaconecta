<?php

use App\Http\Controllers\DailyController;
use App\Http\Controllers\DailyPlanController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas web
|--------------------------------------------------------------------------
|
| Menú principal, formulario del diario y acciones sobre los días guardados.
| Las acciones que consume el AJAX devuelven JSON; las que se navegan
| devuelven vistas.
|
*/

// Menú principal
Route::get('/', [HomeController::class, 'index'])->name('home');

// Planificador diario
Route::prefix('diario')->name('diario.')->group(function () {
    // Formulario del día: en blanco y con la fecha de hoy ya puesta
    Route::get('/', [DailyController::class, 'index'])->name('index');
    Route::get('/today', [DailyController::class, 'today'])->name('today');

    // Días ya guardados: la página del listado y sus datos para la tabla
    Route::get('/listado', [DailyPlanController::class, 'index'])->name('listado');
    Route::get('/tabla', [DailyPlanController::class, 'list'])->name('tabla');

    // Guardar el día que envía el formulario
    Route::post('/', [DailyPlanController::class, 'store'])->name('store');

    // Rutas con identificador. Van al final del grupo a propósito: así
    // /today, /listado y /tabla no se confunden con un id, y el número se
    // restringe para que cualquier otra cosa caiga en un 404.
    Route::whereNumber('dailyPlan')->group(function () {
        // Mostrar
        Route::get('/{dailyPlan}', [DailyPlanController::class, 'show'])->name('show');
        Route::get('/{dailyPlan}/detalle', [DailyPlanController::class, 'detail'])->name('detail');

        // Modificar
        Route::get('/{dailyPlan}/editar', [DailyPlanController::class, 'edit'])->name('edit');
        Route::match(['put', 'patch'], '/{dailyPlan}', [DailyPlanController::class, 'update'])->name('update');

        // Imprimir
        Route::get('/{dailyPlan}/imprimir', [DailyPlanController::class, 'printPdf'])->name('print');

        // Eliminar
        Route::delete('/{dailyPlan}', [DailyPlanController::class, 'destroy'])->name('destroy');
    });
});
