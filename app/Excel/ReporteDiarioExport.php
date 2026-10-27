<?php

namespace App\Excel;

use App\Helpers\Helper;
use App\Models\DailyPlan;
use App\Reportes\AnalisisDeDesempeno;
use App\Reportes\RedaccionDelResumen;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Shared\Date as FechaExcel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Reporte detallado de los diarios en Excel (.xlsx).
 *
 * Dos hojas:
 *
 *   1. "Diarios": un diario por fila, con la fecha, la energía, la meta
 *      semanal, los tres objetivos, el "antes de empezar", las preguntas de
 *      procrastinación, el bloque de acción, las actividades diarias, el
 *      seguimiento personal, la reflexión de la semana, el cierre y las notas.
 *   2. "Resumen": la lectura del período con los mismos números y textos del
 *      resumen en PDF, para que el archivo también sirva de análisis.
 *
 * Los colores son los institucionales de Transforma-Conecta. Se escribe en
 * memoria, así que no deja archivos temporales en el servidor.
 */
class ReporteDiarioExport
{
    /* Paleta institucional, la misma de public/css/styles.css. */
    public const AZUL_OSCURO = '002060';

    public const AZUL = '0080D0';

    public const TURQUESA = '00AE9C';

    public const TURQUESA_OSCURO = '006C60';

    public const NARANJA = 'F0600C';

    public const ROJO = 'D90B0B';

    public const GRIS_BORDE = 'BFC9D9';

    public const GRIS_CLARO = 'F4F7FB';

    public const BLANCO = 'FFFFFF';

    /** La fila de encabezados y la primera de datos. */
    public const FILA_ENCABEZADO = 4;

    /**
     * Las columnas del reporte.
     *
     * Cada sección de la hoja impresa tiene su color. El orden de esta lista es
     * el orden de las celdas que devuelve DailyPlan::toReportArray().
     */
    public const COLUMNAS = [
        ['titulo' => 'Fecha', 'color' => self::AZUL_OSCURO, 'ancho' => 12],
        ['titulo' => 'Día', 'color' => self::AZUL_OSCURO, 'ancho' => 11],
        ['titulo' => 'Energía', 'color' => self::AZUL_OSCURO, 'ancho' => 10],

        // Mi meta semanal.
        ['titulo' => 'Meta semanal', 'color' => self::NARANJA, 'ancho' => 34],
        ['titulo' => '¿Por qué es importante?', 'color' => self::NARANJA, 'ancho' => 30],

        // Mis 3 objetivos principales de hoy.
        ['titulo' => 'Debo hacer', 'color' => self::NARANJA, 'ancho' => 34],
        ['titulo' => 'Quiero hacer', 'color' => self::NARANJA, 'ancho' => 34],
        ['titulo' => 'Algo para mí', 'color' => self::NARANJA, 'ancho' => 34],

        // Antes de empezar: un Sí/No por ítem.
        ['titulo' => 'Materiales', 'color' => self::TURQUESA, 'ancho' => 13],
        ['titulo' => 'Ropa adecuada', 'color' => self::TURQUESA, 'ancho' => 13],
        ['titulo' => 'Alimentación', 'color' => self::TURQUESA, 'ancho' => 13],
        ['titulo' => 'Cargar dispositivos', 'color' => self::TURQUESA, 'ancho' => 13],
        ['titulo' => 'Espacio organizado', 'color' => self::TURQUESA, 'ancho' => 13],
        ['titulo' => 'Todo lo necesario', 'color' => self::TURQUESA, 'ancho' => 13],

        // Si estoy procrastinando: un Sí/No por pregunta.
        ['titulo' => '¿Qué estoy evitando?', 'color' => self::NARANJA, 'ancho' => 15],
        ['titulo' => '¿Por qué lo estoy postergando?', 'color' => self::NARANJA, 'ancho' => 15],
        ['titulo' => '¿Qué me está distrayendo?', 'color' => self::NARANJA, 'ancho' => 15],
        ['titulo' => '¿Necesito desglosarlo?', 'color' => self::NARANJA, 'ancho' => 15],
        ['titulo' => '¿Qué puedo hacer en 5 minutos?', 'color' => self::NARANJA, 'ancho' => 15],

        // Bloque de acción.
        ['titulo' => 'Voy a trabajar durante', 'color' => self::TURQUESA_OSCURO, 'ancho' => 18],
        ['titulo' => 'Cuando termine este bloque', 'color' => self::TURQUESA_OSCURO, 'ancho' => 20],
        ['titulo' => '¿En qué vas a trabajar?', 'color' => self::TURQUESA_OSCURO, 'ancho' => 34],

        // Actividades diarias y seguimiento de la meta.
        ['titulo' => 'Actividades diarias', 'color' => self::TURQUESA, 'ancho' => 42],
        ['titulo' => 'Minutos de actividades', 'color' => self::TURQUESA, 'ancho' => 12],
        ['titulo' => 'Avance de actividades', 'color' => self::TURQUESA, 'ancho' => 12],
        ['titulo' => 'Qué hice por mi meta', 'color' => self::NARANJA, 'ancho' => 34],
        ['titulo' => 'Qué me ayudó', 'color' => self::NARANJA, 'ancho' => 30],
        ['titulo' => 'Qué me dificultó', 'color' => self::NARANJA, 'ancho' => 30],

        // Reflexión de la semana.
        ['titulo' => 'Reflexión semanal', 'color' => self::ROJO, 'ancho' => 44],

        // Cierre del día.
        ['titulo' => 'Logré', 'color' => self::ROJO, 'ancho' => 40],
        ['titulo' => 'Pendiente', 'color' => self::ROJO, 'ancho' => 40],
        ['titulo' => '¿Cuándo lo haré?', 'color' => self::ROJO, 'ancho' => 18],
        ['titulo' => 'Orgulloso/a de mí', 'color' => self::ROJO, 'ancho' => 40],

        ['titulo' => 'Notas del día', 'color' => self::AZUL, 'ancho' => 34],
    ];

