<?php

namespace App\Planificacion;

use App\Excel\PlantillaDelPlanificador as Plantilla;
use App\Helpers\Helper;
use App\Models\ActionBlockDuration;
use App\Models\ActionBlockOutcome;
use App\Models\DailyPlan;
use App\Models\EnergyLevel;
use App\Models\GoalType;
use App\Models\PreparationItem;
use App\Models\ReflectionQuestion;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Lee el libro de "Planificación periódica" y dice qué hacer con cada hoja.
 *
 * La regla de oro es una sola: **sólo se crea lo que falta**. Un día que ya
 * existe en la base de datos no se vuelve a cargar nunca —sea pasado, de hoy o
 * futuro— y el Excel jamás pisa lo que hay guardado. Si un día se elimina desde
 * el listado, la corrida siguiente lo vuelve a crear con lo que traiga el libro.
 *
 * El lector no escribe nada: devuelve el plan de la corrida (qué hoja se crea,
 * cuál se omite, cuál se salta y por qué). El comando es el que decide si lo
 * ejecuta o si sólo lo muestra (--dry-run).
 *
 * Una hoja se salta completa cuando le falta un campo obligatorio: la energía,
 * los 3 objetivos, al menos una franja con hora y actividad, o el cierre del
 * día. Las secciones opcionales (antes de empezar, procrastinación, bloque de
 * acción y notas) se toleran: lo que no se reconozca queda anotado como aviso.
 */
class ImportadorDePlanificacion
{
    /* Lo que puede pasar con una hoja. */
    public const CREAR = 'crear';

    public const OMITIR = 'omitir';

    public const SALTAR = 'saltar';

    public const IGNORAR = 'ignorar';

    /** Hasta qué fila se buscan etiquetas en la columna B. */
    private const FILAS = 200;

    /** Días hacia atrás que se revisan buscando huecos sin hoja. */
    private const DIAS_DEL_AVISO = 366;

    /* ======================================================================
     |  Lectura del libro
     ====================================================================== */

    /**
     * El plan de la corrida para un libro.
     *
     * @return array{
     *     archivo: string,
     *     ruta: string,
     *     hojas: list<array{hoja: string, fecha: ?string, accion: string, motivo: ?string, datos: ?array, avisos: list<string>}>,
     *     resumen: array{crear: int, omitir: int, saltar: int, ignorar: int},
     *     desde: ?string,
     *     hasta: ?string,
     *     cubierto_hasta: ?string,
     *     en_bd_hasta: ?string,
     *     huecos: list<string>,
     *     sin_hoja: list<string>
     * }
     */
    public static function leer(string $ruta, ?CarbonInterface $hoy = null): array
    {
        $hoy = ($hoy ? Carbon::instance($hoy) : Helper::today())->startOfDay();

        // Los nombres de las hojas primero: es barato y evita abrir el libro
        // entero cuando no hay nada que hacer.
        $nombres = (new Xlsx)->listWorksheetNames($ruta);

        $fechas = [];       // 'Y-m-d' => nombre de la hoja
        $hojas = [];        // resultado, todavía sin ordenar
        $catalogos = null;  // se cargan sólo si hay algo que leer

        foreach ($nombres as $nombre) {
            // Las instrucciones se miran como si no existieran.
            if (in_array($nombre, Plantilla::HOJAS_IGNORADAS, true)) {
                continue;
            }

            $fecha = Helper::dateFromSheetName($nombre);

            if (! $fecha) {
                $hojas[] = self::hoja($nombre, null, self::IGNORAR, in_array($nombre, Plantilla::HOJAS_AUXILIARES, true)
                    ? 'Es la hoja de apoyo: duplícala y ponle la fecha del día (aaaa-mm-dd).'
                    : 'El nombre de la hoja no es una fecha (aaaa-mm-dd).');

                continue;
            }

            $clave = $fecha->toDateString();

            if (isset($fechas[$clave])) {
                $hojas[] = self::hoja($nombre, $clave, self::SALTAR, 'Fecha repetida en el libro: ya viene en la hoja «'.$fechas[$clave].'».');

                continue;
            }

            $fechas[$clave] = $nombre;
        }

        ksort($fechas);

        // Qué fechas del libro ya están guardadas.
        $enBd = self::fechasEnBd(array_keys($fechas));

        $aLeer = [];

        foreach ($fechas as $clave => $nombre) {
            if (isset($enBd[$clave])) {
                $hojas[] = self::hoja($nombre, $clave, self::OMITIR, 'Ya está en la base de datos: no se vuelve a cargar.');

                continue;
            }

            $aLeer[$clave] = $nombre;
        }

        // Sólo se abre el libro si de verdad hay días por crear.
        if ($aLeer !== []) {
            $catalogos = self::catalogos();
            $libro = self::abrir($ruta, array_values($aLeer));

            foreach ($aLeer as $clave => $nombre) {
                $hoja = $libro->getSheetByName($nombre);

                // El nombre y la fecha mandan desde afuera: una hoja que se
                // salta no siempre llega a saber cuál era.
                $hojas[] = ['hoja' => $nombre, 'fecha' => $clave] + ($hoja
                    ? self::leerHoja($hoja, Carbon::createFromFormat('Y-m-d', $clave), $catalogos)
                    : self::saltar('No se pudo abrir la hoja dentro del libro.'));
            }

            $libro->disconnectWorksheets();
        }

        // Las hojas sin fecha van al final, para que la tabla se lea por días.
        usort($hojas, fn (array $a, array $b) => [$a['fecha'] ?? '9999-12-31', $a['hoja']] <=> [$b['fecha'] ?? '9999-12-31', $b['hoja']]);

        $resumen = ['crear' => 0, 'omitir' => 0, 'saltar' => 0, 'ignorar' => 0];

        foreach ($hojas as $una) {
            $resumen[$una['accion']]++;
        }

        $claves = array_keys($fechas);

        return [
            'archivo' => basename($ruta),
            'ruta' => $ruta,
            'hojas' => $hojas,
            'resumen' => $resumen,
            'desde' => $claves === [] ? null : reset($claves),
            'hasta' => $claves === [] ? null : end($claves),
            'cubierto_hasta' => self::cubiertoHasta($claves, $enBd, array_keys($aLeer)),
            'en_bd_hasta' => $enBd === [] ? null : max(array_keys($enBd)),
            'huecos' => array_keys($aLeer),
            'sin_hoja' => self::sinHoja($claves, $hoy),
        ];
    }

