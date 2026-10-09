<?php

namespace App\Excel;

use App\Helpers\Helper;
use App\Models\ActionBlockDuration;
use App\Models\ActionBlockOutcome;
use App\Models\EnergyLevel;
use App\Models\GoalType;
use App\Models\PreparationItem;
use App\Models\ReflectionQuestion;
use App\Models\ScheduleSlot;
use App\Planificacion\ArchivosDePlanificacion;
use Illuminate\Database\Eloquent\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Protection;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * La planilla del planificador diario en Excel.
 *
 * Es la plantilla estática que se descarga desde el módulo "Planificación
 * periódica": un libro con una hoja en blanco (DIA) que se duplica una vez por
 * cada día del período y se renombra con la fecha, y al final una hoja de
 * INSTRUCCIONES que la corrida ignora.
 *
 * El mapa de la hoja es **por etiquetas, no por número de fila**: cada dato se
 * busca por el texto que lo nombra en la columna B ("MI ENERGÍA HOY", "CIERRE
 * DEL DÍA", el nombre de un ítem del checklist…). Así el lector no se rompe si
 * alguien inserta una fila, y el mismo texto sirve para escribir y para leer.
 *
 * La hoja queda protegida (sin contraseña) para que las etiquetas no se muevan:
 * sólo se pueden escribir las celdas de valor.
 */
class PlantillaDelPlanificador
{
    /* ======================================================================
     |  Paleta institucional (la misma de public/css/styles.css)
     ====================================================================== */

    public const AZUL_OSCURO = '002060';

    public const AZUL = '0080D0';

    public const TURQUESA = '00AE9C';

    public const TURQUESA_OSCURO = '006C60';

    public const TURQUESA_TENUE = 'E6F7F5';

    public const NARANJA = 'F0600C';

    public const NARANJA_TENUE = 'FEF0E6';

    public const ROJO = 'D90B0B';

    public const GRIS_BORDE = 'BFC9D9';

    public const GRIS_CLARO = 'F4F7FB';

    public const GRIS_TEXTO = '6B7A90';

    public const BLANCO = 'FFFFFF';

    public const TEXTO = '10233F';

    /* ======================================================================
     |  Las hojas del libro
     ====================================================================== */

    /** La hoja en blanco que se duplica una vez por día. */
    public const HOJA_DIA = 'DIA';

    /** La hoja de instrucciones, siempre al final del libro. */
    public const HOJA_INSTRUCCIONES = 'INSTRUCCIONES';

    /**
     * Hojas que la corrida mira como si no existieran.
     *
     * INSTRUCCIONES se puede dejar o borrar antes de entregar el libro: el
     * resultado de la corrida es exactamente el mismo.
     */
    public const HOJAS_IGNORADAS = [self::HOJA_INSTRUCCIONES];

    /** Hojas de apoyo que la corrida nombra en el reporte, por si hubo un olvido. */
    public const HOJAS_AUXILIARES = [self::HOJA_DIA];

    /* ======================================================================
     |  Columnas y etiquetas
     ====================================================================== */

    public const COLUMNA_ETIQUETA = 'B';

    public const COLUMNA_VALOR = 'C';

    public const COLUMNA_DETALLE = 'D';

    public const ULTIMA_COLUMNA = 'E';

    /* Encabezado de la hoja. */
    public const TITULO = 'MI PLANIFICADOR DIARIO · PLANIFICACIÓN PERIÓDICA';

    public const SUBTITULO = 'Programa de Desarrollo Personal «Transforma-Conecta» · Una hoja por día';

    /* Fecha y energía. */
    public const ETIQUETA_FECHA = 'FECHA';

    public const ETIQUETA_ENERGIA = 'MI ENERGÍA HOY ✱';

    /* Secciones, en el orden en que se escriben en la hoja. */
    public const TITULO_OBJETIVOS = 'MIS 3 OBJETIVOS PRINCIPALES DE HOY ✱';

    public const TITULO_HORARIO = 'MI HORARIO DE HOY ✱ · al menos una franja';

    public const TITULO_PREPARACION = 'ANTES DE EMPEZAR · ¿Qué necesito tener listo?';

    public const TITULO_PROCRASTINACION = 'SI ESTOY PROCRASTINANDO ME PREGUNTO';

    public const TITULO_ACCION = 'BLOQUE DE ACCIÓN';

    public const TITULO_CIERRE = 'CIERRE DEL DÍA ✱ · obligatorio en todas las hojas';

    public const TITULO_NOTAS = 'NOTAS Y RECORDATORIOS';

    /* Etiquetas de las filas del bloque de acción y del cierre. */
    public const ETIQUETA_ACCION_DURACION = 'Voy a trabajar durante';

    public const ETIQUETA_ACCION_RESULTADO = 'Cuando termine este bloque';

    public const ETIQUETA_ACCION_TAREA = '¿En qué vas a trabajar?';

    public const ETIQUETA_ACCION_INICIO = 'Hora de inicio (hh:mm)';

    public const ETIQUETA_ACCION_FIN = 'Hora de fin (hh:mm)';

    public const ETIQUETA_CIERRE_LOGROS = 'Lo que logré hoy ✱';

    public const ETIQUETA_CIERRE_PENDIENTE = 'Lo que quedó pendiente ✱';

    public const ETIQUETA_CIERRE_CUANDO = '¿Cuándo lo haré? ✱';

    public const ETIQUETA_CIERRE_ORGULLO = 'Hoy estoy orgulloso/a de mí porque ✱';

    public const ETIQUETA_NOTAS = 'Notas (una por línea)';

    /**
     * Los títulos que separan las secciones de la hoja, en orden.
     *
     * El lector los usa para saber dónde empieza y dónde termina cada bloque.
     */
    public const SECCIONES = [
        self::ETIQUETA_FECHA,
        self::ETIQUETA_ENERGIA,
        self::TITULO_OBJETIVOS,
        self::TITULO_HORARIO,
        self::TITULO_PREPARACION,
        self::TITULO_PROCRASTINACION,
        self::TITULO_ACCION,
        self::TITULO_CIERRE,
        self::TITULO_NOTAS,
    ];

    /** Cuántas franjas de horario trae la hoja en blanco. */
    public const FRANJAS = 15;

    /* ======================================================================
     |  Generar el libro
     ====================================================================== */

    /** El libro en blanco: la hoja DIA y, al final, las instrucciones. */
    public static function generar(): Spreadsheet
    {
        $libro = new Spreadsheet;
        self::armarDia($libro->getActiveSheet(), self::HOJA_DIA);
        self::armarInstrucciones($libro->createSheet());
        self::estiloDelLibro($libro);

        return $libro;
    }

    /**
     * Un libro de ejemplo con días ya llenos, para probar la corrida sin tocar
     * la base de datos. Incluye a propósito una hoja incompleta (sin cierre)
     * para ver cómo se salta y se reporta.
     */
    public static function generarEjemplo(): Spreadsheet
    {
        $completos = ['2026-10-02', '2026-10-07', '2026-10-08', '2026-10-09'];
        $incompleto = '2026-10-10';

        $libro = new Spreadsheet;
        $primera = true;

        foreach ([...$completos, $incompleto] as $fecha) {
            $hoja = $primera ? $libro->getActiveSheet() : $libro->createSheet();
            $primera = false;

            self::armarDia($hoja, $fecha);
            self::llenarEjemplo($hoja, $fecha, $fecha !== $incompleto);
        }

        self::armarDia($libro->createSheet(), self::HOJA_DIA);
        self::armarInstrucciones($libro->createSheet());
        self::estiloDelLibro($libro);

        return $libro;
    }

    /** El contenido binario del libro, escrito en memoria (sin archivos temporales). */
    public static function contenido(Spreadsheet $libro): string
    {
        ob_start();
        (new Xlsx($libro))->save('php://output');
        $contenido = (string) ob_get_clean();

        $libro->disconnectWorksheets();

        return $contenido;
    }

    /** Guarda la planilla en blanco en su carpeta del disco privado. */
    public static function conservar(): string
    {
        return ArchivosDePlanificacion::guardarPlantilla(self::contenido(self::generar()));
    }

    /** Guarda el libro de ejemplo en su carpeta del disco privado. */
    public static function conservarEjemplo(string $nombre = 'libro-ejemplo.xlsx'): string
    {
        $ruta = ArchivosDePlanificacion::CARPETA_EJEMPLOS.'/'.$nombre;

        ArchivosDePlanificacion::disco()->put($ruta, self::contenido(self::generarEjemplo()));

        return $ruta;
    }

    /* ======================================================================
     |  La hoja de un día
     ====================================================================== */

    /** Escribe la hoja completa de un día; el nombre de la hoja es la fecha. */
    public static function armarDia(Worksheet $hoja, string $nombre): Worksheet
    {
        $hoja->setTitle($nombre);

        foreach (['A' => 2, 'B' => 38, 'C' => 54, 'D' => 22, 'E' => 2] as $columna => $ancho) {
            $hoja->getColumnDimension($columna)->setWidth($ancho);
        }

        $editables = [];
        $fila = 1;

        // 1. Encabezado de la hoja.
        self::franja($hoja, $fila++, self::TITULO, self::AZUL_OSCURO, 14, true, 26);
        self::franja($hoja, $fila++, self::SUBTITULO, self::TURQUESA, 10, false, 18);
        $fila = self::separador($hoja, $fila);

        // 2. Fecha y energía.
        $fila = self::filaDeValor(
            $hoja, $fila, self::ETIQUETA_FECHA,
            'Opcional: si la escribes, tiene que coincidir con el nombre de la hoja.',
            $editables, 20, 'DD/MM/YYYY'
        );
        $fila = self::filaDeValor(
            $hoja, $fila, self::ETIQUETA_ENERGIA,
            'Elige una de la lista.',
            $editables, 20
        );
        self::lista($hoja, self::COLUMNA_VALOR.($fila - 1), self::nombresDeEnergia());
        $fila = self::separador($hoja, $fila);

        // 3. Mis 3 objetivos principales de hoy.
        self::franja($hoja, $fila++, self::TITULO_OBJETIVOS, self::NARANJA, 11, true, 20);
        self::encabezados($hoja, $fila++, 'Objetivo', 'Descripción ✱', 'Cumplido (Sí/No)');

        foreach (self::tiposDeObjetivo() as $indice => $tipo) {
            self::etiqueta($hoja, $fila, ($indice + 1).' · '.$tipo->name, true, self::NARANJA_TENUE);
            self::escribible($hoja, $editables, self::COLUMNA_VALOR.$fila);
            self::escribible($hoja, $editables, self::COLUMNA_DETALLE.$fila);
            self::lista($hoja, self::COLUMNA_DETALLE.$fila, self::SINO);
            $hoja->getRowDimension($fila)->setRowHeight(22);
            $fila++;
        }

        $fila = self::separador($hoja, $fila);

        // 4. Mi horario de hoy.
        self::franja($hoja, $fila++, self::TITULO_HORARIO, self::AZUL, 11, true, 20);
        self::encabezados($hoja, $fila++, 'Hora ✱', '¿Qué vas a hacer? ✱', 'Hecho (Sí/No)');

        for ($franja = 0; $franja < self::FRANJAS; $franja++) {
            self::escribible($hoja, $editables, self::COLUMNA_ETIQUETA.$fila);
            self::escribible($hoja, $editables, self::COLUMNA_VALOR.$fila);
            self::escribible($hoja, $editables, self::COLUMNA_DETALLE.$fila);
            self::lista($hoja, self::COLUMNA_DETALLE.$fila, self::SINO);
            $hoja->getRowDimension($fila)->setRowHeight(18);
            $fila++;
        }

        $fila = self::separador($hoja, $fila);

        // 5. Antes de empezar.
        self::franja($hoja, $fila++, self::TITULO_PREPARACION, self::TURQUESA, 11, true, 20);
        self::encabezados($hoja, $fila++, 'Ítem', '¿Listo? (Sí/No)', '¿Qué incluye?');

        foreach (self::itemsDePreparacion() as $item) {
            self::etiqueta($hoja, $fila, $item->name, false, self::TURQUESA_TENUE);
            self::escribible($hoja, $editables, self::COLUMNA_VALOR.$fila);
            self::lista($hoja, self::COLUMNA_VALOR.$fila, self::SINO);
            self::escribible($hoja, $editables, self::COLUMNA_DETALLE.$fila);
            $hoja->getRowDimension($fila)->setRowHeight(20);
            $fila++;
        }

        $fila = self::separador($hoja, $fila);

        // 6. Si estoy procrastinando me pregunto.
        self::franja($hoja, $fila++, self::TITULO_PROCRASTINACION, self::NARANJA, 11, true, 20);
        self::encabezados($hoja, $fila++, 'Pregunta', '¿Me pasa? (Sí/No)', 'Mi respuesta');

        foreach (self::preguntasDeProcrastinacion() as $pregunta) {
            self::etiqueta($hoja, $fila, (string) $pregunta->question, false, self::NARANJA_TENUE);
            self::escribible($hoja, $editables, self::COLUMNA_VALOR.$fila);
            self::lista($hoja, self::COLUMNA_VALOR.$fila, self::SINO);
            self::escribible($hoja, $editables, self::COLUMNA_DETALLE.$fila);
            $hoja->getRowDimension($fila)->setRowHeight(20);
            $fila++;
        }

        $fila = self::separador($hoja, $fila);

        // 7. Bloque de acción.
        self::franja($hoja, $fila++, self::TITULO_ACCION, self::TURQUESA_OSCURO, 11, true, 20);

        $fila = self::filaDeValor($hoja, $fila, self::ETIQUETA_ACCION_DURACION, 'Elige una de la lista.', $editables);
        self::lista($hoja, self::COLUMNA_VALOR.($fila - 1), self::duracionesDeBloque());

        $fila = self::filaDeValor($hoja, $fila, self::ETIQUETA_ACCION_RESULTADO, 'Elige una de la lista.', $editables);
        self::lista($hoja, self::COLUMNA_VALOR.($fila - 1), self::resultadosDeBloque());

        $fila = self::filaDeValor($hoja, $fila, self::ETIQUETA_ACCION_TAREA, 'En qué vas a trabajar.', $editables);
        $fila = self::filaDeValor($hoja, $fila, self::ETIQUETA_ACCION_INICIO, 'Opcional.', $editables);
        $fila = self::filaDeValor($hoja, $fila, self::ETIQUETA_ACCION_FIN, 'Opcional.', $editables);
        $fila = self::separador($hoja, $fila);

        // 8. Cierre del día.
        self::franja($hoja, $fila++, self::TITULO_CIERRE, self::ROJO, 11, true, 20);

        foreach ([
            self::ETIQUETA_CIERRE_LOGROS,
            self::ETIQUETA_CIERRE_PENDIENTE,
            self::ETIQUETA_CIERRE_CUANDO,
            self::ETIQUETA_CIERRE_ORGULLO,
        ] as $etiqueta) {
            $fila = self::filaDeValor($hoja, $fila, $etiqueta, null, $editables, 30, null, null, false);
        }

        $fila = self::separador($hoja, $fila);

        // 9. Notas y recordatorios.
        self::franja($hoja, $fila++, self::TITULO_NOTAS, self::AZUL, 11, true, 20);
        self::etiqueta($hoja, $fila, self::ETIQUETA_NOTAS);
        self::escribible($hoja, $editables, self::COLUMNA_VALOR.$fila);
        $hoja->getRowDimension($fila)->setRowHeight(60);

        // 10. Protección: las etiquetas no se mueven, los valores sí se escriben.
        foreach ($editables as $rango) {
            $hoja->getStyle($rango)->getProtection()->setLocked(Protection::PROTECTION_UNPROTECTED);
        }

        $hoja->getProtection()->setSheet(true);

        // 11. Impresión: una hoja por día, a lo ancho de una página.
        $hoja->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_PORTRAIT)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $hoja->getPageMargins()->setTop(0.5)->setBottom(0.5)->setLeft(0.4)->setRight(0.4);

        return $hoja;
    }

    /* ======================================================================
     |  La hoja de instrucciones (siempre al final)
     ====================================================================== */

    /** Escribe la hoja de instrucciones. La corrida la ignora por completo. */
    public static function armarInstrucciones(Worksheet $hoja): Worksheet
    {
        $hoja->setTitle(self::HOJA_INSTRUCCIONES);

        foreach (['A' => 2, 'B' => 118, 'C' => 2, 'D' => 2, 'E' => 2] as $columna => $ancho) {
            $hoja->getColumnDimension($columna)->setWidth($ancho);
        }

        $fila = 1;
        self::franja($hoja, $fila++, 'INSTRUCCIONES · PLANILLA DEL PLANIFICADOR DIARIO', self::AZUL_OSCURO, 14, true, 26);
        self::franja($hoja, $fila++, 'Programa de Desarrollo Personal «Transforma-Conecta»', self::TURQUESA, 10, false, 18);
        $fila = self::separador($hoja, $fila);

        $lineas = [
            'CÓMO SE ARMA EL LIBRO',
            '1. Una hoja es un día. Duplica la hoja DIA (clic derecho en la pestaña → Mover o copiar → Crear una copia) y renombra la copia con la fecha en formato aaaa-mm-dd, por ejemplo 2026-10-04.',
            '2. El nombre de la hoja es la fecha del diario: es lo que lee el sistema. Si un nombre no es una fecha, esa hoja se ignora (por eso la hoja DIA se ignora sola).',
            '3. Puedes dejar los días en cualquier orden dentro del libro: la corrida los ordena por fecha.',
            '',
            'QUÉ ES OBLIGATORIO EN CADA HOJA',
            '4. Si falta un solo campo obligatorio, la hoja se salta completa y queda anotada en el reporte del módulo. No se carga a medias.',
            '5. Son obligatorios: la energía (una de la lista), los 3 objetivos con su descripción, al menos una franja del horario con su hora y su actividad, y los 4 campos del cierre del día (lo que logré, lo pendiente, cuándo lo haré y por qué estoy orgulloso/a).',
            '6. La fecha de la celda es opcional: si la escribes, tiene que ser la misma del nombre de la hoja.',
            '7. En el horario, si escribes una hora escribe también su actividad, y si escribes una actividad ponle su hora. Una franja a medias salta la hoja.',
            '8. Las secciones opcionales (antes de empezar, si estoy procrastinando, bloque de acción y notas) pueden quedar vacías. Lo que no se reconozca ahí se ignora y queda anotado en el reporte.',
            '9. En las celdas de Sí/No escribe Sí o No (hay lista desplegable).',
            '',
            'CÓMO SE ENTREGA',
            '10. No muevas, borres ni agregues filas: la hoja está protegida para evitarlo. Si necesitas tocar la estructura, quita la protección desde Revisar → Quitar protección de hoja, bajo tu responsabilidad.',
            '11. Cuando el libro esté listo, se monta en el módulo Planificación periódica. El sistema lo procesa solo en la corrida de las 00:00.',
            '12. Un día que ya está cargado en el sistema no se vuelve a cargar nunca, aunque el libro lo traiga de nuevo: la corrida solo llena lo que falta. Para corregir un día ya cargado se edita desde Consultar diario.',
            '13. Si un día no llegó a cargarse porque faltaba un dato, corrige la hoja, vuelve a montar el libro y espera la corrida siguiente: esa hoja se reintenta.',
            '14. Esta hoja de INSTRUCCIONES se ignora siempre. Puedes dejarla o borrarla antes de entregar el libro: el resultado es el mismo.',
            '',
            'HORARIO SUGERIDO (de 7:00 a 21:00, una franja por hora)',
            self::horarioSugerido(),
        ];

        foreach ($lineas as $linea) {
            if ($linea === '') {
                $fila = self::separador($hoja, $fila);

                continue;
            }

            $esTitulo = strtoupper($linea) === $linea;

            $hoja->mergeCells('B'.$fila.':E'.$fila);
            $hoja->setCellValue('B'.$fila, $linea);
            $hoja->getStyle('B'.$fila)->getFont()
                ->setBold($esTitulo)
                ->setSize($esTitulo ? 11 : 10)
                ->getColor()->setRGB($esTitulo ? self::AZUL_OSCURO : self::TEXTO);
            $hoja->getStyle('B'.$fila)->getAlignment()
                ->setVertical(Alignment::VERTICAL_CENTER)
                ->setWrapText(true);
            $hoja->getRowDimension($fila)->setRowHeight(self::altoDeTexto($linea, 118));

            $fila++;
        }

        return $hoja;
    }

    /* ======================================================================
     |  Búsqueda y escritura por etiqueta
     ====================================================================== */

    /**
     * La clave con la que se comparan las etiquetas: sin acentos, en minúsculas
     * y sin los adornos del final (✱, ·, *).
     */
    public static function clave(?string $texto): string
    {
        return trim(preg_replace('/[\s\*·✱]+$/u', '', Helper::normalize($texto)) ?? '');
    }

    /** La fila donde está esa etiqueta dentro de la columna B, o null. */
    public static function buscarFila(Worksheet $hoja, string $etiqueta, int $desde = 1): ?int
    {
        $buscada = self::clave($etiqueta);
        $ultima = max($hoja->getHighestRow(), $desde);

        for ($fila = $desde; $fila <= $ultima; $fila++) {
            if (self::clave((string) $hoja->getCell(self::COLUMNA_ETIQUETA.$fila)->getValue()) === $buscada) {
                return $fila;
            }
        }

        return null;
    }

    /** Escribe un valor en la fila de una etiqueta. Devuelve si la encontró. */
    public static function escribir(Worksheet $hoja, string $etiqueta, mixed $valor, string $columna = self::COLUMNA_VALOR, int $desde = 1): bool
    {
        $fila = self::buscarFila($hoja, $etiqueta, $desde);

        if ($fila === null) {
            return false;
        }

        $hoja->setCellValue($columna.$fila, $valor);

        return true;
    }

    /** Escribe un valor en una celda concreta. */
    public static function escribirEnFila(Worksheet $hoja, int $fila, string $columna, mixed $valor): void
    {
        $hoja->setCellValue($columna.$fila, $valor);
    }

    /* ======================================================================
     |  Catálogos (los mismos que alimentan el formulario)
     ====================================================================== */

    /** @return list<string> */
    public static function nombresDeEnergia(): array
    {
        return EnergyLevel::active()->pluck('name')->all();
    }

    /** @return Collection<int, GoalType> */
    public static function tiposDeObjetivo()
    {
        return GoalType::active()->get();
    }

    /** @return Collection<int, PreparationItem> */
    public static function itemsDePreparacion()
    {
        return PreparationItem::active()->get();
    }

    /** @return Collection<int, ReflectionQuestion> */
    public static function preguntasDeProcrastinacion()
    {
        return ReflectionQuestion::active()->category(ReflectionQuestion::CATEGORY_PROCRASTINATION)->get();
    }

    /** @return list<string> */
    public static function duracionesDeBloque(): array
    {
        return ActionBlockDuration::active()->pluck('label')->all();
    }

    /** @return list<string> */
    public static function resultadosDeBloque(): array
    {
        return ActionBlockOutcome::active()->pluck('name')->all();
    }

    /** Las horas del horario diario, para la hoja de instrucciones. */
    public static function horarioSugerido(): string
    {
        $horas = ScheduleSlot::active()->pluck('start_time')
            ->map(fn (mixed $hora) => Helper::time($hora, 'H:i'))
            ->filter()
            ->all();

        return $horas === [] ? '—' : implode('  ·  ', $horas);
    }

    /* ======================================================================
     |  Internos de dibujo
     ====================================================================== */

    /** Las respuestas de Sí/No que aceptan las listas. */
    public const SINO = ['Sí', 'No'];

    /** Franja de color con texto blanco, combinando B:E. */
    private static function franja(Worksheet $hoja, int $fila, string $texto, string $color, int $tamano, bool $negrita, float $alto = 20): void
    {
        $rango = self::COLUMNA_ETIQUETA.$fila.':'.self::ULTIMA_COLUMNA.$fila;

        $hoja->mergeCells($rango);
        $hoja->setCellValue(self::COLUMNA_ETIQUETA.$fila, $texto);
        $hoja->getStyle($rango)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($color);
        $hoja->getStyle($rango)->getFont()->setBold($negrita)->setSize($tamano)->getColor()->setRGB(self::BLANCO);
        $hoja->getStyle($rango)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $hoja->getRowDimension($fila)->setRowHeight($alto);
    }

    /** Los tres encabezados de un bloque de filas: B, C y D. */
    private static function encabezados(Worksheet $hoja, int $fila, string $etiqueta, string $valor, string $detalle): void
    {
        foreach ([self::COLUMNA_ETIQUETA => $etiqueta, self::COLUMNA_VALOR => $valor, self::COLUMNA_DETALLE => $detalle] as $columna => $texto) {
            $celda = $columna.$fila;

            $hoja->setCellValue($celda, $texto);
            $hoja->getStyle($celda)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::GRIS_CLARO);
            $hoja->getStyle($celda)->getFont()->setBold(true)->setSize(9)->getColor()->setRGB(self::AZUL_OSCURO);
            $hoja->getStyle($celda)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER)
                ->setWrapText(true);
            self::borde($hoja, $celda);
        }

        $hoja->getRowDimension($fila)->setRowHeight(18);
    }

    /** Una etiqueta en la columna B. */
    private static function etiqueta(Worksheet $hoja, int $fila, string $texto, bool $negrita = true, ?string $fondo = null, int $tamano = 10): void
    {
        $celda = self::COLUMNA_ETIQUETA.$fila;

        $hoja->setCellValue($celda, $texto);
        $hoja->getStyle($celda)->getFont()->setBold($negrita)->setSize($tamano)->getColor()->setRGB(self::TEXTO);
        $hoja->getStyle($celda)->getAlignment()
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        if ($fondo !== null) {
            $hoja->getStyle($celda)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($fondo);
        }

        self::borde($hoja, $celda);
    }

    /**
     * Una fila de etiqueta + valor + nota de ayuda.
     *
     * @param  list<string>  $editables  Se le van sumando los rangos escribibles.
     */
    private static function filaDeValor(
        Worksheet $hoja,
        int $fila,
        string $etiqueta,
        ?string $nota,
        array &$editables,
        float $alto = 18,
        ?string $formato = null,
        ?string $fondo = null,
        bool $conNota = true
    ): int {
        self::etiqueta($hoja, $fila, $etiqueta, true, $fondo);
        self::escribible($hoja, $editables, self::COLUMNA_VALOR.$fila, $formato);

        if ($conNota) {
            $celda = self::COLUMNA_DETALLE.$fila;

            $hoja->setCellValue($celda, $nota ?? '');
            $hoja->getStyle($celda)->getFont()->setSize(8)->setItalic(true)->getColor()->setRGB(self::GRIS_TEXTO);
            $hoja->getStyle($celda)->getAlignment()
                ->setVertical(Alignment::VERTICAL_CENTER)
                ->setWrapText(true);
        }

        $hoja->getRowDimension($fila)->setRowHeight($alto);

        return $fila + 1;
    }

    /** Deja una celda lista para escribir: desbloqueada y con su borde. */
    private static function escribible(Worksheet $hoja, array &$editables, string $rango, ?string $formato = null): void
    {
        $editables[] = $rango;

        $hoja->getStyle($rango)->getAlignment()
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        self::borde($hoja, $rango);

        if ($formato !== null) {
            $hoja->getStyle($rango)->getNumberFormat()->setFormatCode($formato);
        }
    }

    /** Borde fino institucional. */
    private static function borde(Worksheet $hoja, string $rango): void
    {
        $hoja->getStyle($rango)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)
            ->getColor()->setRGB(self::GRIS_BORDE);
    }

    /** Una fila flaca que separa dos bloques. */
    private static function separador(Worksheet $hoja, int $fila): int
    {
        $hoja->getRowDimension($fila)->setRowHeight(7);

        return $fila + 1;
    }

    /** Lista desplegable sobre un rango (los valores van separados por comas). */
    private static function lista(Worksheet $hoja, string $rango, array $valores): void
    {
        if ($valores === []) {
            return;
        }

        $validacion = new DataValidation;
        $validacion->setType(DataValidation::TYPE_LIST);
        $validacion->setErrorStyle(DataValidation::STYLE_STOP);
        $validacion->setAllowBlank(true);
        $validacion->setShowErrorMessage(true);
        $validacion->setErrorTitle('Valor no válido');
        $validacion->setError('Elige uno de los valores de la lista.');
        $validacion->setShowInputMessage(true);
        $validacion->setPromptTitle('Elige de la lista');
        $validacion->setPrompt('Usa la flecha de la celda.');
        $validacion->setFormula1('"'.implode(',', $valores).'"');

        $hoja->setDataValidation($rango, $validacion);
    }

    /** El estilo de todo el libro: la fuente y el alto de la primera fila. */
    private static function estiloDelLibro(Spreadsheet $libro): void
    {
        $libro->getDefaultStyle()->getFont()
            ->setName('Calibri')
            ->setSize(10)
            ->getColor()->setRGB(self::TEXTO);

        $libro->setActiveSheetIndex(0);
    }

    /** Alto aproximado de una línea de texto envuelto. */
    private static function altoDeTexto(string $texto, int $ancho): float
    {
        $lineas = max(1, (int) ceil(mb_strlen($texto) / max(1, $ancho - 2)));

        return min(120, max(16, $lineas * 14));
    }

    /* ======================================================================
     |  El libro de ejemplo
     ====================================================================== */

    /** Llena una hoja con datos de ejemplo, para probar la corrida. */
    private static function llenarEjemplo(Worksheet $hoja, string $fecha, bool $conCierre): void
    {
        self::escribir($hoja, self::ETIQUETA_FECHA, $fecha);
        self::escribir($hoja, self::ETIQUETA_ENERGIA, 'Media');

        $objetivos = [
            'Terminar el informe mensual',
            'Llamar a mi familia',
            'Caminar 30 minutos',
        ];

        foreach ($objetivos as $indice => $descripcion) {
            $fila = (self::buscarFila($hoja, self::TITULO_OBJETIVOS) ?? 0) + 2 + $indice;

            self::escribirEnFila($hoja, $fila, self::COLUMNA_VALOR, $descripcion);
            self::escribirEnFila($hoja, $fila, self::COLUMNA_DETALLE, $indice === 0 ? 'Sí' : 'No');
        }

        $franjas = [
            ['07:00', 'Desayunar y revisar la agenda', 'Sí'],
            ['08:00', 'Trabajar en el informe', 'Sí'],
            ['10:00', 'Clase de matemáticas', 'No'],
        ];

        $filaHorario = (self::buscarFila($hoja, self::TITULO_HORARIO) ?? 0) + 2;

        foreach ($franjas as $indice => [$hora, $actividad, $hecho]) {
            self::escribirEnFila($hoja, $filaHorario + $indice, self::COLUMNA_ETIQUETA, $hora);
            self::escribirEnFila($hoja, $filaHorario + $indice, self::COLUMNA_VALOR, $actividad);
            self::escribirEnFila($hoja, $filaHorario + $indice, self::COLUMNA_DETALLE, $hecho);
        }

        self::escribir($hoja, 'Materiales', 'Sí', self::COLUMNA_VALOR);
        self::escribir($hoja, 'Materiales', 'PC, cuaderno y calculadora', self::COLUMNA_DETALLE);
        self::escribir($hoja, 'Espacio organizado', 'Sí', self::COLUMNA_VALOR);
        self::escribir($hoja, '¿Qué estoy evitando?', 'Sí', self::COLUMNA_VALOR);
        self::escribir($hoja, '¿Qué estoy evitando?', 'El informe largo', self::COLUMNA_DETALLE);

        self::escribir($hoja, self::ETIQUETA_ACCION_DURACION, '20 minutos');
        self::escribir($hoja, self::ETIQUETA_ACCION_RESULTADO, 'Avancé');
        self::escribir($hoja, self::ETIQUETA_ACCION_TAREA, 'Redactar la introducción');
        self::escribir($hoja, self::ETIQUETA_ACCION_INICIO, '08:00');
        self::escribir($hoja, self::ETIQUETA_ACCION_FIN, '08:20');

        if ($conCierre) {
            self::escribir($hoja, self::ETIQUETA_CIERRE_LOGROS, 'Terminé el informe y caminé.');
            self::escribir($hoja, self::ETIQUETA_CIERRE_PENDIENTE, 'Falta revisar las cifras del anexo.');
            self::escribir($hoja, self::ETIQUETA_CIERRE_CUANDO, 'Mañana temprano');
            self::escribir($hoja, self::ETIQUETA_CIERRE_ORGULLO, 'Cumplí el horario que me propuse.');
        }

        self::escribir($hoja, self::ETIQUETA_NOTAS, "Revisar el correo antes de dormir\nPreparar la ropa para mañana");
    }
}