    /**
     * Genera el archivo y devuelve su contenido binario.
     *
     * @param  Collection<int, DailyPlan>  $planes
     * @param  array{fecha?: mixed, energia?: mixed, palabra?: mixed}  $filtros
     */
    public static function generar(Collection $planes, array $filtros = [], ?CarbonInterface $generadoEn = null): string
    {
        $generadoEn ??= now();

        $libro = new Spreadsheet;

        self::armarHoja($libro, $planes, $filtros, $generadoEn);
        self::agregarResumen($libro, $planes, $filtros, $generadoEn);

        $libro->setActiveSheetIndex(0);

        $escritor = new Xlsx($libro);

        // Se guarda en memoria: nada de archivos temporales en el servidor.
        ob_start();
        $escritor->save('php://output');
        $contenido = (string) ob_get_clean();

        // Libera la memoria que ocupaban las hojas.
        $libro->disconnectWorksheets();

        return $contenido;
    }

    /** El nombre del archivo, según si se aplicaron filtros o no. */
    public static function nombreArchivo(array $filtros = [], ?CarbonInterface $generadoEn = null): string
    {
        $conFiltros = Helper::strip($filtros['fecha'] ?? null) !== ''
            || Helper::strip($filtros['energia'] ?? null) !== ''
            || Helper::strip($filtros['palabra'] ?? null) !== '';

        $fecha = ($generadoEn ?? now())->format('Y-m-d');

        return 'reporte-diario'.($conFiltros ? '-filtrado' : '').'-'.$fecha.'.xlsx';
    }

    /* ======================================================================
     |  Hoja 1 · Diarios
     ====================================================================== */

