<?php

use App\Http\Controllers\DailyController;
use App\Http\Controllers\DailyPlanController;
use App\Http\Controllers\ErrorController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PlanificacionController;
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

// Página de errores: explica en pantalla el código pedido, agrupado por familia
// (300 redirecciones, 400 errores del cliente, 500 errores del servidor).
// El mismo controlador lo usa el manejador de excepciones de bootstrap/app.php
// para los errores reales de navegación, así el diseño es uno solo.
Route::get('/error/{codigo}', [ErrorController::class, 'index'])
    ->whereNumber('codigo')
    ->name('error');

// Planificador diario
Route::prefix('diario')->name('diario.')->group(function () {
    // Formulario del día: en blanco y con la fecha de hoy ya puesta
    Route::get('/', [DailyController::class, 'index'])->name('index');
    Route::get('/today', [DailyController::class, 'today'])->name('today');

    // Días ya guardados: la página del listado y sus datos para la tabla
    Route::get('/listado', [DailyPlanController::class, 'index'])->name('listado');
    Route::get('/tabla', [DailyPlanController::class, 'list'])->name('tabla');

    // Reporte detallado en Excel, con los filtros del buscador
    Route::get('/reporte', [DailyPlanController::class, 'reporte'])->name('reporte');

    // Resumen de desempeño en PDF: usa los mismos filtros que el listado.
    // Con ?marca=1 sale como documento de muestra.
    Route::get('/resumen', [DailyPlanController::class, 'resumen'])->name('resumen');

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

        // Imprimir: el PDF y su versión en imagen. Los dos se guardan en el
        // almacenamiento privado: si ya están hechos se sirven desde ahí y sólo
        // se generan cuando faltan. Con ?marca=1 salen como muestra.
        Route::get('/{dailyPlan}/imprimir', [DailyPlanController::class, 'printPdf'])->name('print');
        Route::get('/{dailyPlan}/imagen', [DailyPlanController::class, 'image'])->name('image');

        // Eliminar
        Route::delete('/{dailyPlan}', [DailyPlanController::class, 'destroy'])->name('destroy');
    });
});

// Planificación periódica
//
// Es el módulo del libro de Excel del período: sirve la planilla estática, la
// monta en la cola y muestra lo que dejó la corrida de las 00:00 del comando
// planificacion:importar. El módulo no procesa nada por sí solo.
Route::prefix('planificacion')->name('planificacion.')->group(function () {
    Route::get('/', [PlanificacionController::class, 'index'])->name('index');

    // Descargar la planilla en blanco (dirección fija, siempre la misma)
    Route::get('/plantilla', [PlanificacionController::class, 'plantilla'])->name('plantilla');

    // Montar un libro en la cola
    Route::post('/', [PlanificacionController::class, 'store'])->name('store');

    // Sacar un libro de la cola sin procesarlo
    Route::delete('/{archivo}', [PlanificacionController::class, 'descartar'])
        ->where('archivo', '[A-Za-z0-9._-]+')
        ->name('descartar');
});