    /* ======================================================================
     |  Una hoja de día
     ====================================================================== */

    /** Lee una hoja y arma los datos del día, o el motivo para saltarla. */
    private static function leerHoja(Worksheet $hoja, Carbon $fecha, array $catalogos): array
    {
        $avisos = [];
        $indice = self::indice($hoja);
        $titulos = self::titulos($indice);

        // 1. La fecha de la celda es opcional; si viene, tiene que coincidir.
        $filaFecha = $indice[Plantilla::clave(Plantilla::ETIQUETA_FECHA)] ?? null;

        if ($filaFecha !== null) {
            $enCelda = Helper::dateFromSpreadsheet(self::valor($hoja, $filaFecha, Plantilla::COLUMNA_VALOR));

            if ($enCelda && ! $enCelda->isSameDay($fecha)) {
                return self::saltar('La fecha de la celda ('.Helper::date($enCelda).') no coincide con el nombre de la hoja ('.Helper::date($fecha).').');
            }
        }

        // 2. La energía, obligatoria.
        $filaEnergia = $indice[Plantilla::clave(Plantilla::ETIQUETA_ENERGIA)] ?? null;

        if ($filaEnergia === null) {
            return self::saltar('No se encontró la etiqueta «'.Plantilla::ETIQUETA_ENERGIA.'» en la hoja.');
        }

        $energia = self::texto($hoja, $filaEnergia, Plantilla::COLUMNA_VALOR);

        if ($energia === '') {
            return self::saltar('Falta la energía del día ('.Plantilla::ETIQUETA_ENERGIA.').');
        }

        $energiaId = $catalogos['energias'][Helper::normalize($energia)] ?? null;

        if ($energiaId === null) {
            return self::saltar('La energía «'.$energia.'» no está en el catálogo ('.implode(', ', $catalogos['energias_nombres']).').');
        }

        // 3. Los 3 objetivos, obligatorios.
        $filaObjetivos = $indice[Plantilla::clave(Plantilla::TITULO_OBJETIVOS)] ?? null;

        if ($filaObjetivos === null) {
            return self::saltar('No se encontró la sección «'.Plantilla::TITULO_OBJETIVOS.'».');
        }

        $objetivos = [];

        for ($ranura = 1; $ranura <= DailyPlan::MAX_GOALS; $ranura++) {
            $fila = $filaObjetivos + 1 + $ranura;
            $etiqueta = self::texto($hoja, $fila, Plantilla::COLUMNA_ETIQUETA);
            $descripcion = self::texto($hoja, $fila, Plantilla::COLUMNA_VALOR);

            if ($descripcion === '') {
                return self::saltar('Falta el objetivo '.$ranura.($etiqueta === '' ? '' : ' («'.$etiqueta.'»)').'.');
            }

            if (mb_strlen($descripcion) > 255) {
                return self::saltar('La descripción del objetivo '.$ranura.' pasa de 255 caracteres.');
            }

            $hecho = Helper::boolFromSpreadsheet(self::valor($hoja, $fila, Plantilla::COLUMNA_DETALLE));

            if ($hecho === null) {
                $avisos[] = 'Objetivo '.$ranura.': el «Cumplido» no se entendió como Sí/No; se tomó como No.';
                $hecho = false;
            }

            $objetivos[] = [
                'slot' => $ranura,
                'goal_type_id' => $catalogos['objetivos'][Helper::normalize($etiqueta)] ?? $catalogos['objetivos_por_ranura'][$ranura] ?? null,
                'description' => $descripcion,
                'is_done' => $hecho,
            ];
        }

        // 4. El horario: al menos una franja completa, obligatorio.
        $filaHorario = $indice[Plantilla::clave(Plantilla::TITULO_HORARIO)] ?? null;

        if ($filaHorario === null) {
            return self::saltar('No se encontró la sección «'.Plantilla::TITULO_HORARIO.'».');
        }

        $horario = [];
        $franja = 0;

        for ($fila = $filaHorario + 2; $fila <= self::finDeSeccion($hoja, $titulos, $filaHorario); $fila++) {
            $hora = self::valor($hoja, $fila, Plantilla::COLUMNA_ETIQUETA);
            $actividad = self::texto($hoja, $fila, Plantilla::COLUMNA_VALOR);

            // Fila vacía: no es una franja, se ignora.
            if (Helper::isBlank($hora) && $actividad === '') {
                continue;
            }

            $franja++;

            if (Helper::isBlank($hora) || $actividad === '') {
                return self::saltar('La franja '.$franja.' del horario está a medias: si escribes una hora escribe su actividad, y si escribes una actividad ponle su hora.');
            }

            $horaNormal = Helper::timeFromSpreadsheet($hora);

            if ($horaNormal === null) {
                return self::saltar('No se entiende la hora «'.self::texto($hoja, $fila, Plantilla::COLUMNA_ETIQUETA).'» de la franja '.$franja.' del horario.');
            }

            if (mb_strlen($actividad) > 255) {
                return self::saltar('La actividad de la franja '.$franja.' pasa de 255 caracteres.');
            }

            $hecho = Helper::boolFromSpreadsheet(self::valor($hoja, $fila, Plantilla::COLUMNA_DETALLE));

            if ($hecho === null) {
                $avisos[] = 'Franja '.$franja.': el «Hecho» no se entendió como Sí/No; se tomó como No.';
                $hecho = false;
            }

            $horario[] = [
                'start_time' => $horaNormal,
                'activity' => $actividad,
                'is_done' => $hecho,
            ];
        }

        if ($horario === []) {
            return self::saltar('El horario no tiene ninguna franja con hora y actividad.');
        }

        // 5. El cierre del día, obligatorio en todas las hojas.
        $filaCierre = $indice[Plantilla::clave(Plantilla::TITULO_CIERRE)] ?? null;

        if ($filaCierre === null) {
            return self::saltar('No se encontró la sección «'.Plantilla::TITULO_CIERRE.'».');
        }

        $cierre = [];

        foreach ([
            'achievements' => [Plantilla::ETIQUETA_CIERRE_LOGROS, 2000],
            'pending' => [Plantilla::ETIQUETA_CIERRE_PENDIENTE, 2000],
            'pending_when' => [Plantilla::ETIQUETA_CIERRE_CUANDO, 255],
            'proud_of' => [Plantilla::ETIQUETA_CIERRE_ORGULLO, 2000],
        ] as $campo => [$etiqueta, $largo]) {
            $texto = self::texto($hoja, $indice[Plantilla::clave($etiqueta)] ?? 0, Plantilla::COLUMNA_VALOR);

            if ($texto === '') {
                return self::saltar('Falta el cierre del día: «'.$etiqueta.'».');
            }

            if (mb_strlen($texto) > $largo) {
                return self::saltar('«'.$etiqueta.'» pasa de '.$largo.' caracteres.');
            }

            $cierre[$campo] = $texto;
        }

        // 6. Secciones opcionales: se toleran, con avisos.
        $preparacion = self::leerPreparacion($hoja, $indice, $titulos, $catalogos, $avisos);
        $reflexiones = self::leerProcrastinacion($hoja, $indice, $titulos, $catalogos, $avisos);
        $bloques = self::leerBloqueDeAccion($hoja, $indice, $catalogos, $fecha, $avisos);
        $notas = self::leerNotas($hoja, $indice, $avisos);

        return [
            'hoja' => $hoja->getTitle(),
            'fecha' => $fecha->toDateString(),
            'accion' => self::CREAR,
            'motivo' => null,
            'avisos' => $avisos,
            'datos' => array_merge([
                'plan_date' => $fecha->toDateString(),
                'energy_level_id' => $energiaId,
                'goals' => $objetivos,
                'schedule' => $horario,
                'preparation' => $preparacion,
                'reflections' => $reflexiones,
                'action_blocks' => $bloques,
                'notes' => $notas,
            ], $cierre),
        ];
    }