    /** Arma la hoja con un diario por fila. */
    private static function armarHoja(
        Spreadsheet $libro,
        Collection $planes,
        array $filtros,
        CarbonInterface $generadoEn
    ): Worksheet {
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Diarios');

        $ultimaColumna = self::letra(count(self::COLUMNAS));
        $idioma = $libro->getDefaultStyle()->getFont();

        // 1. Encabezado de la institución.
        $hoja->mergeCells('A1:'.$ultimaColumna.'1');
        $hoja->setCellValue('A1', 'MI PLANIFICADOR DIARIO · REPORTE DETALLADO');
        self::pintarFranja($hoja, 'A1:'.$ultimaColumna.'1', self::AZUL_OSCURO, 14, true);
        $hoja->getRowDimension(1)->setRowHeight(26);

        // 2. Programa, fecha de generación, cantidad y filtros aplicados.
        $hoja->mergeCells('A2:'.$ultimaColumna.'2');
        $hoja->setCellValue('A2', self::subtitulo($planes, $filtros, $generadoEn));
        self::pintarFranja($hoja, 'A2:'.$ultimaColumna.'2', self::TURQUESA, 10, false);
        $hoja->getRowDimension(2)->setRowHeight(18);

        // 3. Encabezados de columna, cada uno con el color de su sección.
        foreach (self::COLUMNAS as $indice => $columna) {
            $letra = self::letra($indice + 1);
            $celda = $letra.self::FILA_ENCABEZADO;

            $hoja->setCellValue($celda, $columna['titulo']);
            $hoja->getColumnDimension($letra)->setWidth($columna['ancho']);
            $hoja->getStyle($celda)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB($columna['color']);
            $hoja->getStyle($celda)->getFont()
                ->setBold(true)
                ->setSize(11)
                ->getColor()->setRGB(self::BLANCO);
            $hoja->getStyle($celda)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER)
                ->setWrapText(true);
        }

        $hoja->getRowDimension(self::FILA_ENCABEZADO)->setRowHeight(32);

        // 4. Un diario por fila.
        $fila = self::FILA_ENCABEZADO + 1;

        foreach ($planes as $indice => $plan) {
            $datos = $plan->toReportArray();

            $hoja->setCellValue('A'.$fila, FechaExcel::PHPToExcel($datos['date']));
            $hoja->getStyle('A'.$fila)->getNumberFormat()->setFormatCode('DD/MM/YYYY');

            // Las demás celdas siguen el orden de las columnas: la primera ya
            // es la fecha, así que se empieza desde la segunda.
            foreach (array_values($datos['cells']) as $posicion => $valor) {
                $hoja->setCellValue(self::letra($posicion + 2).$fila, $valor);
            }

            $rango = 'A'.$fila.':'.$ultimaColumna.$fila;

            $hoja->getStyle($rango)->getAlignment()
                ->setVertical(Alignment::VERTICAL_TOP)
                ->setWrapText(true);
            $hoja->getStyle($rango)->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)
                ->getColor()->setRGB(self::GRIS_BORDE);

            // Las filas pares se pintan apenas distinto para leer mejor.
            if ($indice % 2 === 1) {
                $hoja->getStyle($rango)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB(self::GRIS_CLARO);
            }

            $hoja->getStyle('A'.$fila)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $hoja->getStyle('B'.$fila)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $hoja->getStyle('C'.$fila)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $hoja->getRowDimension($fila)->setRowHeight(self::alturaDeFila($datos));

