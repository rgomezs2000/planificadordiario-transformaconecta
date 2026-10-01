<?php

namespace App\Excel;

use App\Helpers\Helper;
use App\Models\DailyPlan;
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
 * Un diario por fila, con la fecha, la energía, el "antes de empezar", las
 * preguntas de procrastinación, el bloque de acción, el cierre del día y las
 * notas. Los colores son los institucionales de Transforma-Conecta y siguen el
 * código de color de la hoja impresa.
 *
 * Se escribe en memoria, así que no deja archivos temporales en el servidor.
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
     * El color de cada encabezado es el de su sección en la hoja impresa:
     * azul oscuro para los datos del día, turquesa para "antes de empezar",
     * naranja para la procrastinación, turquesa oscuro para el bloque de
     * acción, rojo para el cierre y azul para las notas.
     */
    public const COLUMNAS = [
        ['titulo' => 'Fecha', 'color' => self::AZUL_OSCURO, 'ancho' => 12],
        ['titulo' => 'Día', 'color' => self::AZUL_OSCURO, 'ancho' => 11],
        ['titulo' => 'Energía', 'color' => self::AZUL_OSCURO, 'ancho' => 10],
        ['titulo' => 'Antes de empezar', 'color' => self::TURQUESA, 'ancho' => 40],
        ['titulo' => '¿Estoy procrastinando?', 'color' => self::NARANJA, 'ancho' => 40],
        ['titulo' => 'Bloque de acción', 'color' => self::TURQUESA_OSCURO, 'ancho' => 28],
        ['titulo' => 'Cierre del día', 'color' => self::ROJO, 'ancho' => 42],
        ['titulo' => 'Notas / recordatorios', 'color' => self::AZUL, 'ancho' => 34],
    ];

    /**
     * Genera el archivo y devuelve su contenido binario.
     *
     * @param  Collection<int, DailyPlan>  $planes
     * @param  array{fecha?: mixed, energia?: mixed, palabra?: mixed}  $filtros
     */
    public static function generar(Collection $planes, array $filtros = [], ?CarbonInterface $generadoEn = null): string
    {
        $hoja = self::armarHoja($planes, $filtros, $generadoEn ?? now());
        $libro = $hoja->getParent();
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

    /** Arma la hoja completa. */
    private static function armarHoja(Collection $planes, array $filtros, CarbonInterface $generadoEn): Worksheet
    {
        $libro = new Spreadsheet;
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

            $hoja->setCellValue('B'.$fila, (string) $datos['day']);
            $hoja->setCellValue('C'.$fila, (string) $datos['energy']);
            $hoja->setCellValue('D'.$fila, $datos['preparation']);
            $hoja->setCellValue('E'.$fila, $datos['procrastination']);
            $hoja->setCellValue('F'.$fila, $datos['action_block']);
            $hoja->setCellValue('G'.$fila, $datos['closure']);
            $hoja->setCellValue('H'.$fila, $datos['notes']);

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
        $textos = [
            $datos['preparation'],
            $datos['procrastination'],
            $datos['action_block'],
            $datos['closure'],
            $datos['notes'],
        ];

        $filas = 1;

        foreach ($textos as $indice => $texto) {
            $ancho = self::COLUMNAS[$indice + 3]['ancho'];
            $lineas = 0;

            foreach (explode("\n", (string) $texto) as $linea) {
                $lineas += max(1, (int) ceil(mb_strlen($linea) / max(1, $ancho - 2)));
            }

            $filas = max($filas, $lineas);
        }

        return min(409, max(16, $filas * 13.5));
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