    /* ======================================================================
     |  Secciones opcionales
     ====================================================================== */

    /** "Antes de empezar": cada ítem del catálogo con su Sí/No y su detalle. */
    private static function leerPreparacion(Worksheet $hoja, array $indice, array $titulos, array $catalogos, array &$avisos): array
    {
        $fila = $indice[Plantilla::clave(Plantilla::TITULO_PREPARACION)] ?? null;

        if ($fila === null) {
            return [];
        }

        $lista = [];

        for ($actual = $fila + 2; $actual <= self::finDeSeccion($hoja, $titulos, $fila); $actual++) {
            $nombre = self::texto($hoja, $actual, Plantilla::COLUMNA_ETIQUETA);

            if ($nombre === '') {
                continue;
            }

            $itemId = $catalogos['items'][Helper::normalize($nombre)] ?? null;

            if ($itemId === null) {
                $avisos[] = 'El ítem «'.$nombre.'» de «Antes de empezar» no está en el catálogo: se ignoró.';

                continue;
            }

            $marcado = Helper::boolFromSpreadsheet(self::valor($hoja, $actual, Plantilla::COLUMNA_VALOR));

            if ($marcado === null) {
                $avisos[] = 'El ítem «'.$nombre.'» no tiene un Sí/No entendible; se tomó como No.';
                $marcado = false;
            }

            $lista[] = [
                'preparation_item_id' => $itemId,
                'is_checked' => $marcado,
                'preparation_items_description' => self::recortar(
                    self::texto($hoja, $actual, Plantilla::COLUMNA_DETALLE), 2000, 'El detalle de «'.$nombre.'»', $avisos
                ),
            ];
        }

        return $lista;
    }