            $fila++;
        }

        $ultimaFila = max($fila - 1, self::FILA_ENCABEZADO);

        // 5. Filtro automático, panel congelado y márgenes de impresión.
        $hoja->setAutoFilter('A'.self::FILA_ENCABEZADO.':'.$ultimaColumna.$ultimaFila);
        $hoja->freezePane('A'.(self::FILA_ENCABEZADO + 1));
        $hoja->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $hoja->getPageMargins()->setTop(0.5)->setBottom(0.5)->setLeft(0.4)->setRight(0.4);

        // La fuente por defecto del libro, en un tono institucional.
        $idioma->setName('Calibri')->setSize(10)->getColor()->setRGB('10233F');

        return $hoja;
    }

    /* ======================================================================
     |  Hoja 2 · Resumen
     ====================================================================== */

    /**
     * La hoja de análisis: los números del período, la lectura escrita y el
     * detalle de las actividades y las metas semanales.
     */
    private static function agregarResumen(
        Spreadsheet $libro,
        Collection $planes,
        array $filtros,
        CarbonInterface $generadoEn
    ): void {
        $hoja = $libro->createSheet();
        $hoja->setTitle('Resumen');

        $analisis = AnalisisDeDesempeno::de($planes, self::alcance($filtros));
        $textos = RedaccionDelResumen::observaciones($analisis);
        $titulos = RedaccionDelResumen::titulos();

        $hoja->getColumnDimension('A')->setWidth(36);
        $hoja->getColumnDimension('B')->setWidth(18);
        $hoja->getColumnDimension('C')->setWidth(58);
        $hoja->getColumnDimension('D')->setWidth(18);
        $hoja->getColumnDimension('E')->setWidth(14);
        $hoja->getColumnDimension('F')->setWidth(16);

        // Encabezado.
        $hoja->mergeCells('A1:F1');
        $hoja->setCellValue('A1', 'MI PLANIFICADOR DIARIO · ANÁLISIS DEL PERÍODO');
        self::pintarFranja($hoja, 'A1:F1', self::AZUL_OSCURO, 14, true);
        $hoja->getRowDimension(1)->setRowHeight(26);

        $hoja->mergeCells('A2:F2');
        $hoja->setCellValue('A2', 'Programa de Desarrollo Personal "Transforma-Conecta"   ·   '
            .'MI RUTINA ME FORTALECE · PEQUEÑAS ACCIONES, GRANDES CAMBIOS   ·   Generado el '
            .Helper::dateTime($generadoEn));
        self::pintarFranja($hoja, 'A2:F2', self::TURQUESA, 10, false);
        $hoja->getRowDimension(2)->setRowHeight(20);

        $fila = 4;

        // Los números del período.
        self::tituloDeSeccion($hoja, $fila++, 'NÚMEROS DEL PERÍODO', 6);
        self::filaDeDatos($hoja, $fila++, 'Días registrados', $analisis['dias'],
            $analisis['desde'] && $analisis['hasta']
                ? 'Del '.Helper::date($analisis['desde']).' al '.Helper::date($analisis['hasta'])
                : '');
        self::filaDeDatos($hoja, $fila++, 'Rendimiento promedio', $analisis['global']['rendimiento'],
            'Mejor '.$analisis['global']['rendimiento_mejor'].' · peor '.$analisis['global']['rendimiento_peor']);
        self::filaDeDatos($hoja, $fila++, 'Horario cumplido', $analisis['global']['horario_pct'].'%',
            $analisis['global']['horario_hechas'].' de '.$analisis['global']['horario_total']);
        self::filaDeDatos($hoja, $fila++, 'Objetivos alcanzados', $analisis['global']['objetivos_pct'].'%',
            $analisis['global']['objetivos_hechos'].' de '.$analisis['global']['objetivos_total']);
        self::filaDeDatos($hoja, $fila++, 'Actividades diarias', $analisis['global']['actividades_pct'].'%',
            $analisis['global']['actividades_hechas'].' de '.$analisis['global']['actividades_total']
            .' · '.Helper::minutesToHuman($analisis['global']['actividad_minutos']).' en total');
        self::filaDeDatos($hoja, $fila++, 'Señales de procrastinación', $analisis['global']['senales'],
            'Promedio '.$analisis['global']['senales_promedio'].' por día');
        self::filaDeDatos($hoja, $fila++, 'Correlación señales / rendimiento',
            $analisis['correlacion']['r'] ?? '—',
            $analisis['correlacion']['lectura'] ?? '');
        self::filaDeDatos($hoja, $fila++, 'Metas semanales',
            count($analisis['metasSemanales']),
            'Con '.$analisis['global']['dias_con_meta_semanal'].' día(s) de meta registrada');
        self::filaDeDatos($hoja, $fila++, 'Reflexiones semanales',
            count($analisis['reflexionesSemanales']),
            'Con '.$analisis['global']['dias_con_reflexion'].' día(s) con reflexión');

        $fila++;

        // La lectura escrita, sección por sección.
        self::tituloDeSeccion($hoja, $fila++, 'LECTURA DEL PERÍODO', 6);

        foreach ($textos as $clave => $frases) {
            $hoja->mergeCells('A'.$fila.':F'.$fila);
            $hoja->setCellValue('A'.$fila, mb_strtoupper($titulos[$clave] ?? $clave));
            self::pintarFranja($hoja, 'A'.$fila.':F'.$fila, self::NARANJA, 10, true);
            $fila++;

            foreach ($frases as $frase) {
                $hoja->mergeCells('A'.$fila.':F'.$fila);
                $hoja->setCellValue('A'.$fila, $frase);
                $hoja->getStyle('A'.$fila.':F'.$fila)->getAlignment()
                    ->setWrapText(true)
                    ->setVertical(Alignment::VERTICAL_TOP);
                $hoja->getRowDimension($fila)->setRowHeight(self::alturaDeTexto($frase, 160));
                $fila++;
            }

            $fila++;
        }

        // Tabla de actividades diarias.
        if ($analisis['actividadesDiarias'] !== []) {
            self::tituloDeSeccion($hoja, $fila++, 'ACTIVIDADES DIARIAS', 6);

            foreach (['ACTIVIDAD', 'CATEGORÍA', 'DÍAS DISPONIBLES', 'CUMPLIDAS', '%', 'MIN/DÍA'] as $indice => $titulo) {
                $celda = self::letra($indice + 1).$fila;
                $hoja->setCellValue($celda, $titulo);
                self::pintarFranja($hoja, $celda.':'.$celda, self::TURQUESA_OSCURO, 9, true);
            }

            $fila++;

            foreach ($analisis['actividadesDiarias'] as $actividad) {
                $hoja->setCellValue('A'.$fila, $actividad['actividad']);
                $hoja->setCellValue('B'.$fila, $actividad['categoria_label']);
                $hoja->setCellValue('C'.$fila, $actividad['total']);
                $hoja->setCellValue('D'.$fila, $actividad['hechas']);
                $hoja->setCellValue('E'.$fila, $actividad['pct'].'%');
                $hoja->setCellValue('F'.$fila, $actividad['minutos_promedio'] > 0
                    ? round($actividad['minutos_promedio'])
                    : '—');

                $hoja->getStyle('A'.$fila.':F'.$fila)->getBorders()->getBottom()
                    ->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()->setRGB(self::GRIS_BORDE);

                $fila++;
            }

            $fila++;
        }

        // Tabla de metas semanales.
        if ($analisis['metasSemanales'] !== []) {
            self::tituloDeSeccion($hoja, $fila++, 'METAS SEMANALES', 6);

            foreach (['SEMANA', 'META', 'POR QUÉ ES IMPORTANTE', 'DÍAS', 'SEGUIMIENTO', 'OBJETIVOS'] as $indice => $titulo) {
                $celda = self::letra($indice + 1).$fila;
                $hoja->setCellValue($celda, $titulo);
                self::pintarFranja($hoja, $celda.':'.$celda, self::NARANJA, 9, true);
            }

            $fila++;

            foreach ($analisis['metasSemanales'] as $semana) {
                $hoja->setCellValue('A'.$fila, $semana['etiqueta']);
                $hoja->setCellValue('B'.$fila, $semana['goal'] ?: 'Sin meta escrita');
                $hoja->setCellValue('C'.$fila, $semana['why_important'] ?: '—');
                $hoja->setCellValue('D'.$fila, $semana['dias']);
                $hoja->setCellValue('E'.$fila, $semana['dias_con_seguimiento']);
                $hoja->setCellValue('F'.$fila, $semana['objetivos_pct'].'%');

                $hoja->getStyle('A'.$fila.':F'.$fila)->getAlignment()->setWrapText(true);
                $hoja->getStyle('A'.$fila.':F'.$fila)->getBorders()->getBottom()
                    ->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()->setRGB(self::GRIS_BORDE);

                $fila++;
            }
        }

        $hoja->freezePane('A4');
        $hoja->getPageSetup()->setOrientation(PageSetup::ORIENTATION_PORTRAIT)->setFitToWidth(1);
    }

    /** Una franja de color con el título de una sección de la hoja Resumen. */
    private static function tituloDeSeccion(Worksheet $hoja, int $fila, string $titulo, int $columnas): void
    {
        $ultima = self::letra($columnas);
        $hoja->mergeCells('A'.$fila.':'.$ultima.$fila);
        $hoja->setCellValue('A'.$fila, $titulo);
        self::pintarFranja($hoja, 'A'.$fila.':'.$ultima.$fila, self::AZUL_OSCURO, 10, true);
        $hoja->getRowDimension($fila)->setRowHeight(18);
    }

    /** Una fila "indicador | valor | detalle" de los números del período. */
    private static function filaDeDatos(Worksheet $hoja, int $fila, string $indicador, mixed $valor, string $detalle): void
    {
        $hoja->setCellValue('A'.$fila, $indicador);
        $hoja->setCellValue('B'.$fila, $valor);
        $hoja->mergeCells('C'.$fila.':F'.$fila);
        $hoja->setCellValue('C'.$fila, $detalle);

        $hoja->getStyle('A'.$fila)->getFont()->setBold(true)->getColor()->setRGB(self::AZUL_OSCURO);
        $hoja->getStyle('B'.$fila)->getFont()->setBold(true)->getColor()->setRGB(self::TURQUESA_OSCURO);
        $hoja->getStyle('A'.$fila.':F'.$fila)->getAlignment()->setWrapText(true);
        $hoja->getStyle('A'.$fila.':F'.$fila)->getBorders()->getBottom()
            ->setBorderStyle(Border::BORDER_THIN)
            ->getColor()->setRGB(self::GRIS_BORDE);
        $hoja->getRowDimension($fila)->setRowHeight(18);
    }

    /** Texto de la segunda fila: programa, generación, cantidad y filtros. */
    private static function subtitulo(Collection $planes, array $filtros, CarbonInterface $generadoEn): string
    {
        $partes = [
            'Programa de Desarrollo Personal "Transforma-Conecta"',
            'Generado el '.Helper::dateTime($generadoEn),
            $planes->count().' diario(s)',
        ];

        $activos = [];

        if (Helper::strip($filtros['fecha'] ?? null) !== '') {
            $activos[] = 'fecha '.$filtros['fecha'];
        }

        if (Helper::strip($filtros['energia'] ?? null) !== '') {
            $activos[] = 'energía '.$filtros['energia'];
        }

        if (Helper::strip($filtros['palabra'] ?? null) !== '') {
            $activos[] = 'palabra «'.$filtros['palabra'].'»';
        }

        if ($activos !== []) {
            $partes[] = 'Filtros: '.implode(' · ', $activos);
        }

        return implode('   ·   ', $partes);
    }

    /** Describe el alcance del análisis igual que el resumen en PDF. */
    private static function alcance(array $filtros): string
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

        return $partes === [] ? 'Todos los diarios' : ucfirst(implode(', ', $partes));
    }

    /** Franja de color con texto blanco, para los títulos. */
    private static function pintarFranja(Worksheet $hoja, string $rango, string $color, int $tamano, bool $negrita): void
    {
        $hoja->getStyle($rango)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB($color);
        $hoja->getStyle($rango)->getFont()
            ->setBold($negrita)
            ->setSize($tamano)
            ->getColor()->setRGB(self::BLANCO);
        $hoja->getStyle($rango)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
    }

    /**
     * Alto aproximado de la fila, según el texto más largo de cada celda.
     *
     * Excel no calcula el alto solo al abrir el archivo, así que se estima a
     * partir del ancho de cada columna.
     */
    private static function alturaDeFila(array $datos): float
    {
        $filas = 1;

        // Las celdas vienen en el mismo orden que las columnas, así que el
        // ancho de cada una se busca por su posición.
        foreach (array_values($datos['cells'] ?? []) as $indice => $texto) {
            $ancho = self::COLUMNAS[$indice + 1]['ancho'] ?? 20;
            $lineas = 0;

            foreach (explode("\n", (string) $texto) as $linea) {
                $lineas += max(1, (int) ceil(mb_strlen($linea) / max(1, $ancho - 2)));
            }

            $filas = max($filas, $lineas);
        }

        return min(409, max(16, $filas * 13.5));
    }

    /** Alto estimado para un párrafo largo de la hoja Resumen. */
    private static function alturaDeTexto(string $texto, int $ancho): float
    {
        $lineas = max(1, (int) ceil(mb_strlen($texto) / max(1, $ancho)));

        return min(409, max(16, $lineas * 13.5));
    }

    /** Letra de la columna: 1 -> A, 27 -> AA. */
    public static function letra(int $numero): string
    {
        $letra = '';

        while ($numero > 0) {
            $resto = ($numero - 1) % 26;
            $letra = chr(65 + $resto).$letra;
            $numero = intdiv($numero - 1, 26);
        }

        return $letra;
    }
}
