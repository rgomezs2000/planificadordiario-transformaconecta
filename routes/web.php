<?php

use App\Http\Controllers\DailyController;
use App\Http\Controllers\DailyPlanController;
use App\Http\Controllers\ErrorController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\PlanificacionController;
use Illuminate\Support\Facades\Route;
use Kreait\Firebase\Factory;

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

    // La meta de la semana de una fecha, para que el formulario la precargue
    // cuando se elige un día de una semana que ya la tiene escrita.
    Route::get('/meta-semanal', [DailyController::class, 'metaSemanal'])->name('meta-semanal');

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

        // Cumplimiento de las franjas desde la vista de sólo lectura. El botón
        // del final de "Mi horario de hoy" confirma con bootbox y llama aquí
        // por AJAX: así se marca el día sin abrir el formulario completo.
        Route::patch('/{dailyPlan}/horario', [DailyPlanController::class, 'updateSchedule'])->name('horario');

        // Actividades diarias desde la vista de sólo lectura: mismo patrón que
        // el horario. Guarda el estado y los minutos sin abrir el formulario.
        Route::patch('/{dailyPlan}/actividades', [DailyPlanController::class, 'updateActivities'])->name('actividades');

        // Imprimir: el PDF y su versión en imagen. Los dos se guardan en el
        // almacenamiento privado: si ya están hechos se sirven desde ahí y sólo
        // se generan cuando faltan. Con ?marca=1 salen como muestra.
        Route::get('/{dailyPlan}/imprimir', [DailyPlanController::class, 'printPdf'])->name('print');
        Route::get('/{dailyPlan}/imagen', [DailyPlanController::class, 'image'])->name('image');

        // Eliminar
        Route::delete('/{dailyPlan}', [DailyPlanController::class, 'destroy'])->name('destroy');
    });
});

// Notificaciones
//
// El buzón tiene dos caras: la principal lee MySQL (la fuente de verdad) y la
// de Firestore lee el espejo NoSQL. Las dos comparten el mismo uuid y las
// mismas acciones. El contador es la consulta periódica de la campanita; las
// rutas con uuid van restringidas para que /contador, /tabla o /estado no se
// confundan con una notificación.
Route::prefix('notificaciones')->name('notificaciones.')->group(function () {
    // MySQL
    Route::get('/', [NotificacionController::class, 'index'])->name('index');
    Route::get('/contador', [NotificacionController::class, 'contador'])->name('contador');
    Route::get('/tabla', [NotificacionController::class, 'tabla'])->name('tabla');
    Route::get('/estado', [NotificacionController::class, 'estado'])->name('estado');
    Route::post('/sincronizar', [NotificacionController::class, 'sincronizar'])->name('sincronizar');

    // Acciones por lote (los checkbox de la bandeja)
    Route::patch('/lote/leidas', [NotificacionController::class, 'marcarLote'])->name('lote.leidas');
    Route::delete('/lote', [NotificacionController::class, 'descartarLote'])->name('lote.descartar');

    // Una notificación del buzón
    Route::whereUuid('notificacion')->group(function () {
        Route::get('/{notificacion}', [NotificacionController::class, 'mostrar'])->name('mostrar');
        Route::patch('/{notificacion}/leida', [NotificacionController::class, 'marcarLeida'])->name('leida');
        Route::delete('/{notificacion}', [NotificacionController::class, 'descartar'])->name('descartar');
    });
});

// Planificación periódica
//
// Es el módulo del libro de Excel del período: sirve la planilla estática, la
// monta en la cola y muestra lo que dejó la corrida de las 00:00 del comando
// planificacion:importar. El módulo no procesa nada por sí solo.
Route::prefix('planificacion')->name('planificacion.')->group(function () {
    Route::get('/', [PlanificacionController::class, 'index'])->name('index');

    // Descargar la planilla: sin argumento, la genérica; con el nombre, la de un
    // período (una hoja por día). La dirección de la genérica no cambia nunca.
    Route::get('/plantilla/{archivo?}', [PlanificacionController::class, 'plantilla'])
        ->where('archivo', '[A-Za-z0-9._-]+')
        ->name('plantilla');

    // Montar un libro en la cola
    Route::post('/', [PlanificacionController::class, 'store'])->name('store');

    // El estado del módulo (la cola y el detalle de la última corrida), para que
    // se actualice solo por AJAX sin recargar. No dispara ninguna corrida.
    Route::get('/estado', [PlanificacionController::class, 'estado'])->name('estado');

    // La corrida a mano: el boton del modulo la arranca (POST) y sigue el
    // progreso (GET). Lanza el mismo comando que el cron, en segundo plano.
    Route::post('/corrida', [PlanificacionController::class, 'ejecutarCorrida'])->name('corrida.ejecutar');
    Route::get('/corrida', [PlanificacionController::class, 'corrida'])->name('corrida');

    // Sacar varios libros de la cola de una sola vez (lo llama el AJAX del
    // módulo). Va con dos segmentos para no confundirse con /{archivo}.
    Route::delete('/cola/lote', [PlanificacionController::class, 'descartarVarios'])
        ->name('descartar.varios');

    // Sacar un libro de la cola sin procesarlo
    Route::delete('/{archivo}', [PlanificacionController::class, 'descartar'])
        ->where('archivo', '[A-Za-z0-9._-]+')
        ->name('descartar');
});

Route::get('/test-firebase', function () {
    try {
        // Inicializamos Firebase apuntando directamente al archivo JSON de la raíz
        $firebase = (new Factory)
            ->withServiceAccount(base_path('firebase-credentials.json'));

        // Creamos la instancia del servicio de mensajería (notificaciones)
        $messaging = $firebase->createMessaging();

        return response()->json([
            'status' => '¡Conexión Exitosa!',
            'mensaje' => 'El planificador diario ya se comunica con Firebase de manera nativa.',
            'clase_motor' => get_class($messaging)
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'Error de conexión',
            'detalles' => $e->getMessage()
        ], 500);
    }
});