    /** "Si estoy procrastinando me pregunto": cada pregunta con su Sí/No y su respuesta. */
    private static function leerProcrastinacion(Worksheet $hoja, array $indice, array $titulos, array $catalogos, array &$avisos): array
    {
        $fila = $indice[Plantilla::clave(Plantilla::TITULO_PROCRASTINACION)] ?? null;

        if ($fila === null) {
            return [];
        }

        $lista = [];

        for ($actual = $fila + 2; $actual <= self::finDeSeccion($hoja, $titulos, $fila); $actual++) {
            $pregunta = self::texto($hoja, $actual, Plantilla::COLUMNA_ETIQUETA);

            if ($pregunta === '') {
                continue;
            }

            $preguntaId = $catalogos['preguntas'][Helper::normalize($pregunta)] ?? null;

            if ($preguntaId === null) {
                $avisos[] = 'La pregunta «'.$pregunta.'» no está en el catálogo: se ignoró.';

                continue;
            }

            $marcado = Helper::boolFromSpreadsheet(self::valor($hoja, $actual, Plantilla::COLUMNA_VALOR));

            if ($marcado === null) {
                $avisos[] = 'La pregunta «'.$pregunta.'» no tiene un Sí/No entendible; se tomó como No.';
                $marcado = false;
            }

            $lista[] = [
                'reflection_question_id' => $preguntaId,
                'is_checked' => $marcado,
                'answer' => self::recortar(
                    self::texto($hoja, $actual, Plantilla::COLUMNA_DETALLE), 2000, 'La respuesta a «'.$pregunta.'»', $avisos
                ),
            ];
        }

        return $lista;
    }

