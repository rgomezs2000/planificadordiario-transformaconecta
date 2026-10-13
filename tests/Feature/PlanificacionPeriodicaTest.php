<?php

namespace Tests\Feature;

use App\Console\Commands\ImportarPlanificacion;
use App\Excel\PlantillaDelPlanificador;
use App\Helpers\Helper;
use App\Models\DailyPlan;
use App\Models\EnergyLevel;
use App\Models\PlanificacionHoja;
use App\Models\PlanificacionImportacion;
use App\Models\PreparationItem;
use App\Models\ReflectionAnswer;
use App\Models\ScheduleEntry;
use App\Planificacion\ArchivosDePlanificacion;
use App\Planificacion\ImportadorDePlanificacion;
use App\Planificacion\LanzadorDeCorridas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as FechaExcel;
use PhpOffice\PhpSpreadsheet\Style\Protection;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\Process\PhpExecutableFinder;
use Tests\TestCase;

/**
 * Módulo "Planificación periódica": la planilla, el lector del libro y el
 * comando que sólo crea los días que faltan.
 */
class PlanificacionPeriodicaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Catálogos reales: la planilla y el lector se arman con ellos.
        $this->seed();

        // El disco privado, aislado: nada de esto toca los archivos reales.
        Storage::fake(ArchivosDePlanificacion::DISCO);
    }

    /* ======================================================================
     |  La planilla
     ====================================================================== */

    public function test_la_planilla_trae_la_hoja_del_dia_y_las_instrucciones_al_final(): void
    {
        PlantillaDelPlanificador::conservar();

        $this->assertTrue(ArchivosDePlanificacion::existePlantilla());

        $libro = IOFactory::load(ArchivosDePlanificacion::rutaAbsoluta(ArchivosDePlanificacion::rutaPlantilla()));

        $this->assertSame(
            [
                PlantillaDelPlanificador::HOJA_DIA,
                PlantillaDelPlanificador::HOJA_LISTAS,
                PlantillaDelPlanificador::HOJA_INSTRUCCIONES,
            ],
            $libro->getSheetNames()
        );

        // Las listas van ocultas y las instrucciones, siempre al final.
        $this->assertSame(
            Worksheet::SHEETSTATE_HIDDEN,
            $libro->getSheetByName(PlantillaDelPlanificador::HOJA_LISTAS)->getSheetState()
        );

        $dia = $libro->getSheetByName(PlantillaDelPlanificador::HOJA_DIA);

        // Las etiquetas que busca la corrida.
        foreach ([
            PlantillaDelPlanificador::ETIQUETA_FECHA,
            PlantillaDelPlanificador::ETIQUETA_ENERGIA,
            PlantillaDelPlanificador::TITULO_OBJETIVOS,
            PlantillaDelPlanificador::TITULO_HORARIO,
            PlantillaDelPlanificador::TITULO_PREPARACION,
            PlantillaDelPlanificador::TITULO_PROCRASTINACION,
            PlantillaDelPlanificador::TITULO_ACCION,
            PlantillaDelPlanificador::TITULO_CIERRE,
            PlantillaDelPlanificador::ETIQUETA_CIERRE_LOGROS,
        ] as $etiqueta) {
            $this->assertNotNull(
                PlantillaDelPlanificador::buscarFila($dia, $etiqueta),
                'No se encontró la etiqueta «'.$etiqueta.'» en la planilla.'
            );
        }

        // Los catálogos están escritos en la hoja, tal como están en la base.
        foreach (PreparationItem::active()->pluck('name') as $item) {
            $this->assertNotNull(PlantillaDelPlanificador::buscarFila($dia, $item), 'Falta el ítem «'.$item.'».');
        }

        // La hoja va protegida y las celdas de valor quedan escribibles.
        $this->assertTrue($dia->getProtection()->isProtectionEnabled());
        $this->assertSame(
            Protection::PROTECTION_UNPROTECTED,
            $dia->getCell('C9')->getStyle()->getProtection()->getLocked()
        );
        $this->assertNotSame(
            Protection::PROTECTION_UNPROTECTED,
            $dia->getCell('B9')->getStyle()->getProtection()->getLocked()
        );
    }

    public function test_la_planilla_trae_las_listas_desplegables_puestas(): void
    {
        PlantillaDelPlanificador::conservar();

        $libro = IOFactory::load(ArchivosDePlanificacion::rutaAbsoluta(ArchivosDePlanificacion::rutaPlantilla()));
        $dia = $libro->getSheetByName(PlantillaDelPlanificador::HOJA_DIA);

        // Energía y Sí/No: listas cerradas.
        $energia = $dia->getDataValidation('C5');
        $this->assertSame(DataValidation::TYPE_LIST, $energia->getType());
        $this->assertSame('"Baja,Media,Alta"', $energia->getFormula1());
        $this->assertSame(DataValidation::STYLE_STOP, $energia->getErrorStyle());

        // La fecha: validada como fecha (Excel no tiene calendario en .xlsx).
        $fecha = $dia->getDataValidation('C4');
        $this->assertSame(DataValidation::TYPE_DATE, $fecha->getType());
        $this->assertSame(DataValidation::OPERATOR_BETWEEN, $fecha->getOperator());

        // Cada franja del horario sale con la hora y el Sí/No ya puestos, y hay
        // franjas de más para no tener que insertar filas.
        $encabezado = PlantillaDelPlanificador::buscarFila($dia, PlantillaDelPlanificador::TITULO_HORARIO) + 1;
        $primera = $encabezado + 1;
        $ultima = $encabezado + PlantillaDelPlanificador::FRANJAS + PlantillaDelPlanificador::FRANJAS_DE_MAS;

        $horas = $dia->getDataValidation('B'.$primera);
        $this->assertSame(DataValidation::TYPE_LIST, $horas->getType());
        $this->assertSame(DataValidation::STYLE_WARNING, $horas->getErrorStyle());
        // Las horas salen de la lista oculta: todo el día, minuto a minuto.
        $this->assertSame('LISTAS!$B$1:$B$'.PlantillaDelPlanificador::HORAS_DEL_DIA, $horas->getFormula1());
        $this->assertSame('"Sí,No"', $dia->getDataValidation('D'.$primera)->getFormula1());

        $this->assertTrue($dia->dataValidationExists('B'.$ultima));
        $this->assertTrue($dia->dataValidationExists('D'.$ultima));
        $this->assertFalse($dia->dataValidationExists('B'.($ultima + 1)));

        // Ojo con este atributo: en el archivo está invertido (1 = ocultar la
        // flecha). Si se deja como viene por defecto, Excel no muestra el
        // desplegable y la celda parece un campo común.
        $this->assertTrue($energia->getShowDropDown());
        $this->assertTrue($horas->getShowDropDown());
    }

    /**
     * El desplegable de la Hora cubre el día completo, minuto a minuto: de 00:00
     * a 23:59 (1440 opciones), y el lector las entiende.
     */
    public function test_las_horas_de_la_lista_cubren_todo_el_dia(): void
    {
        $horas = PlantillaDelPlanificador::horasDelDia();

        $this->assertCount(PlantillaDelPlanificador::HORAS_DEL_DIA, $horas);
        $this->assertCount(1440, array_unique($horas));
        $this->assertSame(PlantillaDelPlanificador::PRIMERA_HORA, $horas[0]);
        $this->assertSame('00:01', $horas[1]);
        $this->assertSame('12:00', $horas[12 * 60]);
        $this->assertSame(PlantillaDelPlanificador::ULTIMA_HORA, $horas[array_key_last($horas)]);

        // Y están en la hoja oculta, completas.
        $libro = PlantillaDelPlanificador::generar();
        $listas = $libro->getSheetByName(PlantillaDelPlanificador::HOJA_LISTAS);

        $this->assertSame('00:00', $listas->getCell('B1')->getValue());
        $this->assertSame('12:00', $listas->getCell('B'.(12 * 60 + 1))->getValue());
        $this->assertSame('23:59', $listas->getCell('B'.PlantillaDelPlanificador::HORAS_DEL_DIA)->getValue());
        $this->assertNull($listas->getCell('B'.(PlantillaDelPlanificador::HORAS_DEL_DIA + 1))->getValue());

        // Lo que se elija de esa lista lo entiende la corrida.
        foreach (['00:00', '07:30', '13:45', '23:59'] as $hora) {
            $this->assertSame($hora, Helper::timeFromSpreadsheet($hora));
        }
    }

    /** Las instrucciones explican el envío del diario y las horas de la lista. */
    public function test_las_instrucciones_traen_el_envio_del_diario_y_las_horas(): void
    {
        $libro = PlantillaDelPlanificador::generar();
        $hoja = $libro->getSheetByName(PlantillaDelPlanificador::HOJA_INSTRUCCIONES);

        $textos = [];

        for ($fila = 1; $fila <= $hoja->getHighestRow(); $fila++) {
            $textos[] = (string) $hoja->getCell('B'.$fila)->getValue();
        }

        $todo = implode("\n", $textos);

        $this->assertStringContainsString('CÓMO SE ENTREGA', $todo);
        $this->assertStringContainsString(
            'enviar el mismo al grupo de Telegram de la cohorte correspondiente de Transforma-Conecta',
            $todo
        );
        $this->assertStringContainsString('canal correspondiente para tal actividad', $todo);
        $this->assertStringContainsString('al o a los día(s) que le(s) corresponda(n)', $todo);
        $this->assertStringContainsString('de 00:00 (12:00 a. m.) a 23:59 (11:59 p. m.), minuto a minuto', $todo);

        // Y las instrucciones siguen siendo la última hoja del libro.
        $nombres = $libro->getSheetNames();

        $this->assertSame(PlantillaDelPlanificador::HOJA_INSTRUCCIONES, $nombres[array_key_last($nombres)]);
    }

    /**
     * La planilla de un período: una hoja por día, ya nombrada con la fecha, con
     * la fecha puesta y con la lista de días del período (Excel no tiene
     * calendario desplegable en un .xlsx normal; esto es lo más parecido).
     */
    public function test_la_planilla_de_un_periodo_trae_una_hoja_por_dia(): void
    {
        $ruta = PlantillaDelPlanificador::conservarPeriodo('2026-10-01', '2026-10-05');

        $this->assertNotSame('', $ruta);
        $this->assertTrue(ArchivosDePlanificacion::disco()->exists($ruta));

        $libro = IOFactory::load(ArchivosDePlanificacion::rutaAbsoluta($ruta));

        $this->assertSame([
            '2026-10-01', '2026-10-02', '2026-10-03', '2026-10-04', '2026-10-05',
            PlantillaDelPlanificador::HOJA_LISTAS,
            PlantillaDelPlanificador::HOJA_INSTRUCCIONES,
        ], $libro->getSheetNames());

        // La hoja de las fechas va oculta y con los días del período.
        $listas = $libro->getSheetByName(PlantillaDelPlanificador::HOJA_LISTAS);
        $this->assertSame(Worksheet::SHEETSTATE_HIDDEN, $listas->getSheetState());
        $this->assertSame('01/10/2026', $listas->getCell('A1')->getValue());
        $this->assertSame('05/10/2026', $listas->getCell('A5')->getValue());

        // Cada hoja trae su fecha puesta y la lista para cambiarla.
        $dia = $libro->getSheetByName('2026-10-03');
        $this->assertSame('03/10/2026', $dia->getCell('C4')->getValue());

        $fecha = $dia->getDataValidation('C4');
        $this->assertSame(DataValidation::TYPE_LIST, $fecha->getType());
        $this->assertSame('LISTAS!$A$1:$A$5', $fecha->getFormula1());
        $this->assertTrue($fecha->getShowDropDown());

        // Y sigue trayendo las listas de siempre (energía y horas).
        $this->assertSame('"Baja,Media,Alta"', $dia->getDataValidation('C5')->getFormula1());
        $this->assertTrue($dia->dataValidationExists('B15'));
    }

    /** La hoja oculta de las fechas se ignora, como las instrucciones. */
    public function test_la_corrida_ignora_la_hoja_de_las_fechas(): void
    {
        $ruta = ArchivosDePlanificacion::rutaAbsoluta(
            PlantillaDelPlanificador::conservarPeriodo('2026-10-01', '2026-10-03')
        );

        $lectura = ImportadorDePlanificacion::leer($ruta);

        $hojas = array_column($lectura['hojas'], 'hoja');

        $this->assertSame(['2026-10-01', '2026-10-02', '2026-10-03'], $hojas);
        $this->assertNotContains(PlantillaDelPlanificador::HOJA_LISTAS, $hojas);
        $this->assertNotContains(PlantillaDelPlanificador::HOJA_INSTRUCCIONES, $hojas);
    }

    /* ======================================================================
     |  El lector del libro
     ====================================================================== */

    public function test_la_corrida_crea_solo_los_dias_que_faltan(): void
    {
        // El 02/10 ya está en la base; el 10/10 viene sin cierre.
        $this->crearDia('2026-10-02');

        $this->artisan('planificacion:importar', [
            '--archivo' => $this->libroDeEjemplo(),
            '--dry-run' => true,
        ])->assertSuccessful();

        // Con --dry-run no se escribe nada.
        $this->assertSame(1, DailyPlan::count());

        $this->artisan('planificacion:importar', ['--archivo' => $this->libroDeEjemplo()])
            ->assertSuccessful();

        // El 02/10 no se duplicó, el 10/10 se saltó y los otros tres entraron.
        $this->assertSame(4, DailyPlan::count());
        $this->assertNotNull(DailyPlan::findByDate('2026-10-07'));
        $this->assertNotNull(DailyPlan::findByDate('2026-10-08'));
        $this->assertNotNull(DailyPlan::findByDate('2026-10-09'));
        $this->assertNull(DailyPlan::findByDate('2026-10-10'));

        // Un día completo: sus 3 objetivos, sus franjas y su cierre.
        $plan = DailyPlan::findByDate('2026-10-07');

        $this->assertNotNull($plan);
        $this->assertCount(3, $plan->goals);
        $this->assertCount(3, $plan->scheduleEntries);
        $this->assertSame('Media', $plan->energyLevel->name);
        $this->assertSame('Terminé el informe y caminé.', $plan->achievements);
        $this->assertCount(6, $plan->preparationItems);
        $this->assertCount(5, $plan->reflectionAnswers);
        $this->assertCount(1, $plan->actionBlocks);
        $this->assertCount(2, $plan->notes);
    }

    /**
     * Todo lo que en la hoja se elige de una lista (energía, horas, Sí/No,
     * duración y resultado) tiene que llegar al diario tal cual, porque el cron
     * es el que lee el libro.
     */
    public function test_la_corrida_lee_los_campos_que_se_eligen_de_una_lista(): void
    {
        $this->artisan('planificacion:importar', ['--archivo' => $this->libroDeEjemplo()])
            ->assertSuccessful();

        $plan = DailyPlan::findByDate('2026-10-07');
        $this->assertNotNull($plan);

        // Energía: el valor que ofrece la lista.
        $this->assertSame('Media', $plan->energyLevel->name);

        // Horas (de la lista de horas) y su Sí/No.
        $this->assertSame(
            ['07:00', '08:00', '10:00'],
            $plan->scheduleEntries->map(fn (ScheduleEntry $franja) => Helper::time($franja->start_time, 'H:i'))->all()
        );
        $this->assertSame(
            [true, true, false],
            $plan->scheduleEntries->map(fn (ScheduleEntry $franja) => (bool) $franja->is_done)->all()
        );

        // Los 3 objetivos con su "Cumplido" (Sí/No) y su tipo del catálogo.
        $objetivos = $plan->goals->keyBy('slot');
        $this->assertSame('Terminar el informe mensual', $objetivos[1]->description);
        $this->assertTrue((bool) $objetivos[1]->is_done);
        $this->assertFalse((bool) $objetivos[2]->is_done);
        $this->assertSame('Debo hacer', $objetivos[1]->goalType->name);

        // Checklist "Antes de empezar": los Sí y su detalle.
        $marcados = $plan->preparationItems->filter(fn (PreparationItem $item) => (bool) $item->pivot->is_checked);
        $this->assertSame(['Materiales', 'Espacio organizado'], $marcados->pluck('name')->values()->all());
        $this->assertSame('PC, cuaderno y calculadora', $marcados->first()->pivot->preparation_items_description);

        // Preguntas de procrastinación: la marcada y su respuesta.
        $marcadas = $plan->reflectionAnswers->filter(fn (ReflectionAnswer $r) => (bool) $r->is_checked);
        $this->assertCount(1, $marcadas);
        $this->assertSame('¿Qué estoy evitando?', $marcadas->first()->question->question);
        $this->assertSame('El informe largo', $marcadas->first()->answer);

        // Bloque de acción: duración y resultado (las dos de lista) y las horas.
        $bloque = $plan->actionBlocks->first();
        $this->assertSame('20 minutos', $bloque->duration->label);
        $this->assertSame(20, $bloque->duration->minutes);
        $this->assertSame('Avancé', $bloque->outcome->name);
        $this->assertSame('Redactar la introducción', $bloque->task);
        $this->assertSame('2026-10-07 08:00:00', $bloque->started_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-07 08:20:00', $bloque->finished_at->format('Y-m-d H:i:s'));

        // Cierre del día completo y notas.
        $this->assertSame('Falta revisar las cifras del anexo.', $plan->pending);
        $this->assertSame('Mañana temprano', $plan->pending_when);
        $this->assertSame('Cumplí el horario que me propuse.', $plan->proud_of);
        $this->assertSame(
            ['Revisar el correo antes de dormir', 'Preparar la ropa para mañana'],
            $plan->notes->pluck('content')->all()
        );
    }

    /**
     * El mismo libro, pero con los valores como los guarda Excel de verdad: la
     * fecha y la hora como número de serie, y los Sí/No como verdadero/falso.
     * La lista deplegable escribe texto, pero quien tipea 7:00 o pone una fecha
     * puede terminar con valores nativos y la corrida tiene que leerlos igual.
     */
    public function test_la_corrida_lee_los_valores_nativos_de_excel(): void
    {
        PlantillaDelPlanificador::conservarEjemplo();
        $ruta = ArchivosDePlanificacion::rutaAbsoluta($this->rutaEjemplo());

        // Se reescribe una hoja con los tipos nativos de Excel.
        $libro = IOFactory::load($ruta);
        $hoja = $libro->getSheetByName('2026-10-09');

        PlantillaDelPlanificador::escribirEnFila($hoja, PlantillaDelPlanificador::buscarFila($hoja, PlantillaDelPlanificador::ETIQUETA_FECHA), 'C', FechaExcel::PHPToExcel(new \DateTime('2026-10-09')));
        PlantillaDelPlanificador::escribirEnFila($hoja, PlantillaDelPlanificador::buscarFila($hoja, PlantillaDelPlanificador::ETIQUETA_ENERGIA), 'C', 'Alta');

        $filaHorario = PlantillaDelPlanificador::buscarFila($hoja, PlantillaDelPlanificador::TITULO_HORARIO) + 2;
        PlantillaDelPlanificador::escribirEnFila($hoja, $filaHorario, 'B', FechaExcel::PHPToExcel(new \DateTime('1899-12-30 09:30:00')));
        PlantillaDelPlanificador::escribirEnFila($hoja, $filaHorario, 'C', 'Reunión con el equipo');
        PlantillaDelPlanificador::escribirEnFila($hoja, $filaHorario, 'D', true);
        PlantillaDelPlanificador::escribirEnFila($hoja, $filaHorario + 1, 'B', '14:15');
        PlantillaDelPlanificador::escribirEnFila($hoja, $filaHorario + 1, 'C', 'Revisar el anexo');
        PlantillaDelPlanificador::escribirEnFila($hoja, $filaHorario + 1, 'D', false);

        // La hoja de ejemplo traía una tercera franja: se vacía para que la
        // prueba quede con las dos de arriba y nada más.
        PlantillaDelPlanificador::escribirEnFila($hoja, $filaHorario + 2, 'B', null);
        PlantillaDelPlanificador::escribirEnFila($hoja, $filaHorario + 2, 'C', null);
        PlantillaDelPlanificador::escribirEnFila($hoja, $filaHorario + 2, 'D', null);

        (new Xlsx($libro))->save($ruta);

        $this->artisan('planificacion:importar', ['--archivo' => $ruta])->assertSuccessful();

        $plan = DailyPlan::findByDate('2026-10-09');
        $this->assertNotNull($plan);
        $this->assertSame('2026-10-09', $plan->plan_date->toDateString());
        $this->assertSame('Alta', $plan->energyLevel->name);
        $this->assertSame(
            ['09:30', '14:15'],
            $plan->scheduleEntries->map(fn (ScheduleEntry $franja) => Helper::time($franja->start_time, 'H:i'))->all()
        );
        $this->assertSame(
            [true, false],
            $plan->scheduleEntries->map(fn (ScheduleEntry $franja) => (bool) $franja->is_done)->all()
        );
    }

    public function test_un_dia_ya_cargado_no_se_pisa(): void
    {
        $this->artisan('planificacion:importar', ['--archivo' => $this->libroDeEjemplo()])
            ->assertSuccessful();

        $plan = DailyPlan::findByDate('2026-10-07');
        $plan->updateDay(['achievements' => 'Lo escribí yo, desde el formulario.']);

        $this->artisan('planificacion:importar', ['--archivo' => $this->libroDeEjemplo()])
            ->assertSuccessful();

        $this->assertSame('Lo escribí yo, desde el formulario.', $plan->fresh()->achievements);
        $this->assertSame(4, DailyPlan::count());
    }

    public function test_un_diario_eliminado_se_vuelve_a_crear(): void
    {
        $this->artisan('planificacion:importar', ['--archivo' => $this->libroDeEjemplo()])
            ->assertSuccessful();

        DailyPlan::findByDate('2026-10-07')->deleteDay();

        $this->assertNull(DailyPlan::findByDate('2026-10-07'));

        $this->artisan('planificacion:importar', ['--archivo' => $this->libroDeEjemplo()])
            ->assertSuccessful();

        $this->assertNotNull(DailyPlan::findByDate('2026-10-07'));
    }

    public function test_una_hoja_sin_cierre_se_salta_y_el_libro_queda_parcial(): void
    {
        // El 02/10 ya está cargado: tiene que quedar como omitido.
        $this->crearDia('2026-10-02');

        $this->artisan('planificacion:importar', ['--archivo' => $this->libroDeEjemplo()])
            ->assertSuccessful();

        $libro = PlanificacionImportacion::where('archivo', basename($this->rutaEjemplo()))->first();

        $this->assertNotNull($libro);
        $this->assertSame(PlanificacionImportacion::PARCIAL, $libro->estado);
        $this->assertSame(1, $libro->hojas_saltadas);
        $this->assertSame(3, $libro->hojas_creadas);
        $this->assertSame(1, $libro->hojas_omitidas);

        $saltada = $libro->hojas()->where('accion', PlanificacionHoja::SALTADO)->first();

        $this->assertNotNull($saltada);
        $this->assertSame('2026-10-10', $saltada->fecha->toDateString());
        $this->assertStringContainsString('cierre del día', (string) $saltada->motivo);
    }

    public function test_la_hoja_de_instrucciones_se_ignora_como_si_no_existiera(): void
    {
        $lectura = ImportadorDePlanificacion::leer($this->libroDeEjemplo());

        $hojas = array_column($lectura['hojas'], 'hoja');

        $this->assertNotContains(PlantillaDelPlanificador::HOJA_INSTRUCCIONES, $hojas);
        $this->assertContains(PlantillaDelPlanificador::HOJA_DIA, $hojas);
    }

    public function test_la_corrida_sin_libros_no_hace_nada(): void
    {
        $this->artisan('planificacion:importar')->assertSuccessful();

        $this->assertSame(0, DailyPlan::count());
    }

    /* ======================================================================
     |  El módulo
     ====================================================================== */

    public function test_el_modulo_se_abre_y_descarga_la_plantilla(): void
    {
        PlantillaDelPlanificador::conservar();

        $this->get(route('planificacion.index', absolute: false))
            ->assertOk()
            ->assertSee('Planificación periódica')
            ->assertSee('Monta el libro del período')
            ->assertSee(ArchivosDePlanificacion::PLANTILLA);

        $this->get(route('planificacion.plantilla', absolute: false))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    /** La planilla de un período se descarga por su nombre y sólo si existe. */
    public function test_el_modulo_descarga_la_planilla_de_un_periodo(): void
    {
        PlantillaDelPlanificador::conservar();
        PlantillaDelPlanificador::conservarPeriodo('2026-10-01', '2026-10-03');

        $nombre = PlantillaDelPlanificador::nombreDePeriodo('2026-10-01', '2026-10-03');

        // El módulo la lista junto a la genérica.
        $this->get(route('planificacion.index', absolute: false))
            ->assertOk()
            ->assertSee($nombre)
            ->assertSee('Una hoja por día, ya nombrada');

        $this->get(route('planificacion.plantilla', ['archivo' => $nombre], absolute: false))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertHeader('content-disposition', 'attachment; filename='.$nombre);

        // Un nombre que no está en la carpeta no se descarga.
        $this->get(route('planificacion.plantilla', ['archivo' => 'otra-cosa.xlsx'], absolute: false))
            ->assertRedirect(route('planificacion.index', absolute: false))
            ->assertSessionHas('error');
    }

    public function test_montar_un_libro_lo_deja_en_la_cola_sin_procesar_nada(): void
    {
        PlantillaDelPlanificador::conservar();

        $libro = new UploadedFile(
            ArchivosDePlanificacion::rutaAbsoluta(ArchivosDePlanificacion::rutaPlantilla()),
            'planilla octubre.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $this->post(route('planificacion.store', absolute: false), ['archivo' => $libro])
            ->assertRedirect(route('planificacion.index', absolute: false))
            ->assertSessionHas('exito');

        // El nombre queda saneado y el archivo, en la cola.
        $this->assertTrue(ArchivosDePlanificacion::estaEnCola('planilla-octubre.xlsx'));

        $registro = PlanificacionImportacion::where('archivo', 'planilla-octubre.xlsx')->first();

        $this->assertNotNull($registro);
        $this->assertSame(PlanificacionImportacion::EN_ESPERA, $registro->estado);

        // Montar no procesa: la base sigue vacía.
        $this->assertSame(0, DailyPlan::count());
    }

    public function test_montar_dos_veces_el_mismo_libro_lo_reemplaza(): void
    {
        PlantillaDelPlanificador::conservar();

        foreach (['planilla octubre.xlsx', 'PLANILLA OCTUBRE.XLSX'] as $nombre) {
            $libro = new UploadedFile(
                ArchivosDePlanificacion::rutaAbsoluta(ArchivosDePlanificacion::rutaPlantilla()),
                $nombre,
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                null,
                true
            );

            $this->post(route('planificacion.store', absolute: false), ['archivo' => $libro])->assertSessionHas('exito');
        }

        $this->assertCount(1, ArchivosDePlanificacion::librosEnCola());
        $this->assertSame(1, PlanificacionImportacion::count());
    }

    public function test_un_libro_se_puede_sacar_de_la_cola(): void
    {
        PlantillaDelPlanificador::conservar();

        $libro = new UploadedFile(
            ArchivosDePlanificacion::rutaAbsoluta(ArchivosDePlanificacion::rutaPlantilla()),
            'planilla octubre.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $this->post(route('planificacion.store', absolute: false), ['archivo' => $libro]);

        $this->delete(route('planificacion.descartar', ['archivo' => 'planilla-octubre.xlsx'], absolute: false))
            ->assertRedirect(route('planificacion.index', absolute: false))
            ->assertSessionHas('exito');

        $this->assertFalse(ArchivosDePlanificacion::estaEnCola('planilla-octubre.xlsx'));
        $this->assertTrue(ArchivosDePlanificacion::disco()->exists(
            ArchivosDePlanificacion::CARPETA_DESCARTADOS.'/planilla-octubre.xlsx'
        ));

        $this->assertSame(
            PlanificacionImportacion::DESCARTADO,
            PlanificacionImportacion::where('archivo', 'planilla-octubre.xlsx')->first()->estado
        );
    }

    /* ======================================================================
     |  Eliminar varios libros de la cola (AJAX del módulo)
     ====================================================================== */

    public function test_el_modulo_elimina_varios_libros_de_la_cola(): void
    {
        $this->montarLibro('planilla octubre.xlsx');
        $this->montarLibro('planilla noviembre.xlsx');
        $this->montarLibro('planilla diciembre.xlsx');

        $this->assertCount(3, ArchivosDePlanificacion::librosEnCola());

        // Se eligen dos de los tres (el otro tiene que quedar en la cola).
        $respuesta = $this->deleteJson(route('planificacion.descartar.varios', absolute: false), [
            'archivos' => ['planilla-octubre.xlsx', 'planilla-noviembre.xlsx'],
        ]);

        $respuesta->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.eliminados', ['planilla-octubre.xlsx', 'planilla-noviembre.xlsx'])
            ->assertJsonPath('data.fallos', []);

        // El mensaje del sobre es el que muestra el módulo.
        $this->assertStringContainsString(
            '2 libros fueron eliminados de la cola',
            (string) $respuesta->json('message')
        );

        // Los dos salieron de la cola y quedaron guardados en descartados.
        $this->assertFalse(ArchivosDePlanificacion::estaEnCola('planilla-octubre.xlsx'));
        $this->assertFalse(ArchivosDePlanificacion::estaEnCola('planilla-noviembre.xlsx'));
        $this->assertTrue(ArchivosDePlanificacion::estaEnCola('planilla-diciembre.xlsx'));

        foreach (['planilla-octubre.xlsx', 'planilla-noviembre.xlsx'] as $nombre) {
            $this->assertTrue(ArchivosDePlanificacion::disco()->exists(
                ArchivosDePlanificacion::CARPETA_DESCARTADOS.'/'.$nombre
            ));

            $this->assertSame(
                PlanificacionImportacion::DESCARTADO,
                PlanificacionImportacion::where('archivo', $nombre)->first()->estado
            );
        }

        $this->assertSame(
            PlanificacionImportacion::EN_ESPERA,
            PlanificacionImportacion::where('archivo', 'planilla-diciembre.xlsx')->first()->estado
        );
    }

    public function test_el_modulo_no_elimina_nada_si_no_hay_seleccion(): void
    {
        $this->montarLibro('planilla octubre.xlsx');

        // Sin la lista de archivos, la validación contesta con el sobre de error.
        $this->deleteJson(route('planificacion.descartar.varios', absolute: false))
            ->assertStatus(422)
            ->assertJsonPath('ok', false);

        // Y la cola queda como estaba.
        $this->assertTrue(ArchivosDePlanificacion::estaEnCola('planilla-octubre.xlsx'));
    }

    public function test_el_modulo_elimina_los_que_puede_y_avisa_los_que_no(): void
    {
        $this->montarLibro('planilla octubre.xlsx');

        $respuesta = $this->deleteJson(route('planificacion.descartar.varios', absolute: false), [
            'archivos' => ['planilla-octubre.xlsx', 'no-existe.xlsx'],
        ]);

        $respuesta->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.eliminados', ['planilla-octubre.xlsx'])
            ->assertJsonPath('data.fallos', ['no-existe.xlsx (ya no estaba en la cola)']);

        $this->assertStringContainsString('no-existe.xlsx', (string) $respuesta->json('message'));
        $this->assertFalse(ArchivosDePlanificacion::estaEnCola('planilla-octubre.xlsx'));
    }

    public function test_el_modulo_avisa_si_ninguno_se_pudo_eliminar(): void
    {
        $respuesta = $this->deleteJson(route('planificacion.descartar.varios', absolute: false), [
            'archivos' => ['no-existe.xlsx'],
        ]);

        $respuesta->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('errors', ['no-existe.xlsx (ya no estaba en la cola)']);

        $this->assertStringContainsString(
            'No se pudo eliminar ningún libro',
            (string) $respuesta->json('message')
        );
    }

    /* ======================================================================
     |  Montar y refrescar por AJAX (sin recargar la página)
     ====================================================================== */

    /** Con el encabezado de JSON, montar contesta el sobre en vez de redirigir. */
    public function test_montar_un_libro_por_ajax_no_recarga(): void
    {
        PlantillaDelPlanificador::conservar();

        $respuesta = $this->post(
            route('planificacion.store', absolute: false),
            ['archivo' => $this->archivoDePrueba('planilla octubre.xlsx')],
            ['Accept' => 'application/json']
        );

        $respuesta->assertStatus(201)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.archivo', 'planilla-octubre.xlsx');

        // No es una redirección: el navegador no recarga nada. (Ojo: el 201
        // cuenta como "redirect" para Symfony, así que se mira la cabecera.)
        $this->assertNull($respuesta->headers->get('Location'));
        $this->assertStringContainsString('quedó en la cola', (string) $respuesta->json('message'));

        $this->assertTrue(ArchivosDePlanificacion::estaEnCola('planilla-octubre.xlsx'));
        $this->assertSame(0, DailyPlan::count());
    }

    /** Un archivo que no sirve contesta el error por JSON, no una redirección. */
    public function test_montar_por_ajax_avisa_si_el_archivo_no_sirve(): void
    {
        $respuesta = $this->post(
            route('planificacion.store', absolute: false),
            ['archivo' => UploadedFile::fake()->create('planilla.txt', 4, 'text/plain')],
            ['Accept' => 'application/json']
        );

        $respuesta->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'El libro tiene que ser .xlsx (Excel).');

        // Un archivo que no sirve no se guarda ni deja nada en la cola.
        $this->assertSame([], ArchivosDePlanificacion::librosEnCola());
    }

    /** La cola se puede volver a pedir sola: es lo que refresca el módulo. */
    public function test_la_cola_se_puede_pedir_por_ajax(): void
    {
        // Sin libros: el bloque trae el aviso de que no hay nada montado.
        $vacia = $this->get(route('planificacion.estado', absolute: false), ['Accept' => 'application/json']);

        $vacia->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.cola.en_cola', 0);

        $this->assertStringContainsString('Todavía no hay ningún libro montado', (string) $vacia->json('data.cola.html'));

        // Con un libro: el bloque trae la tabla, las casillas y el nombre.
        $this->montarLibro('planilla octubre.xlsx');

        $conLibro = $this->get(route('planificacion.estado', absolute: false), ['Accept' => 'application/json']);

        $conLibro->assertOk()
            ->assertJsonPath('data.cola.en_cola', 1)
            ->assertJsonPath('data.cola.enCola', 1)
            ->assertJsonPath('data.cola.archivos', ['planilla-octubre.xlsx']);

        // La huella sirve para no tocar la pantalla cuando nada cambió.
        $this->assertNotEmpty($conLibro->json('data.cola.huella'));
        $this->assertSame(
            md5((string) $conLibro->json('data.cola.html')),
            $conLibro->json('data.cola.huella')
        );

        $html = (string) $conLibro->json('data.cola.html');

        foreach ([
            'tf-cola__check', 'cola-eliminar', 'cola-todos', 'tf-cola__quitar',
            'planilla-octubre.xlsx', 'id="tabla-cola"', 'data-filtro', 'cola-latido',
        ] as $marca) {
            $this->assertStringContainsString($marca, $html);
        }

        // La huella cambia cuando cambia la cola.
        $this->montarLibro('planilla noviembre.xlsx');

        $otra = $this->get(route('planificacion.estado', absolute: false), ['Accept' => 'application/json']);

        $otra->assertOk()->assertJsonPath('data.cola.en_cola', 2);
        $this->assertNotSame($conLibro->json('data.cola.huella'), $otra->json('data.cola.huella'));
    }

    /**
     * El detalle de la última corrida viaja en el mismo estado, con su tabla de
     * DataTables, sus filtros y su huella.
     */
    public function test_el_detalle_de_la_corrida_se_puede_pedir_por_ajax(): void
    {
        // Antes de correr, el bloque avisa que todavía no corrió.
        $antes = $this->get(route('planificacion.estado', absolute: false), ['Accept' => 'application/json']);

        $antes->assertOk();
        $this->assertStringContainsString(
            'Todavía no corrió ninguna vez',
            (string) $antes->json('data.corrida.html')
        );

        // Se monta un libro y se corre.
        $this->post(route('planificacion.store', absolute: false), [
            'archivo' => $this->archivoDeEjemplo('semana 1.xlsx'),
        ])->assertSessionHas('exito');

        $this->artisan('planificacion:importar')->assertSuccessful();

        $despues = $this->get(route('planificacion.estado', absolute: false), ['Accept' => 'application/json']);

        $despues->assertOk();

        // La huella es la del HTML: así el módulo no toca la pantalla de gusto.
        $this->assertSame(
            md5((string) $despues->json('data.corrida.html')),
            $despues->json('data.corrida.huella')
        );

        $html = (string) $despues->json('data.corrida.html');

        foreach ([
            'id="tabla-corrida"',   // la tabla que maneja DataTables
            'data-filtro',          // los filtros por columna
            'Todas las acciones',   // el filtro de acción
            'Saltado',              // una acción en la tabla
            'semana-1.xlsx',        // el libro de la corrida
        ] as $marca) {
            $this->assertStringContainsString($marca, $html);
        }

        // Y la huella es otra cuando el contenido es otro.
        $this->assertNotSame($antes->json('data.corrida.huella'), $despues->json('data.corrida.huella'));
    }

    /**
     * Volver a montar un libro que ya está en la cola reemplaza el archivo y su
     * estado: vuelve a "en espera" y se borra la observación de la corrida
     * anterior (por ejemplo, una hoja saltada).
     */
    public function test_volver_a_montar_el_mismo_libro_lo_reemplaza(): void
    {
        // 1) Un libro montado y una corrida encima: queda parcial, con su detalle.
        $this->post(route('planificacion.store', absolute: false), [
            'archivo' => $this->archivoDeEjemplo('semana 1.xlsx'),
        ])->assertSessionHas('exito');

        $this->artisan('planificacion:importar')->assertSuccessful();

        $registro = PlanificacionImportacion::where('archivo', 'semana-1.xlsx')->first();

        $this->assertNotNull($registro);
        $this->assertSame(PlanificacionImportacion::PARCIAL, $registro->estado);
        $this->assertGreaterThan(0, $registro->hojas_saltadas);
        $this->assertGreaterThan(0, $registro->hojas()->count());
        $this->assertNotNull($registro->procesado_en);

        // 2) Se vuelve a montar con el mismo nombre: se reemplaza.
        $respuesta = $this->post(
            route('planificacion.store', absolute: false),
            ['archivo' => $this->archivoDePrueba('semana 1.xlsx')],
            ['Accept' => 'application/json']
        );

        $respuesta->assertStatus(201)->assertJsonPath('ok', true);
        $this->assertStringContainsString('se volvió a montar', (string) $respuesta->json('message'));

        $registro = $registro->fresh();

        $this->assertSame(PlanificacionImportacion::EN_ESPERA, $registro->estado);
        $this->assertNull($registro->procesado_en);
        $this->assertSame(0, $registro->hojas_saltadas);
        $this->assertSame(0, $registro->hojas()->count());
        $this->assertSame(1, PlanificacionImportacion::count());

        // El archivo de la cola es el nuevo: la planilla genérica trae DIA, no días.
        $libro = IOFactory::load(ArchivosDePlanificacion::rutaAbsoluta(
            ArchivosDePlanificacion::rutaEnCola('semana-1.xlsx')
        ));

        $this->assertSame(
            [
                PlantillaDelPlanificador::HOJA_DIA,
                PlantillaDelPlanificador::HOJA_LISTAS,
                PlantillaDelPlanificador::HOJA_INSTRUCCIONES,
            ],
            $libro->getSheetNames()
        );

        // Y la observación anterior ya no aparece en el detalle: no hay corrida
        // nueva que mostrar.
        $estado = $this->get(route('planificacion.estado', absolute: false), ['Accept' => 'application/json']);

        $this->assertStringContainsString(
            'Todavía no corrió ninguna vez',
            (string) $estado->json('data.corrida.html')
        );
    }

    /** Quitar un libro con el botón de su fila también es AJAX. */
    public function test_un_libro_se_puede_quitar_por_ajax(): void
    {
        $this->montarLibro('planilla octubre.xlsx');

        $respuesta = $this->deleteJson(
            route('planificacion.descartar', ['archivo' => 'planilla-octubre.xlsx'], absolute: false)
        );

        $respuesta->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.archivo', 'planilla-octubre.xlsx');

        $this->assertStringContainsString('salió de la cola', (string) $respuesta->json('message'));
        $this->assertFalse(ArchivosDePlanificacion::estaEnCola('planilla-octubre.xlsx'));

        // Y si ya no está, avisa con un 404 en el mismo sobre.
        $this->deleteJson(
            route('planificacion.descartar', ['archivo' => 'planilla-octubre.xlsx'], absolute: false)
        )->assertStatus(404)->assertJsonPath('ok', false);
    }

    /**
     * La cola se dibuja también con libros ya corridos: la fila trae la fecha de
     * la corrida (y, si se descartó, la del descarte). Es el caso que rompió la
     * vista una vez: el parcial usa Helper::dateTime y necesita su propio @use.
     */
    public function test_la_cola_dibuja_los_libros_ya_corridos(): void
    {
        $this->post(route('planificacion.store', absolute: false), [
            'archivo' => $this->archivoDeEjemplo('libro de ejemplo.xlsx'),
        ])->assertSessionHas('exito');

        // Una corrida de verdad: el registro queda con su fecha de proceso.
        $this->artisan('planificacion:importar')->assertSuccessful();

        $registro = PlanificacionImportacion::where('archivo', 'libro-de-ejemplo.xlsx')->first();

        $this->assertNotNull($registro);
        $this->assertNotNull($registro->procesado_en);

        // La pantalla del módulo dibuja esa fila.
        $this->get(route('planificacion.index', absolute: false))
            ->assertOk()
            ->assertSee('libro-de-ejemplo.xlsx');

        // Y el bloque que refresca el AJAX también, con la fecha en palabras.
        $cola = $this->get(route('planificacion.estado', absolute: false), ['Accept' => 'application/json']);
        $cola->assertOk();

        $this->assertStringContainsString(
            Helper::dateTime($registro->procesado_en),
            (string) $cola->json('data.cola.html')
        );

        // Un libro descartado también se dibuja (la otra rama de la fecha).
        $this->deleteJson(
            route('planificacion.descartar', ['archivo' => 'libro-de-ejemplo.xlsx'], absolute: false)
        )->assertOk();

        $this->get(route('planificacion.index', absolute: false))
            ->assertOk()
            ->assertSee('Descartado');
    }

    /** El inicio tiene el mismo acceso al módulo que el menú lateral. */
    public function test_el_inicio_tiene_el_acceso_al_modulo(): void
    {
        $this->get(route('home', absolute: false))
            ->assertOk()
            ->assertSee('Planificación periódica')
            ->assertSee('Montar el libro')
            ->assertSee(route('planificacion.index', absolute: false));
    }

    /* ======================================================================
     |  La corrida a mano
     ====================================================================== */

    /** Sin libros en la cola no hay nada que correr: avisa y no arranca nada. */
    public function test_la_corrida_a_mano_avisa_si_no_hay_libros(): void
    {
        $respuesta = $this->postJson(route('planificacion.corrida.ejecutar', absolute: false));

        $respuesta->assertStatus(422)->assertJsonPath('ok', false);
        $this->assertStringContainsString('No hay ningún libro en la cola', (string) $respuesta->json('message'));
    }

    /** El botón arranca la corrida: contesta en el acto y deja el progreso. */
    public function test_el_modulo_arranca_la_corrida_a_mano(): void
    {
        $this->montarLibro('planilla octubre.xlsx');

        $lanzador = $this->lanzadorDeMentira();

        $respuesta = $this->postJson(route('planificacion.corrida.ejecutar', absolute: false));

        $respuesta->assertStatus(202)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.comando', $lanzador->comando());

        $this->assertTrue($lanzador->lanzado);

        // El progreso queda arrancado y el módulo ya lo puede leer.
        $estado = ArchivosDePlanificacion::estadoDeLaCorrida();

        $this->assertSame('en_curso', $estado['estado']);
        $this->assertSame(0, $estado['porcentaje']);
        $this->assertSame(1, $estado['libros']);

        $this->getJson(route('planificacion.corrida', absolute: false))
            ->assertOk()
            ->assertJsonPath('data.estado', 'en_curso');
    }

    /** Si ya hay una corrida en curso no se arranca otra. */
    public function test_la_corrida_a_mano_no_se_pisa_a_si_misma(): void
    {
        $this->montarLibro('planilla octubre.xlsx');
        $this->lanzadorDeMentira();

        $this->postJson(route('planificacion.corrida.ejecutar', absolute: false))->assertStatus(202);

        $this->postJson(route('planificacion.corrida.ejecutar', absolute: false))
            ->assertStatus(409)
            ->assertJsonPath('ok', false);
    }

    /** Una corrida que quedó colgada se informa como interrumpida, no en curso. */
    public function test_una_corrida_colgada_se_informa_interrumpida(): void
    {
        ArchivosDePlanificacion::guardarEstadoDeLaCorrida([
            'estado' => 'en_curso',
            'iniciada_en' => Helper::now()->subHours(5)->toDateTimeString(),
            // Hace 5 horas que el comando no da señales: el proceso ya no está.
            'actualizado_en' => Helper::now()->subHours(5)->toDateTimeString(),
            'porcentaje' => 40,
            'paso' => 'Leyendo…',
        ]);

        $this->getJson(route('planificacion.corrida', absolute: false))
            ->assertOk()
            ->assertJsonPath('data.estado', 'interrumpida')
            ->assertJsonPath('data.resultado', 'con_errores');

        // Y deja arrancar una corrida nueva.
        $this->montarLibro('planilla octubre.xlsx');
        $this->lanzadorDeMentira();

        $this->postJson(route('planificacion.corrida.ejecutar', absolute: false))->assertStatus(202);
    }

    /** Sin ninguna corrida anterior, el estado lo dice. */
    public function test_la_corrida_sin_estado_avisa_que_no_corrio(): void
    {
        $this->getJson(route('planificacion.corrida', absolute: false))
            ->assertOk()
            ->assertJsonPath('data.estado', 'sin_corrida')
            ->assertJsonPath('data.porcentaje', 0);
    }

    /**
     * La corrida de verdad deja el progreso, el resumen y las observaciones: es
     * lo que muestra la barra y lo que queda en su log.
     */
    public function test_la_corrida_deja_el_progreso_y_su_log(): void
    {
        $this->post(route('planificacion.store', absolute: false), [
            'archivo' => $this->archivoDeEjemplo('semana 1.xlsx'),
        ])->assertSessionHas('exito');

        $this->artisan(ImportarPlanificacion::COMANDO)->assertSuccessful();

        $estado = ArchivosDePlanificacion::estadoDeLaCorrida();

        $this->assertSame('terminada', $estado['estado']);
        $this->assertSame(100, $estado['porcentaje']);
        $this->assertNotNull($estado['iniciada_en']);
        $this->assertNotNull($estado['terminada_en']);

        // El libro de ejemplo trae una hoja sin cierre: queda como observación.
        $this->assertSame('con_observaciones', $estado['resultado']);
        $this->assertSame('Corrida completada con observaciones', $estado['resultado_en_palabras']);
        $this->assertSame(1, $estado['resumen']['saltados']);
        $this->assertNotEmpty($estado['observaciones']);
        $this->assertStringContainsString('2026-10-10', implode(' ', $estado['observaciones']));

        // Y todo el paso a paso queda en el log de la corrida.
        $log = File::get(ArchivosDePlanificacion::rutaDelLogDeLaCorrida());

        foreach ([
            'Corrida iniciada',
            '100% · Corrida terminada',
            'Resumen ·',
            'Resultado · Corrida completada con observaciones',
            'Observación · Hoja 2026-10-10',
        ] as $marca) {
            $this->assertStringContainsString($marca, $log);
        }
    }

    /** La corrida del cron también deja su rastro. */
    public function test_la_corrida_del_cron_tambien_deja_su_log(): void
    {
        $this->post(route('planificacion.store', absolute: false), [
            'archivo' => $this->archivoDeEjemplo('semana 1.xlsx'),
        ]);

        $this->artisan(ImportarPlanificacion::COMANDO)->assertSuccessful();

        $estado = ArchivosDePlanificacion::estadoDeLaCorrida();

        $this->assertSame('terminada', $estado['estado']);

        $this->assertStringContainsString(
            'Corrida iniciada',
            File::get(ArchivosDePlanificacion::rutaDelLogDeLaCorrida())
        );
    }

    /** La vista del módulo trae el botón, la confirmación y la barra de progreso. */
    public function test_el_modulo_trae_el_boton_y_la_barra_de_la_corrida(): void
    {
        $this->montarLibro('planilla octubre.xlsx');

        $respuesta = $this->get(route('planificacion.index', absolute: false));

        $respuesta->assertOk();

        foreach ([
            'id="corrida-ejecutar"',
            'Ejecutar corrida',
            'id="modalCorrida"',
            'id="corrida-barra"',
            'id="corrida-barra-relleno"',
            'id="corrida-paso"',
            'id="corrida-resultado"',
            '¿Desea ejecutar la corrida manual?',
            'la corrida sigue sola',
        ] as $marca) {
            $respuesta->assertSee($marca, false);
        }
    }

    /**
     * El botón tiene que invocar el mismo comando que el cron: ni una versión
     * aparte, ni opciones de más. Esta prueba es el candado de eso.
     */
    public function test_el_boton_corre_el_mismo_comando_que_el_cron(): void
    {
        $linea = (new LanzadorDeCorridas)->comando();

        // Es el mismo comando, sin ninguna opción.
        $this->assertStringEndsWith('"'.base_path('artisan').'" '.ImportarPlanificacion::COMANDO, $linea);
        $this->assertStringNotContainsString('--', $linea);

        // Y ese nombre es el que está programado en el horario del cron.
        $this->assertStringContainsString(
            'Schedule::command(ImportarPlanificacion::COMANDO)',
            File::get(base_path('routes/console.php'))
        );

        // El comando existe con ese nombre exacto (el mismo que corre a mano).
        $this->assertArrayHasKey(ImportarPlanificacion::COMANDO, Artisan::all());
        $this->assertSame(
            '"'.(new PhpExecutableFinder)->find(false).'" "'.base_path('artisan').'" '.ImportarPlanificacion::COMANDO,
            $linea
        );
    }

    /* ======================================================================
     |  Ayudas
     ====================================================================== */

    /**
     * Un lanzador de mentira: en las pruebas no se arranca ningún proceso de
     * verdad. Si se arrancara, el comando correría contra la base real y
     * procesaría la cola de verdad.
     */
    private function lanzadorDeMentira(): LanzadorDeCorridas
    {
        $lanzador = new class extends LanzadorDeCorridas
        {
            public bool $lanzado = false;

            public function lanzar(): void
            {
                $this->lanzado = true;
            }
        };

        $this->app->instance(LanzadorDeCorridas::class, $lanzador);

        return $lanzador;
    }

    /** Monta un libro en la cola como si se hubiera subido desde el módulo. */
    private function montarLibro(string $nombre): string
    {
        PlantillaDelPlanificador::conservar();

        $this->post(route('planificacion.store', absolute: false), [
            'archivo' => $this->archivoDePrueba($nombre),
        ])->assertSessionHas('exito');

        return Helper::safeFileName($nombre);
    }

    /** Un .xlsx de mentira con la planilla de verdad adentro. */
    private function archivoDePrueba(string $nombre): UploadedFile
    {
        PlantillaDelPlanificador::conservar();

        return new UploadedFile(
            ArchivosDePlanificacion::rutaAbsoluta(ArchivosDePlanificacion::rutaPlantilla()),
            $nombre,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    /** Un .xlsx con el libro de ejemplo (días ya llenos) adentro. */
    private function archivoDeEjemplo(string $nombre): UploadedFile
    {
        PlantillaDelPlanificador::conservarEjemplo();

        return new UploadedFile(
            ArchivosDePlanificacion::rutaAbsoluta($this->rutaEjemplo()),
            $nombre,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    /** Un diario mínimo, como si se hubiera cargado a mano. */
    private function crearDia(string $fecha): DailyPlan
    {
        return DailyPlan::createDay([
            'plan_date' => $fecha,
            'energy_level_id' => EnergyLevel::query()->first()->id,
            'goals' => [
                ['slot' => 1, 'description' => 'Un objetivo ya cargado'],
            ],
        ]);
    }

    /** El libro de ejemplo, guardado en el disco aislado de la prueba. */
    private function libroDeEjemplo(): string
    {
        PlantillaDelPlanificador::conservarEjemplo();

        return ArchivosDePlanificacion::rutaAbsoluta($this->rutaEjemplo());
    }

    /** La ruta relativa del libro de ejemplo dentro del disco. */
    private function rutaEjemplo(): string
    {
        return ArchivosDePlanificacion::CARPETA_EJEMPLOS.'/libro-ejemplo.xlsx';
    }
}
