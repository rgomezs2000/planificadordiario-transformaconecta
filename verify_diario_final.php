<?php

/**
 * TEMPORAL: verificación completa del planificador diario.
 *
 * Cubre los 6 puntos: guardado por AJAX, listado, ver, modificar, PDF con y sin
 * marca de agua, y eliminar. Necesita permisos de escritura porque Blade compila
 * las vistas en storage/framework/views y dompdf usa su carpeta temporal.
 *
 * Todo ocurre dentro de una transacción que se revierte: no deja datos.
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\DailyController;
use App\Http\Controllers\DailyPlanController;
use App\Models\ActionBlockDuration;
use App\Models\ActionBlockOutcome;
use App\Models\DailyPlan;
use App\Models\EnergyLevel;
use App\Models\GoalType;
use App\Models\PreparationItem;
use App\Models\ReflectionQuestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

$fallos = 0;
$ok = 0;

function comprobar(string $etiqueta, mixed $obtenido, mixed $esperado): void
{
    global $fallos, $ok;

    $bien = $obtenido === $esperado;
    $bien ? $ok++ : $fallos++;

    printf("  %s %-56s %s%s\n", $bien ? 'OK  ' : 'FALLA', $etiqueta,
        is_bool($obtenido) ? var_export($obtenido, true) : (is_scalar($obtenido) ? var_export($obtenido, true) : gettype($obtenido)),
        $bien ? '' : '  <- esperado: '.var_export($esperado, true));
}

function titulo(string $texto): void
{
    echo "\n=== {$texto} ===\n";
}

/** Prepara una petición JSON y la deja como petición actual. */
function peticion(string $uri, string $verbo, array $datos = []): Request
{
    $request = Request::create($uri, $verbo, [], [], [], [
        'HTTP_ACCEPT' => 'application/json',
        'CONTENT_TYPE' => 'application/json',
    ]);

    // La petición va declarada como JSON, así que Laravel lee los datos de
    // json(); se llenan las dos bolsas para que dé igual por dónde los busque.
    $request->request->replace($datos);
    $request->json()->replace($datos);

    app()->instance('request', $request);

    return $request;
}

$cuerpoJson = fn ($respuesta) => json_decode($respuesta->getContent(), true);

/* ==================================================================== */
titulo('1. VISTAS BLADE');
/* ==================================================================== */

foreach ([
    'layouts/app', 'home/index', 'diario/formulario', 'diario/index',
    'diario/pdf', 'diario/_modal_imprimir',
] as $vista) {
    try {
        token_get_all(
            Blade::compileString(file_get_contents(__DIR__.'/resources/views/'.$vista.'.blade.php')),
            TOKEN_PARSE
        );
        comprobar($vista.' compila', true, true);
    } catch (Throwable $e) {
        $fallos++;
        printf("  FALLA %-56s %s\n", $vista, $e->getMessage());
    }
}

/* ==================================================================== */
titulo('2. RUTAS');
/* ==================================================================== */

foreach ([
    'home', 'diario.index', 'diario.today', 'diario.store', 'diario.listado',
    'diario.tabla', 'diario.show', 'diario.edit', 'diario.update',
    'diario.destroy', 'diario.print', 'diario.detail',
] as $ruta) {
    comprobar('existe '.$ruta, Route::has($ruta), true);
}

/* ==================================================================== */
titulo('3. GUARDADO POR AJAX (POST)');
/* ==================================================================== */

DB::beginTransaction();

$controlador = new DailyPlanController();

$tipos = GoalType::active()->pluck('id', 'slug');
$prep = PreparationItem::active()->pluck('id');
$preguntas = ReflectionQuestion::active()->pluck('id');
$duraciones = ActionBlockDuration::active()->pluck('id', 'minutes');
$resultados = ActionBlockOutcome::active()->pluck('id', 'slug');
$alta = EnergyLevel::where('slug', 'alta')->value('id');
$media = EnergyLevel::where('slug', 'media')->value('id');