    /** "Bloque de acción": un bloque, con su duración, su resultado y su tarea. */
    private static function leerBloqueDeAccion(Worksheet $hoja, array $indice, array $catalogos, Carbon $fecha, array &$avisos): array
    {
        if (! isset($indice[Plantilla::clave(Plantilla::TITULO_ACCION)])) {
            return [];
        }

        $duracion = self::texto($hoja, $indice[Plantilla::clave(Plantilla::ETIQUETA_ACCION_DURACION)] ?? 0, Plantilla::COLUMNA_VALOR);
        $resultado = self::texto($hoja, $indice[Plantilla::clave(Plantilla::ETIQUETA_ACCION_RESULTADO)] ?? 0, Plantilla::COLUMNA_VALOR);
        $tarea = self::texto($hoja, $indice[Plantilla::clave(Plantilla::ETIQUETA_ACCION_TAREA)] ?? 0, Plantilla::COLUMNA_VALOR);
        $inicio = self::valor($hoja, $indice[Plantilla::clave(Plantilla::ETIQUETA_ACCION_INICIO)] ?? 0, Plantilla::COLUMNA_VALOR);
        $fin = self::valor($hoja, $indice[Plantilla::clave(Plantilla::ETIQUETA_ACCION_FIN)] ?? 0, Plantilla::COLUMNA_VALOR);

        $duracionId = null;

        if ($duracion !== '') {
            $duracionId = $catalogos['duraciones'][Helper::normalize($duracion)]
                ?? $catalogos['duraciones_minutos'][(int) preg_replace('/\D+/', '', $duracion)]
                ?? null;

            if ($duracionId === null) {
                $avisos[] = 'La duración «'.$duracion.'» del bloque de acción no está en el catálogo: se ignoró.';
            }
        }

        $resultadoId = null;

        if ($resultado !== '') {
            $resultadoId = $catalogos['resultados'][Helper::normalize($resultado)] ?? null;

            if ($resultadoId === null) {
                $avisos[] = 'El resultado «'.$resultado.'» del bloque de acción no está en el catálogo: se ignoró.';
            }
        }

        $inicioEn = Helper::isBlank($inicio) ? null : Helper::dateTimeFrom($fecha, $inicio);
        $finEn = Helper::isBlank($fin) ? null : Helper::dateTimeFrom($fecha, $fin);

        if (! Helper::isBlank($inicio) && $inicioEn === null) {
            $avisos[] = 'La hora de inicio del bloque de acción no se entendió: se ignoró.';
        }

        if (! Helper::isBlank($fin) && $finEn === null) {
            $avisos[] = 'La hora de fin del bloque de acción no se entendió: se ignoró.';
        }

        if ($inicioEn && $finEn && $finEn->lessThan($inicioEn)) {
            $avisos[] = 'El bloque de acción termina antes de empezar: se guardaron las horas tal como están.';
        }

        $tarea = self::recortar($tarea, 255, 'La tarea del bloque de acción', $avisos);

        $vacio = $duracion === '' && $resultado === '' && $tarea === '' && $inicioEn === null && $finEn === null;

        if ($vacio) {
            return [];
        }

        return [[
            'action_block_duration_id' => $duracionId,
            'action_block_outcome_id' => $resultadoId,
            'task' => $tarea === '' ? null : $tarea,
            'started_at' => $inicioEn?->format('Y-m-d H:i:s'),
            'finished_at' => $finEn?->format('Y-m-d H:i:s'),
        ]];
    }

    /** "Notas y recordatorios": una nota por línea. */
    private static function leerNotas(Worksheet $hoja, array $indice, array &$avisos): array
    {
        $fila = $indice[Plantilla::clave(Plantilla::ETIQUETA_NOTAS)] ?? null;

        if ($fila === null) {
            return [];
        }

        $texto = trim((string) self::valor($hoja, $fila, Plantilla::COLUMNA_VALOR));

        if ($texto === '') {
            return [];
        }

        $notas = [];
        $orden = 1;

        foreach (preg_split('/\r\n|\r|\n/', $texto) ?: [] as $linea) {
            $contenido = trim($linea);

            if ($contenido === '') {
                continue;
            }

            $notas[] = [
                'content' => self::recortar($contenido, 1000, 'Una nota', $avisos),
                'sort_order' => $orden++,
            ];
        }

        return $notas;
    }

