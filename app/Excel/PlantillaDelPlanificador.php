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
     * resultado de la corrida es exactamente el mismo. LISTAS es la hoja oculta
     * donde viven las fechas del período que ofrece el desplegable.
     */
    public const HOJAS_IGNORADAS = [self::HOJA_INSTRUCCIONES, self::HOJA_LISTAS];

    /** La hoja oculta con las fechas del período. */
    public const HOJA_LISTAS = 'LISTAS';

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

    /** Cuántas franjas de horario trae la hoja en blanco (las del catálogo). */
    public const FRANJAS = 15;

    /** Franjas vacías de más, ya con sus listas, para no tener que insertar filas. */
    public const FRANJAS_DE_MAS = 5;

    /** Tope de días que puede abarcar un libro de período. */
    public const DIAS_MAXIMOS = 366;

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
     |  El libro de un período: una hoja por día
     ====================================================================== */

    /**
     * El libro de un período: una hoja por día, ya nombrada con la fecha, con la
     * fecha puesta y con la lista de días del período para cambiarla.
     *
     * Es la forma cómoda de repartir la planilla: nadie tiene que duplicar ni
     * renombrar hojas, solo llenarlas. Excel no tiene calendario desplegable en
     * un .xlsx normal, así que la fecha se elige de una lista con los días del
     * período (hoja oculta LISTAS), que es lo más parecido que se puede hacer
     * sin macros.
     */
    public static function generarPeriodo(string $desde, string $hasta): Spreadsheet
    {
        $fechas = self::diasDelPeriodo($desde, $hasta);

        $libro = new Spreadsheet;
        $primera = true;

        foreach ($fechas as $fecha) {
            $hoja = $primera ? $libro->getActiveSheet() : $libro->createSheet();
            $primera = false;

            self::armarDia($hoja, $fecha, $fechas);
        }

        if ($fechas !== []) {
            self::armarListasDeFechas($libro->createSheet(), $fechas);
        }

        self::armarInstrucciones($libro->createSheet());
        self::estiloDelLibro($libro);

        return $libro;
    }

    /**
     * Los días del período, como fechas "aaaa-mm-dd". Devuelve una lista vacía
     * si las fechas no se entienden, si están al revés o si el período pasa del
     * tope (un libro con cientos de hojas no es práctico).
     *
     * @return list<string>
     */
    public static function diasDelPeriodo(string $desde, string $hasta): array
    {
        $inicio = Helper::toCarbon($desde);
        $fin = Helper::toCarbon($hasta);

        if (! $inicio || ! $fin || $fin->lessThan($inicio)) {
            return [];
        }

        if ($inicio->diffInDays($fin) + 1 > self::DIAS_MAXIMOS) {
            return [];
        }

        $dias = [];

        for ($dia = $inicio->copy(); $dia->lessThanOrEqualTo($fin); $dia->addDay()) {
            $dias[] = $dia->format('Y-m-d');
        }

        return $dias;
    }

    /** Guarda el libro del período en la carpeta de las plantillas. */
    public static function conservarPeriodo(string $desde, string $hasta): string
    {
        $inicio = Helper::toCarbon($desde);
        $fin = Helper::toCarbon($hasta);

        if (! $inicio || ! $fin || self::diasDelPeriodo($desde, $hasta) === []) {
            return '';
        }

        $ruta = ArchivosDePlanificacion::CARPETA_FORMATO.'/'.self::nombreDePeriodo($inicio->format('Y-m-d'), $fin->format('Y-m-d'));

        ArchivosDePlanificacion::disco()->put($ruta, self::contenido(self::generarPeriodo($desde, $hasta)));

        return $ruta;
    }

    /** Cómo se llama el archivo de un período. */
    public static function nombreDePeriodo(string $desde, string $hasta): string
    {
        return 'planilla-'.$desde.'_'.$hasta.'.xlsx';
    }

    /** La hoja oculta con los días del período, para la lista desplegable. */
    private static function armarListasDeFechas(Worksheet $hoja, array $fechas): Worksheet
    {
        $hoja->setTitle(self::HOJA_LISTAS);
        $hoja->getColumnDimension('A')->setWidth(14);

        foreach ($fechas as $indice => $fecha) {
            $hoja->setCellValue('A'.($indice + 1), self::fechaEnPalabras($fecha));
        }

        $hoja->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

        return $hoja;
    }

    /* ======================================================================
     |  La hoja de un día
     ====================================================================== */

    /** Escribe la hoja completa de un día; el nombre de la hoja es la fecha. */
    public static function armarDia(Worksheet $hoja, string $nombre, array $fechas = []): Worksheet
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
        $filaFecha = $fila;

        $fila = self::filaDeValor(
            $hoja, $fila, self::ETIQUETA_FECHA,
            $fechas === []
                ? 'Opcional: si la escribes, tiene que coincidir con el nombre de la hoja.'
                : 'Elige el día de la lista. Manda el nombre de la hoja.',
            $editables, 20, 'DD/MM/YYYY'
        );

        if ($fechas === []) {
            // Plantilla en blanco: la celda acepta cualquier fecha válida.
            self::validarFecha($hoja, self::COLUMNA_VALOR.$filaFecha);
        } else {
            // Libro de un período: la fecha viene puesta y se puede cambiar por
            // otro día del mismo período desde la lista.
            $hoja->setCellValue(self::COLUMNA_VALOR.$filaFecha, self::fechaEnPalabras($nombre));
            self::listaDeFechas($hoja, self::COLUMNA_VALOR.$filaFecha, count($fechas));
        }

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
        //
        // Cada franja sale con sus dos listas puestas: la hora (las del horario
        // del sistema) y el Sí/No. Se dejan franjas de más para que no haga
        // falta insertar filas; si se inserta una en medio del bloque, Excel le
        // arrastra las mismas listas.
        self::franja($hoja, $fila++, self::TITULO_HORARIO, self::AZUL, 11, true, 20);
        self::encabezados($hoja, $fila++, 'Hora ✱', '¿Qué vas a hacer? ✱', 'Hecho (Sí/No)');

        $horas = self::horasDelHorario();

        for ($franja = 0; $franja < self::FRANJAS + self::FRANJAS_DE_MAS; $franja++) {
            self::escribible($hoja, $editables, self::COLUMNA_ETIQUETA.$fila);
            self::escribible($hoja, $editables, self::COLUMNA_VALOR.$fila);
            self::escribible($hoja, $editables, self::COLUMNA_DETALLE.$fila);
            self::lista(
                $hoja, self::COLUMNA_ETIQUETA.$fila, $horas, DataValidation::STYLE_WARNING,
                'Elige la hora de la lista, o escribe otra (por ejemplo 07:30).'
            );
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
            'LISTAS Y CELDAS YA PREPARADAS',
            '4. La energía, los Sí/No, la duración y el resultado del bloque de acción se eligen de una lista desplegable: haz clic en la celda y usa la flecha de la derecha.',
            '5. La Hora de cada franja también es una lista, con las horas del horario del sistema (07:00 a 21:00). Si necesitas otra hora (por ejemplo 07:30), escríbela: el sistema te avisa, pero la acepta.',
            '6. El horario trae 20 franjas listas (las 15 del horario y 5 de más), cada una con sus dos listas puestas. Si insertas una fila en medio del horario, Excel le arrastra las mismas listas.',
            '7. La celda de la FECHA está preparada para escribir una fecha (dd/mm/aaaa). Excel no tiene calendario desplegable en un archivo normal: como el nombre de la hoja es el que manda, esta celda se puede dejar vacía.',
            '',
            'QUÉ ES OBLIGATORIO EN CADA HOJA',
            '8. Si falta un solo campo obligatorio, la hoja se salta completa y queda anotada en el reporte del módulo. No se carga a medias.',
            '9. Son obligatorios: la energía (una de la lista), los 3 objetivos con su descripción, al menos una franja del horario con su hora y su actividad, y los 4 campos del cierre del día (lo que logré, lo pendiente, cuándo lo haré y por qué estoy orgulloso/a).',
            '10. En el horario, si escribes una hora escribe también su actividad, y si escribes una actividad ponle su hora. Una franja a medias salta la hoja.',
            '11. Las secciones opcionales (antes de empezar, si estoy procrastinando, bloque de acción y notas) pueden quedar vacías. Lo que no se reconozca ahí se ignora y queda anotado en el reporte.',
            '',
            'CÓMO SE ENTREGA',
            '12. No muevas, borres ni agregues filas: la hoja está protegida para evitarlo. Si necesitas tocar la estructura, quita la protección desde Revisar → Quitar protección de hoja, bajo tu responsabilidad.',
            '13. Cuando el libro esté listo, se monta en el módulo Planificación periódica. El sistema lo procesa solo en la corrida de las 00:00.',
            '14. Un día que ya está cargado en el sistema no se vuelve a cargar nunca, aunque el libro lo traiga de nuevo: la corrida solo llena lo que falta. Para corregir un día ya cargado se edita desde Consultar diario.',
            '15. Si un día no llegó a cargarse porque faltaba un dato, corrige la hoja, vuelve a montar el libro y espera la corrida siguiente: esa hoja se reintenta.',
            '16. Esta hoja de INSTRUCCIONES se ignora siempre. Puedes dejarla o borrarla antes de entregar el libro: el resultado es el mismo.',
            '',
            'HORARIO DEL SISTEMA (las horas de la lista desplegable de cada franja)',
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

    /**
     * Las horas del horario diario, tal como salen en la lista desplegable de
     * cada franja de la hoja (07:00, 08:00… 21:00).
     *
     * @return list<string>
     */
    public static function horasDelHorario(): array
    {
        return ScheduleSlot::active()->pluck('start_time')
            ->map(fn (mixed $hora) => Helper::time($hora, 'H:i'))
            ->filter()
            ->values()
            ->all();
    }

    /** Esas mismas horas, en una línea, para la hoja de instrucciones. */
    public static function horarioSugerido(): string
    {
        $horas = self::horasDelHorario();

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

    /**
     * Lista desplegable sobre un rango (los valores van separados por comas).
     *
     * Con STYLE_STOP la celda solo acepta lo que está en la lista; con
     * STYLE_WARNING deja escribir otro valor, avisando: es lo que se usa en las
     * horas, donde alguien puede necesitar una franja que no está en el catálogo
     * (por ejemplo 07:30).
     */
    private static function lista(
        Worksheet $hoja,
        string $rango,
        array $valores,
        string $estilo = DataValidation::STYLE_STOP,
        ?string $ayuda = null
    ): void {
        if ($valores === []) {
            return;
        }

        $estricta = $estilo === DataValidation::STYLE_STOP;

        $validacion = new DataValidation;
        $validacion->setType(DataValidation::TYPE_LIST);
        // Ojo con este atributo: en el archivo, «showDropDown» está invertido
        // (1 = ocultar la flecha). PhpSpreadsheet escribe el valor negado, así
        // que hay que pedirlo en true para que Excel muestre el desplegable.
        $validacion->setShowDropDown(true);
        $validacion->setErrorStyle($estilo);
        $validacion->setAllowBlank(true);
        $validacion->setShowErrorMessage(true);
        $validacion->setErrorTitle('Valor fuera de la lista');
        $validacion->setError($estricta
            ? 'Elige uno de los valores de la lista.'
            : 'Ese valor no está en la lista. Se acepta, pero revísalo antes de seguir.');
        $validacion->setShowInputMessage(true);
        $validacion->setPromptTitle('Elige de la lista');
        $validacion->setPrompt($ayuda ?? 'Usa la flecha de la celda.');
        $validacion->setFormula1('"'.implode(',', $valores).'"');

        $hoja->setDataValidation($rango, $validacion);
    }

    /**
     * La celda de la fecha: se valida como fecha y se muestra dd/mm/aaaa.
     *
     * Excel no tiene un calendario desplegable en un archivo .xlsx normal (solo
     * con macros, en un .xlsm): lo que sí se puede es impedir que entre cualquier
     * cosa. Se deja con aviso, no con bloqueo, porque la fecha también puede
     * llegar como texto (aaaa-mm-dd) y esa forma es válida para la corrida.
     */
    private static function validarFecha(Worksheet $hoja, string $celda): void
    {
        $validacion = new DataValidation;
        $validacion->setType(DataValidation::TYPE_DATE);
        $validacion->setOperator(DataValidation::OPERATOR_BETWEEN);
        $validacion->setShowDropDown(true);
        $validacion->setAllowBlank(true);
        $validacion->setShowErrorMessage(true);
        $validacion->setErrorStyle(DataValidation::STYLE_WARNING);
        $validacion->setErrorTitle('Fecha fuera de rango');
        $validacion->setError('Escribe una fecha entre 2020 y 2100, por ejemplo 04/10/2026.');
        $validacion->setShowInputMessage(true);
        $validacion->setPromptTitle('Fecha del día');
        $validacion->setPrompt('Escribe la fecha (dd/mm/aaaa o aaaa-mm-dd). El nombre de la hoja es el que manda.');
        $validacion->setFormula1('DATE(2020,1,1)');
        $validacion->setFormula2('DATE(2100,12,31)');

        $hoja->setDataValidation($celda, $validacion);
    }

    /**
     * La fecha como lista desplegable con los días del período.
     *
     * Los días viven en la hoja oculta LISTAS y la validación apunta a ese rango
     * (una lista escrita dentro de la fórmula no aguanta más de 255 caracteres:
     * un mes ya no entra).
     */
    private static function listaDeFechas(Worksheet $hoja, string $celda, int $cuantas): void
    {
        if ($cuantas < 1) {
            return;
        }

        $validacion = new DataValidation;
        $validacion->setType(DataValidation::TYPE_LIST);
        $validacion->setShowDropDown(true);
        $validacion->setAllowBlank(true);
        $validacion->setShowErrorMessage(true);
        $validacion->setErrorStyle(DataValidation::STYLE_WARNING);
        $validacion->setErrorTitle('Fecha fuera del período');
        $validacion->setError('Elige uno de los días del período, o escribe la fecha a mano.');
        $validacion->setShowInputMessage(true);
        $validacion->setPromptTitle('Día del período');
        $validacion->setPrompt('Elige el día de la lista. El nombre de la hoja es el que manda.');
        $validacion->setFormula1(self::HOJA_LISTAS.'!$A$1:$A$'.$cuantas);

        $hoja->setDataValidation($celda, $validacion);
    }

    /** Una fecha "aaaa-mm-dd" como se muestra en la hoja: "04/10/2026". */
    public static function fechaEnPalabras(string $fecha): string
    {
        return Helper::date($fecha) ?? $fecha;
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