$cuerpo = [
    'plan_date' => '2026-11-10',
    'energy_level_id' => $alta,
    'achievements' => 'Terminé el planificador',
    'pending' => 'Revisar el PDF',
    'pending_when' => 'Mañana temprano',
    'proud_of' => 'Mantuve el foco todo el día',
    'goals' => [
        ['slot' => 1, 'goal_type_id' => $tipos['debo_hacer'], 'description' => 'Terminar las validaciones', 'is_done' => 1],
        ['slot' => 2, 'goal_type_id' => $tipos['quiero_hacer'], 'description' => 'Caminar 30 minutos'],
        ['slot' => 3, 'goal_type_id' => $tipos['algo_para_mi'], 'description' => 'Leer 20 páginas'],
    ],
    'schedule' => [
        ['start_time' => '06:30', 'activity' => 'Caminata', 'is_done' => 1],
        ['start_time' => '08:15', 'activity' => 'Programar', 'is_done' => 0],
    ],
    'preparation' => [
        ['preparation_item_id' => $prep[0], 'is_checked' => 1, 'preparation_items_description' => 'PC, cuaderno'],
        ['preparation_item_id' => $prep[1], 'is_checked' => 0],
    ],
    'reflections' => [
        ['reflection_question_id' => $preguntas[0], 'is_checked' => 1, 'answer' => 'Estoy evitando la llamada'],
    ],
    'action_blocks' => [
        ['action_block_duration_id' => $duraciones[15], 'action_block_outcome_id' => $resultados['avance'], 'task' => 'Validaciones'],
    ],
    'notes' => [
        ['content' => "Primera nota\nSegunda línea"],
    ],
];

$respuesta = $controlador->store(peticion('/diario', 'POST', $cuerpo));
$json = $cuerpoJson($respuesta);
$planId = $json['data']['plan']['id'] ?? null;

comprobar('store responde 201', $respuesta->getStatusCode(), 201);
comprobar('store devuelve ok', $json['ok'] ?? null, true);
comprobar('store crea el diario', is_int($planId), true);

$plan = DailyPlan::find($planId);
comprobar('3 objetivos guardados', $plan->goals()->count(), 3);
comprobar('2 franjas guardadas', $plan->scheduleEntries()->count(), 2);
comprobar('franja con hora libre', $plan->scheduleEntries()->first()->start_time, '06:30:00');
comprobar('descripción del checklist', $plan->preparationItems()->first()->pivot->preparation_items_description, 'PC, cuaderno');
comprobar('respuesta de reflexión', $plan->reflectionAnswers()->first()->answer, 'Estoy evitando la llamada');
comprobar('notas con salto de línea', $plan->notes()->first()->content, "Primera nota\nSegunda línea");
comprobar('cierre del día', $plan->proud_of, 'Mantuve el foco todo el día');

/* ==================================================================== */
titulo('4. VALIDACIÓN EN EL BACKEND');
/* ==================================================================== */

try {
    $controlador->store(peticion('/diario', 'POST', [
        'plan_date' => '2026-11-11',
        'goals' => [],
        'schedule' => [],
    ]));
    $fallos++;
    echo "  FALLA no rechazó el diario sin obligatorios\n";
} catch (ValidationException $e) {
    $campos = array_keys($e->errors());

    foreach (['energy_level_id', 'goals', 'schedule', 'achievements', 'pending', 'pending_when', 'proud_of'] as $campo) {
        comprobar('exige '.$campo, in_array($campo, $campos, true), true);
    }
}

try {
    $controlador->store(peticion('/diario', 'POST', $cuerpo));
    $fallos++;
    echo "  FALLA no rechazó la fecha duplicada\n";
} catch (ValidationException $e) {
    comprobar('rechaza la fecha duplicada', array_key_exists('plan_date', $e->errors()), true);
}

