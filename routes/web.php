<?php

use App\Http\Controllers\DailyPlanController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas web
|--------------------------------------------------------------------------
|
| Menú principal y planificador diario. Las acciones que consume el AJAX
| devuelven JSON; las que se navegan devuelven vistas.
|
*/

// Menú principal
Route::get('/', [HomeController::class, 'index'])->name('home');

// Planificador diario
Route::prefix('diario')->name('diario.')->group(function () {
    // Listar
    Route::get('/', [DailyPlanController::class, 'index'])->name('index');
    Route::get('/listar', [DailyPlanController::class, 'list'])->name('list');

    // Día de hoy (AJAX)
    Route::get('/hoy', [DailyPlanController::class, 'today'])->name('today');

    // Crear
    Route::get('/crear', [DailyPlanController::class, 'create'])->name('create');
    Route::post('/', [DailyPlanController::class, 'store'])->name('store');

    // Rutas con identificador. Van al final del grupo a propósito: así
    // /crear, /listar y /hoy no se confunden con un id, y el número se
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
