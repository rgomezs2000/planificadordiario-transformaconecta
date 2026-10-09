<?php

namespace Tests\Feature;

use App\Excel\PlantillaDelPlanificador;
use App\Models\DailyPlan;
use App\Models\EnergyLevel;
use App\Models\PlanificacionHoja;
use App\Models\PlanificacionImportacion;
use App\Models\PreparationItem;
use App\Planificacion\ArchivosDePlanificacion;
use App\Planificacion\ImportadorDePlanificacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Protection;
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
            [PlantillaDelPlanificador::HOJA_DIA, PlantillaDelPlanificador::HOJA_INSTRUCCIONES],
            $libro->getSheetNames()
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
            ->assertSee('Monta el libro del período');

        $this->get(route('planificacion.plantilla', absolute: false))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
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
     |  Ayudas
     ====================================================================== */

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