try {
    $malHorario = $cuerpo;
    $malHorario['plan_date'] = '2026-11-13';
    $malHorario['schedule'] = [['start_time' => '07:00']];
    $controlador->store(peticion('/diario', 'POST', $malHorario));
    $fallos++;
    echo "  FALLA no rechazó una franja sin actividad\n";
} catch (ValidationException $e) {
    comprobar('exige la actividad de cada franja', array_key_exists('schedule.0.activity', $e->errors()), true);
}

/* ==================================================================== */
titulo('5. LISTADO (GET)');
/* ==================================================================== */

$listado = $controlador->list();
$datos = $cuerpoJson($listado);

comprobar('listado responde 200', $listado->getStatusCode(), 200);
comprobar('listado trae los diarios', count($datos['data']['plans']), 1);
comprobar('la fila trae la fecha', $datos['data']['plans'][0]['date'], '2026-11-10');
comprobar('la fila trae la letra del día', $datos['data']['plans'][0]['day_letter'], 'M');
comprobar('la fila trae el slug de energía', $datos['data']['plans'][0]['energy_slug'], 'alta');

/* ==================================================================== */
titulo('6. VER Y MODIFICAR');
/* ==================================================================== */

comprobar('index() devuelve una vista', $controlador->index() instanceof View, true);
comprobar('show() devuelve una vista', $controlador->show($planId) instanceof View, true);
comprobar('edit() devuelve una vista', $controlador->edit($planId) instanceof View, true);
comprobar('show() inexistente redirige',
    $controlador->show(999999) instanceof \Illuminate\Http\RedirectResponse, true);
comprobar('show() inexistente deja el aviso', session('error') !== null, true);

$actualizado = $controlador->update(
    peticion('/diario/'.$planId, 'PUT', array_merge($cuerpo, [
        'energy_level_id' => $media,
        'schedule' => [['start_time' => '07:00', 'activity' => 'Desayuno']],
    ])),
    $planId
);

comprobar('update responde 200', $actualizado->getStatusCode(), 200);
comprobar('update devuelve ok', $cuerpoJson($actualizado)['ok'] ?? null, true);
comprobar('energía actualizada', DailyPlan::find($planId)->energy_level_id, $media);
comprobar('horario reemplazado', DailyPlan::find($planId)->scheduleEntries()->count(), 1);
comprobar('update inexistente da 404',
    $controlador->update(peticion('/diario/999999', 'PUT', $cuerpo), 999999)->getStatusCode(), 404);

/* ==================================================================== */
titulo('7. PDF CON Y SIN MARCA DE AGUA');
/* ==================================================================== */

$limpio = $controlador->printPdf(peticion('/diario/'.$planId.'/imprimir', 'GET'), $planId);
$conMarca = $controlador->printPdf(
    peticion('/diario/'.$planId.'/imprimir', 'GET', ['marca' => 1]),
    $planId
);

$pdfLimpio = $limpio->getContent();
$pdfMarca = $conMarca->getContent();

/** Saca el texto de un PDF descomprimiendo sus flujos (van en FlateDecode). */
$textoDelPdf = function (string $pdf): string {
    $texto = '';

    if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $coincidencias)) {
        foreach ($coincidencias[1] as $flujo) {
            $inflado = @gzuncompress($flujo);
            $texto .= $inflado !== false ? $inflado : $flujo;
        }
    }

    return $texto;
};

$textoLimpio = $textoDelPdf($pdfLimpio);
$textoMarca = $textoDelPdf($pdfMarca);

comprobar('PDF limpio: tipo de contenido', $limpio->headers->get('Content-Type'), 'application/pdf');
comprobar('PDF limpio: firma %PDF', str_starts_with($pdfLimpio, '%PDF'), true);
comprobar('PDF limpio: cierra bien', str_contains(substr($pdfLimpio, -32), '%%EOF'), true);
comprobar('PDF limpio: nombre del archivo',
    str_contains((string) $limpio->headers->get('Content-Disposition'), 'planificador-diario-2026-11-10.pdf'), true);