    /* ======================================================================
     |  Catálogos
     ====================================================================== */

    /** Los catálogos indexados por nombre normalizado, para resolver las celdas. */
    private static function catalogos(): array
    {
        $energias = EnergyLevel::query()->orderBy('sort_order')->get();

        return [
            'energias' => $energias->mapWithKeys(fn (EnergyLevel $nivel) => [
                Helper::normalize($nivel->name) => $nivel->id,
                Helper::normalize($nivel->slug) => $nivel->id,
            ])->all(),
            'energias_nombres' => $energias->pluck('name')->all(),

            'objetivos' => GoalType::query()->get()
                ->mapWithKeys(fn (GoalType $tipo) => [Helper::normalize($tipo->name) => $tipo->id])->all(),
            'objetivos_por_ranura' => GoalType::query()->orderBy('sort_order')->pluck('id', 'sort_order')
                ->values()->take(DailyPlan::MAX_GOALS)->values()->all(),

            'items' => PreparationItem::query()->get()
                ->mapWithKeys(fn (PreparationItem $item) => [Helper::normalize($item->name) => $item->id])->all(),

            'preguntas' => ReflectionQuestion::query()
                ->where('category', ReflectionQuestion::CATEGORY_PROCRASTINATION)->get()
                ->mapWithKeys(fn (ReflectionQuestion $pregunta) => [Helper::normalize((string) $pregunta->question) => $pregunta->id])->all(),

            'duraciones' => ActionBlockDuration::query()->get()
                ->mapWithKeys(fn (ActionBlockDuration $duracion) => [Helper::normalize($duracion->label) => $duracion->id])->all(),
            'duraciones_minutos' => ActionBlockDuration::query()->pluck('id', 'minutes')->all(),

            'resultados' => ActionBlockOutcome::query()->get()
                ->mapWithKeys(fn (ActionBlockOutcome $resultado) => [
                    Helper::normalize($resultado->name) => $resultado->id,
                    Helper::normalize($resultado->slug) => $resultado->id,
                ])->all(),
        ];
    }

    /* ======================================================================
     |  Consultas a la base
     ====================================================================== */

    /**
     * De las fechas pedidas, cuáles ya están guardadas.
     *
     * @param  list<string>  $fechas
     * @return array<string, true>
     */
    private static function fechasEnBd(array $fechas): array
    {
        if ($fechas === []) {
            return [];
        }

        return DailyPlan::query()
            ->whereIn(DB::raw('date(plan_date)'), $fechas)
            ->pluck('plan_date')
            ->mapWithKeys(fn (mixed $fecha) => [Helper::date($fecha, 'Y-m-d') => true])
            ->all();
    }

    /**
     * Hasta qué día el libro está cubierto sin huecos: la última fecha
     * consecutiva, desde la primera del libro, que ya está en la base.
     *
     * @param  list<string>  $claves  Todas las fechas del libro, ordenadas.
     * @param  array<string, true>  $enBd
     * @param  list<string>  $aLeer  Fechas que la corrida va a crear.
     */
    private static function cubiertoHasta(array $claves, array $enBd, array $aLeer): ?string
    {
        $cubierto = null;

        foreach ($claves as $clave) {
            if (in_array($clave, $aLeer, true)) {
                break;
            }

            $cubierto = $clave;
        }

        return $cubierto;
    }

    /**
     * Días pasados que el libro no trae y que tampoco están en la base: son los
     * huecos que el libro no puede llenar. Se avisan, no son un error.
     *
     * @param  list<string>  $claves
     * @return list<string>
     */
    private static function sinHoja(array $claves, Carbon $hoy): array
    {
        if ($claves === []) {
            return [];
        }

        $desde = Carbon::createFromFormat('Y-m-d', reset($claves));
        $hasta = $hoy->copy()->subDay();

        if ($desde->greaterThan($hasta) || $desde->diffInDays($hasta) > self::DIAS_DEL_AVISO) {
            return [];
        }

        $delLibro = array_flip($claves);
        $enBd = DailyPlan::query()
            ->whereRaw('date(plan_date) between ? and ?', [$desde->toDateString(), $hasta->toDateString()])
            ->pluck('plan_date')
            ->mapWithKeys(fn (mixed $fecha) => [Helper::date($fecha, 'Y-m-d') => true])
            ->all();

        $faltantes = [];

        for ($dia = $desde->copy(); $dia->lessThanOrEqualTo($hasta); $dia->addDay()) {
            $clave = $dia->toDateString();

            if (! isset($delLibro[$clave]) && ! isset($enBd[$clave])) {
                $faltantes[] = $clave;
            }
        }

        return $faltantes;
    }