comprobar('el PDF trae el título', str_contains($textoLimpio, 'MI PLANIFICADOR DIARIO'), true);
comprobar('el PDF trae la fecha', str_contains($textoLimpio, '10/11/2026'), true);
comprobar('el PDF trae un objetivo', str_contains($textoLimpio, 'Terminar las validaciones'), true);
// En la prueba de update el horario se reemplazó por "Desayuno".
comprobar('el PDF trae una franja del horario', str_contains($textoLimpio, 'Desayuno'), true);
comprobar('el PDF trae una pregunta de reflexión', str_contains($textoLimpio, 'estoy evitando'), true);
comprobar('el PDF trae el cierre del día', str_contains($textoLimpio, 'Mantuve el foco'), true);
comprobar('el PDF trae una sección', str_contains($textoLimpio, 'ANTES DE EMPEZAR'), true);

comprobar('PDF con marca: tipo de contenido', $conMarca->headers->get('Content-Type'), 'application/pdf');
comprobar('PDF con marca: firma %PDF', str_starts_with($pdfMarca, '%PDF'), true);
comprobar('PDF con marca: nombre del archivo',
    str_contains((string) $conMarca->headers->get('Content-Disposition'), '-muestra.pdf'), true);
comprobar('los dos PDF son distintos', $pdfLimpio !== $pdfMarca, true);
comprobar('el limpio NO lleva la marca de agua', str_contains($textoLimpio, 'SPECIMEN'), false);
comprobar('el de muestra SÍ lleva la marca de agua', str_contains($textoMarca, 'SPECIMEN'), true);
comprobar('PDF inexistente da 404',
    $controlador->printPdf(peticion('/diario/999999/imprimir', 'GET'), 999999)->getStatusCode(), 404);

/* ==================================================================== */
titulo('8. PESO DE LOS PDF');
/* ==================================================================== */

printf("  PDF limpio ....... %d bytes\n", strlen($pdfLimpio));
printf("  PDF con marca .... %d bytes\n", strlen($pdfMarca));

comprobar('el PDF es ligero (fuente base, sin incrustar)', strlen($pdfLimpio) < 102400, true);
comprobar('el PDF con marca también', strlen($pdfMarca) < 102400, true);

/* ==================================================================== */
titulo('9. ELIMINAR (DELETE)');
/* ==================================================================== */

$borrado = $controlador->destroy($planId);

comprobar('destroy responde 200', $borrado->getStatusCode(), 200);
comprobar('destroy devuelve ok', $cuerpoJson($borrado)['ok'] ?? null, true);
comprobar('el diario se eliminó', DailyPlan::find($planId), null);
comprobar('cascada de objetivos', DB::table('plan_goals')->count(), 0);
comprobar('cascada de horario', DB::table('schedule_entries')->count(), 0);
comprobar('cascada de checklist', DB::table('daily_plan_preparation')->count(), 0);
comprobar('destroy inexistente da 404', $controlador->destroy(999999)->getStatusCode(), 404);

/* ==================================================================== */
titulo('10. RENDER DE LAS VISTAS');
/* ==================================================================== */

$otro = DailyPlan::createDay(array_merge($cuerpo, ['plan_date' => '2026-11-12']));

$render = function (mixed $respuesta, string $nombre): ?string {
    if ($respuesta instanceof View) {
        return $respuesta->render();
    }

    global $fallos;
    $fallos++;
    printf("  FALLA %-56s no se pudo renderizar\n", $nombre);

    return null;
};

$htmlVer = $render($controlador->show($otro->id), 'ver');

if ($htmlVer !== null) {
    comprobar('ver: trae la fecha', str_contains($htmlVer, '2026-11-12'), true);
    comprobar('ver: modo ver', str_contains($htmlVer, 'data-modo="ver"'), true);
    comprobar('ver: sin botón de guardar', str_contains($htmlVer, 'id="formulario-guardar"'), false);
    comprobar('ver: con botón de imprimir', str_contains($htmlVer, 'id="imprimir-diario"'), true);
    comprobar('ver: sin botón agregar franja', str_contains($htmlVer, 'id="horario-agregar"'), false);
    // Cada franja lleva 4 campos: id, hora, actividad y check.
    comprobar('ver: trae las 2 franjas (8 campos)', substr_count($htmlVer, 'name="schedule['), 8);
    comprobar('ver: la franja 0 está', str_contains($htmlVer, 'name="schedule[0][start_time]"'), true);
    comprobar('ver: la franja 1 está', str_contains($htmlVer, 'name="schedule[1][start_time]"'), true);
    comprobar('ver: trae los objetivos', str_contains($htmlVer, 'Terminar las validaciones'), true);
    comprobar('ver: trae la descripción del checklist', str_contains($htmlVer, 'PC, cuaderno'), true);
    comprobar('ver: campos bloqueados', substr_count($htmlVer, 'readonly') > 5, true);
    comprobar('ver: el script del formulario viaja', str_contains($htmlVer, 'js/script.js'), true);
}

$htmlEditar = $render($controlador->edit($otro->id), 'editar');

if ($htmlEditar !== null) {
    comprobar('editar: modo editar', str_contains($htmlEditar, 'data-modo="editar"'), true);
    comprobar('editar: con botón de guardar', str_contains($htmlEditar, 'id="formulario-guardar"'), true);
    comprobar('editar: con botón agregar franja', str_contains($htmlEditar, 'id="horario-agregar"'), true);
    comprobar('editar: método PUT', str_contains($htmlEditar, 'data-metodo="PUT"'), true);
    comprobar('editar: índice para nuevas filas', str_contains($htmlEditar, 'data-indice-horario="2"'), true);
    comprobar('editar: sin botón limpiar', str_contains($htmlEditar, 'id="formulario-limpiar"'), false);
    comprobar('editar: panel de mensajes', str_contains($htmlEditar, 'id="formulario-mensajes"'), true);
    comprobar('editar: lleva el hidden _method', str_contains($htmlEditar, 'name="_method"'), true);
}

$htmlCrear = $render((new DailyController())->index(), 'crear');

if ($htmlCrear !== null) {
    comprobar('crear: modo crear', str_contains($htmlCrear, 'data-modo="crear"'), true);
    comprobar('crear: POST', str_contains($htmlCrear, 'data-metodo="POST"'), true);
    comprobar('crear: con botón limpiar', str_contains($htmlCrear, 'id="formulario-limpiar"'), true);
    comprobar('crear: sin filas de horario', str_contains($htmlCrear, 'name="schedule[0]'), false);
    comprobar('crear: con el modal de impresión', str_contains($htmlCrear, 'id="modalImprimir"'), true);
    // "checked" también aparece dentro de los nombres [is_checked], así que se
    // busca el atributo real, que va precedido de un espacio.
    $marcados = substr_count($htmlCrear, ' checked');

    comprobar('crear: ningún check marcado al inicio', $marcados, 0);
}

$htmlListado = $render($controlador->index(), 'listado');

if ($htmlListado !== null) {
    comprobar('listado: tabla de DataTables', str_contains($htmlListado, 'id="tabla-diarios"'), true);
    comprobar('listado: dirección del AJAX', str_contains($htmlListado, 'data-url-tabla'), true);
    comprobar('listado: 4 columnas', substr_count($htmlListado, '<th scope="col"'), 4);
    comprobar('listado: cuerpo vacío para DataTables',
        preg_match('/<tbody>\s*<\/tbody>/', $htmlListado) === 1, true);
    foreach (['data-url-tabla', 'data-url-ver', 'data-url-editar', 'data-url-eliminar'] as $atributo) {
        comprobar('listado: atributo '.$atributo, str_contains($htmlListado, $atributo.'="'), true);
    }
}

DB::rollBack();

/* ==================================================================== */
titulo('RESULTADO');
/* ==================================================================== */

printf("Comprobaciones correctas: %d\n", $ok);
echo $fallos === 0 ? "Todas las comprobaciones pasaron.\n" : "Comprobaciones fallidas: {$fallos}\n";
echo "Transacción revertida: la base quedó sin datos de prueba.\n";