    /* ======================================================================
     |  Internos de la hoja
     ====================================================================== */

    /** Abre el libro cargando sólo las hojas que se van a leer. */
    private static function abrir(string $ruta, array $hojas): Spreadsheet
    {
        $lector = new Xlsx;
        // Los formatos hacen falta para entender las fechas.
        $lector->setReadDataOnly(false);
        $lector->setLoadSheetsOnly($hojas);

        return $lector->load($ruta);
    }

    /**
     * El índice de la hoja: la clave de cada etiqueta de la columna B y la fila
     * donde está. Si una etiqueta se repite, manda la primera.
     *
     * @return array<string, int>
     */
    private static function indice(Worksheet $hoja): array
    {
        $indice = [];
        $ultima = min($hoja->getHighestRow(), self::FILAS);

        for ($fila = 1; $fila <= $ultima; $fila++) {
            $clave = Plantilla::clave((string) $hoja->getCell(Plantilla::COLUMNA_ETIQUETA.$fila)->getValue());

            if ($clave !== '' && ! isset($indice[$clave])) {
                $indice[$clave] = $fila;
            }
        }

        return $indice;
    }

    /**
     * Las filas donde empieza cada sección, ordenadas.
     *
     * @param  array<string, int>  $indice
     * @return list<int>
     */
    private static function titulos(array $indice): array
    {
        $filas = [];

        foreach (Plantilla::SECCIONES as $titulo) {
            $fila = $indice[Plantilla::clave($titulo)] ?? null;

            if ($fila !== null) {
                $filas[] = $fila;
            }
        }

        sort($filas);

        return $filas;
    }

    /** La última fila de una sección: la anterior al título siguiente. */
    private static function finDeSeccion(Worksheet $hoja, array $titulos, int $fila): int
    {
        $fin = min($hoja->getHighestRow(), self::FILAS);

        foreach ($titulos as $titulo) {
            if ($titulo > $fila && $titulo - 1 < $fin) {
                $fin = $titulo - 1;
            }
        }

        return $fin;
    }

    /** El valor crudo de una celda. */
    private static function valor(Worksheet $hoja, int $fila, string $columna): mixed
    {
        if ($fila < 1) {
            return null;
        }

        return $hoja->getCell($columna.$fila)->getValue();
    }

    /** El texto limpio de una celda. */
    private static function texto(Worksheet $hoja, int $fila, string $columna): string
    {
        $valor = self::valor($hoja, $fila, $columna);

        return is_scalar($valor) ? Helper::strip((string) $valor) : '';
    }

    /** Recorta un texto opcional demasiado largo y deja el aviso. */
    private static function recortar(string $texto, int $largo, string $etiqueta, array &$avisos): ?string
    {
        if ($texto === '') {
            return null;
        }

        if (mb_strlen($texto) <= $largo) {
            return $texto;
        }

        $avisos[] = $etiqueta.' pasa de '.$largo.' caracteres: se recortó.';

        return mb_substr($texto, 0, $largo);
    }

    /* ======================================================================
     |  Armar el resultado
     ====================================================================== */

    /** Una hoja del resultado. */
    private static function hoja(string $nombre, ?string $fecha, string $accion, ?string $motivo, ?array $datos = null, array $avisos = []): array
    {
        return [
            'hoja' => $nombre,
            'fecha' => $fecha,
            'accion' => $accion,
            'motivo' => $motivo,
            'datos' => $datos,
            'avisos' => $avisos,
        ];
    }

    /** Una hoja que se salta por completo. */
    private static function saltar(string $motivo, array $avisos = []): array
    {
        return [
            'hoja' => '',
            'fecha' => null,
            'accion' => self::SALTAR,
            'motivo' => $motivo,
            'datos' => null,
            'avisos' => $avisos,
        ];
    }
}
