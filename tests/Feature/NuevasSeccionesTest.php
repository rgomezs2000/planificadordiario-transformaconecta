<?php

namespace Tests\Feature;

use App\Models\DailyActivity;
use App\Models\DailyPlan;
use App\Models\EnergyLevel;
use App\Models\GoalType;
use App\Models\WeeklyGoal;
use App\Models\WeeklyReflection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Las secciones nuevas del diario, de punta a punta.
 *
 * Comprueba que el formulario las muestra, que el guardado las persiste (meta
 * semanal compartida por la semana, actividades con minutos, seguimiento
 * personal y reflexión del domingo) y que los documentos las recogen: el PDF
 * del día, el resumen de desempeño y el reporte en Excel.
 */
class NuevasSeccionesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    /* ======================================================================
     |  Formulario
     ====================================================================== */

    public function test_el_formulario_muestra_las_secciones_nuevas(): void
    {
        $this->get(route('diario.index', absolute: false))
            ->assertOk()
            ->assertSee('Mi meta semanal')
            ->assertSee('Actividades diarias')
            ->assertSee('Mi meta personal')
            ->assertSee('Reflexión de la semana')
            ->assertSee('Tendí mi cama')
            ->assertSee('Temas del día en WhatsApp')
            ->assertSee('Cambié las sábanas');
    }

    public function test_la_meta_semanal_es_obligatoria(): void
    {
        $datos = $this->payload();
        unset($datos['weekly_goal']);

        $this->postJson(route('diario.store', absolute: false), $datos)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['weekly_goal']);
    }

    public function test_el_endpoint_de_meta_semanal_devuelve_la_meta_y_la_reflexion(): void
    {
        $this->postJson(route('diario.store', absolute: false), $this->payload())->assertCreated();

        $this->getJson(route('diario.meta-semanal', ['fecha' => '2026-10-25'], absolute: false))
            ->assertOk()
            ->assertJsonPath('data.goal', 'Ordenar mis mañanas')
            ->assertJsonPath('data.why_important', 'Porque me da calma')
            ->assertJsonPath('data.reflection.answers.what_learned', 'Aprendí a pedir ayuda');

        // La misma semana, otro día: la meta también está.
        $this->getJson(route('diario.meta-semanal', ['fecha' => '2026-10-24'], absolute: false))
            ->assertOk()
            ->assertJsonPath('data.goal', 'Ordenar mis mañanas');
    }

    /* ======================================================================
     |  Guardado
     ====================================================================== */

    public function test_guarda_meta_semanal_actividades_y_reflexion_del_domingo(): void
    {
        $caminata = DailyActivity::query()->where('clave', 'caminata')->firstOrFail();
        $meditacion = DailyActivity::query()->where('clave', 'meditacion')->firstOrFail();

        $datos = $this->payload();
        $datos['activities'] = [
            ['daily_activity_id' => $caminata->id, 'is_done' => 1, 'minutes' => 35],
            ['daily_activity_id' => $meditacion->id, 'is_done' => 0],
        ];

        $respuesta = $this->postJson(route('diario.store', absolute: false), $datos)->assertCreated();

        $plan = DailyPlan::query()->firstOrFail();

        $this->assertNotNull($plan->weekly_goal_id);
        $this->assertSame('Ordenar mis mañanas', $plan->weeklyGoal->goal);
        $this->assertSame('Porque me da calma', $plan->weeklyGoal->why_important);
        $this->assertSame('Escribí la primera acción', $plan->personal_done_today);

        $this->assertSame(35, (int) $plan->activities()->where('daily_activities.id', $caminata->id)->first()->pivot->minutes);
        $this->assertFalse((bool) $plan->activities()->where('daily_activities.id', $meditacion->id)->first()->pivot->is_done);

        // El domingo guarda las seis respuestas de la reflexión semanal.
        $reflexion = WeeklyReflection::query()->firstOrFail();
        $this->assertSame('Fue una buena semana', $reflexion->how_was_week);
        $this->assertSame('Aprendí a pedir ayuda', $reflexion->what_learned);

        $this->assertSame(1, WeeklyGoal::query()->count());
        $respuesta->assertJsonPath('ok', true);
        $this->assertArrayHasKey('weekly_goal', $respuesta->json('data.plan'));
        $this->assertArrayHasKey('activities', $respuesta->json('data.plan'));
        $this->assertArrayHasKey('weekly_reflection', $respuesta->json('data.plan'));
    }

    public function test_el_sabado_trae_sus_actividades_y_los_demas_dias_no(): void
    {
        $this->postJson(route('diario.store', absolute: false), $this->payload([
            'plan_date' => '2026-10-24', // sábado
        ]))->assertCreated();

        $sabado = DailyPlan::query()->firstOrFail();
        $claves = $sabado->activities()->pluck('clave')->all();

        $this->assertContains('cambio_sabanas', $claves);
        $this->assertContains('cambio_toallas', $claves);
        $this->assertContains('espacio_limpio', $claves);

        // El domingo siguiente no las trae.
        $this->postJson(route('diario.store', absolute: false), $this->payload([
            'plan_date' => '2026-10-25',
        ]))->assertCreated();

        $domingo = DailyPlan::query()->whereDate('plan_date', '2026-10-25')->firstOrFail();
        $this->assertNotContains('cambio_sabanas', $domingo->activities()->pluck('clave')->all());
    }

    public function test_la_meta_de_la_semana_se_comparte_entre_los_dias(): void
    {
        $this->postJson(route('diario.store', absolute: false), $this->payload([
            'plan_date' => '2026-10-24', // sábado
        ]))->assertCreated();

        $this->postJson(route('diario.store', absolute: false), $this->payload([
            'plan_date' => '2026-10-25', // domingo de la misma semana
        ]))->assertCreated();

        $planes = DailyPlan::query()->orderBy('plan_date')->get();

        $this->assertCount(2, $planes);
        $this->assertSame(1, WeeklyGoal::query()->count());
        $this->assertSame($planes[0]->weekly_goal_id, $planes[1]->weekly_goal_id);

        // El segundo día trae la meta ya cargada en el formulario.
        $this->get(route('diario.edit', $planes[1], absolute: false))
            ->assertOk()
            ->assertSee('Ordenar mis mañanas');
    }

    public function test_la_meta_semanal_no_se_repite_y_solo_cambia_si_se_pide(): void
    {
        // Lunes y martes de la misma semana, con metas distintas: manda la primera.
        $this->postJson(route('diario.store', absolute: false), $this->payload([
            'plan_date' => '2026-10-26',
            'weekly_goal' => ['goal' => 'Meta del lunes', 'why_important' => 'Porque sí'],
        ]))->assertCreated();

        $this->postJson(route('diario.store', absolute: false), $this->payload([
            'plan_date' => '2026-10-27',
            'weekly_goal' => ['goal' => 'Meta del martes', 'why_important' => 'Otra razón'],
        ]))->assertCreated();

        $lunes = DailyPlan::query()->whereDate('plan_date', '2026-10-26')->firstOrFail();
        $martes = DailyPlan::query()->whereDate('plan_date', '2026-10-27')->firstOrFail();

        $this->assertSame(1, WeeklyGoal::query()->count());
        $this->assertSame('Meta del lunes', WeeklyGoal::query()->firstOrFail()->goal);
        $this->assertSame($lunes->weekly_goal_id, $martes->weekly_goal_id);

        // El formulario muestra la meta registrada y bloqueada.
        $html = $this->get(route('diario.edit', $martes, absolute: false))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/id="weekly_goal_goal"[^>]*readonly/s', $html);
        $this->assertStringContainsString('Registrada', $html);
        $this->assertStringContainsString('Cambiar meta de la semana', $html);

        // Sólo el cambio explícito (botón «Cambiar meta») actualiza la semana.
        $this->putJson(route('diario.update', $martes, absolute: false), $this->payload([
            'plan_date' => '2026-10-27',
            'weekly_goal' => [
                'goal' => 'Meta corregida',
                'why_important' => 'Razón corregida',
                'actualizar' => 1,
            ],
        ]))->assertOk();

        $this->assertSame(1, WeeklyGoal::query()->count());
        $this->assertSame('Meta corregida', WeeklyGoal::query()->firstOrFail()->goal);
        $this->assertSame('Meta corregida', $lunes->fresh()->weeklyGoal->goal);
    }

    public function test_una_semana_nueva_registra_una_meta_nueva(): void
    {
        $this->postJson(route('diario.store', absolute: false), $this->payload([
            'plan_date' => '2026-10-26',
            'weekly_goal' => ['goal' => 'Meta de octubre', 'why_important' => 'Razón de octubre'],
        ]))->assertCreated();

        $this->postJson(route('diario.store', absolute: false), $this->payload([
            'plan_date' => '2026-11-02',
            'weekly_goal' => ['goal' => 'Meta de noviembre', 'why_important' => 'Razón de noviembre'],
        ]))->assertCreated();

        $octubre = DailyPlan::query()->whereDate('plan_date', '2026-10-26')->firstOrFail();
        $noviembre = DailyPlan::query()->whereDate('plan_date', '2026-11-02')->firstOrFail();

        $this->assertSame(2, WeeklyGoal::query()->count());
        $this->assertSame('Meta de octubre', $octubre->weeklyGoal->goal);
        $this->assertSame('Meta de noviembre', $noviembre->weeklyGoal->goal);
        $this->assertNotSame($octubre->weekly_goal_id, $noviembre->weekly_goal_id);
    }

    public function test_la_vista_de_solo_lectura_guarda_las_actividades(): void
    {
        $this->postJson(route('diario.store', absolute: false), $this->payload())->assertCreated();
        $plan = DailyPlan::query()->firstOrFail();
        $caminata = DailyActivity::query()->where('clave', 'caminata')->firstOrFail();
        $sabanas = DailyActivity::query()->where('clave', 'cambio_sabanas')->firstOrFail();

        $respuesta = $this->patchJson(route('diario.actividades', $plan, absolute: false), [
            'actividades' => [
                ['daily_activity_id' => $caminata->id, 'is_done' => 1, 'minutes' => 45],
                // Una actividad de sábado no se puede marcar un domingo.
                ['daily_activity_id' => $sabanas->id, 'is_done' => 1],
            ],
        ]);

        $respuesta->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.updated', 1)
            ->assertJsonPath('data.done', 1);

        $caminataMarcada = $plan->activities()->where('daily_activities.id', $caminata->id)->firstOrFail();
        $this->assertTrue((bool) $caminataMarcada->pivot->is_done);
        $this->assertSame(45, (int) $caminataMarcada->pivot->minutes);

        $this->assertNull(
            $plan->activities()->where('daily_activities.id', $sabanas->id)->first()
        );
    }

    public function test_las_actividades_del_sabado_se_guardan_como_las_demas(): void
    {
        $this->postJson(route('diario.store', absolute: false), $this->payload([
            'plan_date' => '2026-10-24', // sábado
        ]))->assertCreated();

        $plan = DailyPlan::query()->firstOrFail();
        $sabanas = DailyActivity::query()->where('clave', 'cambio_sabanas')->firstOrFail();
        $caminata = DailyActivity::query()->where('clave', 'caminata')->firstOrFail();

        $this->patchJson(route('diario.actividades', $plan, absolute: false), [
            'actividades' => [
                ['daily_activity_id' => $sabanas->id, 'is_done' => 1],
                ['daily_activity_id' => $caminata->id, 'is_done' => 1, 'minutes' => 20],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.updated', 2)
            ->assertJsonPath('data.done', 2);

        $this->assertTrue((bool) $plan->activities()
            ->where('daily_activities.id', $sabanas->id)
            ->firstOrFail()->pivot->is_done);
    }

    /* ======================================================================
     |  Listado en vivo
     ====================================================================== */

    public function test_la_huella_del_listado_cambia_al_crear_un_diario(): void
    {
        $antes = $this->getJson(route('diario.huella', absolute: false))
            ->assertOk()
            ->json('data.huella');

        $this->postJson(route('diario.store', absolute: false), $this->payload())->assertCreated();

        $despues = $this->getJson(route('diario.huella', absolute: false))
            ->assertOk()
            ->json('data.huella');

        $this->assertNotSame($antes, $despues);

        // La tabla y la huella liviana comparten el mismo valor.
        $this->assertSame(
            $despues,
            $this->getJson(route('diario.tabla', absolute: false))->assertOk()->json('data.huella')
        );
    }

    /* ======================================================================
     |  Análisis
     ====================================================================== */

    public function test_el_analisis_cruza_actividades_metas_y_senales_entre_dias(): void
    {
        $caminata = DailyActivity::query()->where('clave', 'caminata')->firstOrFail();

        // Sábado y domingo de la misma semana; lunes de la siguiente.
        $this->postJson(route('diario.store', absolute: false), $this->payload([
            'plan_date' => '2026-10-24',
            'activities' => [['daily_activity_id' => $caminata->id, 'is_done' => 1, 'minutes' => 40]],
        ]))->assertCreated();

        $this->postJson(route('diario.store', absolute: false), $this->payload([
            'plan_date' => '2026-10-25',
            'activities' => [['daily_activity_id' => $caminata->id, 'is_done' => 0]],
        ]))->assertCreated();

        $this->postJson(route('diario.store', absolute: false), $this->payload([
            'plan_date' => '2026-10-26',
            'weekly_goal' => ['goal' => 'Segunda semana de prueba', 'why_important' => 'Para sostener'],
            'activities' => [['daily_activity_id' => $caminata->id, 'is_done' => 1, 'minutes' => 20]],
        ]))->assertCreated();

        // Algunas señales para que el cruce tenga con qué comparar.
        $sabado = DailyPlan::query()->whereDate('plan_date', '2026-10-24')->firstOrFail();
        $sabado->reflectionAnswers()->update(['is_checked' => true]);

        $analisis = \App\Reportes\AnalisisDeDesempeno::de(DailyPlan::query()->get());

        $this->assertSame(3, $analisis['dias']);
        $this->assertCount(3, $analisis['detalle']);
        $this->assertNotEmpty($analisis['actividadesDiarias']);
        $this->assertSame(3, $analisis['correlacion']['n']);
        $this->assertCount(2, $analisis['metasSemanales']);
        $this->assertCount(1, $analisis['reflexionesSemanales']);
        $this->assertNotEmpty($analisis['impactoActividades']);
        $this->assertArrayHasKey('con_senales', $analisis['procrastinacionVsActividades']);
        $this->assertGreaterThan(0, $analisis['global']['actividades_total']);
    }

    /* ======================================================================
     |  Documentos
     ====================================================================== */

    public function test_el_pdf_del_dia_incluye_las_secciones_nuevas(): void
    {
        $this->postJson(route('diario.store', absolute: false), $this->payload())->assertCreated();
        $plan = DailyPlan::query()->firstOrFail();

        $respuesta = $this->get(route('diario.print', $plan, absolute: false));

        $respuesta->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $archivo = $respuesta->baseResponse->getFile()->getPathname();
        $this->assertFileExists($archivo);
        $this->assertStringStartsWith('%PDF', (string) file_get_contents($archivo));

        // La vista que arma el PDF muestra las secciones nuevas.
        $html = view('diario.pdf', [
            'plan' => $plan->loadFull(),
            'marca' => false,
            'logo' => null,
            'reflexionSemanal' => $plan->reflexionSemanal(),
            'grafico' => '',
        ])->render();

        $this->assertStringContainsString('MI META SEMANAL', $html);
        $this->assertStringContainsString('ACTIVIDADES DIARIAS', $html);
        $this->assertStringContainsString('Temas del día en WhatsApp', $html);
        $this->assertStringContainsString('MI META PERSONAL', $html);
        $this->assertStringContainsString('REFLEXIÓN DE LA SEMANA', $html);
        $this->assertStringContainsString('MI RUTINA ME FORTALECE', $html);
        $this->assertStringContainsString('LO IMPORTANTE NO ES EL PENSAMIENTO', $html);
    }

    public function test_el_resumen_incluye_dictamen_y_recomendaciones(): void
    {
        $this->postJson(route('diario.store', absolute: false), $this->payload())->assertCreated();

        $respuesta = $this->get(route('diario.resumen', ['fecha' => '2026-10-25'], absolute: false));

        $respuesta->assertOk();
        $this->assertStringStartsWith('%PDF', (string) $respuesta->getContent());
    }

    public function test_el_reporte_excel_trae_las_columnas_y_la_hoja_de_analisis(): void
    {
        $this->postJson(route('diario.store', absolute: false), $this->payload())->assertCreated();

        $respuesta = $this->get(route('diario.reporte', ['fecha' => '2026-10-25'], absolute: false));

        $respuesta->assertOk();

        $temporal = tempnam(sys_get_temp_dir(), 'reporte').'.xlsx';
        file_put_contents($temporal, $respuesta->getContent());

        try {
            $libro = IOFactory::load($temporal);

            $this->assertContains('Diarios', $libro->getSheetNames());
            $this->assertContains('Resumen', $libro->getSheetNames());

            $hoja = $libro->getSheetByName('Diarios');
            $this->assertSame('Meta semanal', $hoja->getCell('D4')->getValue());
            $this->assertSame('Actividades diarias', $hoja->getCell('W4')->getValue());
            $this->assertSame('Reflexión semanal', $hoja->getCell('AC4')->getValue());

            // Una fila de datos y el texto de la meta en la celda D5.
            $this->assertSame('Ordenar mis mañanas', $hoja->getCell('D5')->getValue());

            // Las actividades van en el mismo orden del catálogo, con WhatsApp
            // entre los audios y la alimentación.
            $actividades = (string) $hoja->getCell('W5')->getValue();
            $this->assertStringContainsString('Temas del día en WhatsApp', $actividades);
            $this->assertLessThan(
                mb_strpos($actividades, 'Alimentación saludable'),
                mb_strpos($actividades, 'Temas del día en WhatsApp')
            );

            $resumen = $libro->getSheetByName('Resumen');
            $this->assertSame('MI PLANIFICADOR DIARIO · ANÁLISIS DEL PERÍODO', $resumen->getCell('A1')->getValue());
        } finally {
            @unlink($temporal);
        }
    }

    /* ======================================================================
     |  Auxiliares
     ====================================================================== */

    /** Un día completo, con las secciones nuevas incluidas. */
    private function payload(array $sobreescribir = []): array
    {
        $tipos = GoalType::query()->orderBy('sort_order')->get()->values();
        $caminata = DailyActivity::query()->where('clave', 'caminata')->firstOrFail();

        $datos = [
            'plan_date' => '2026-10-25', // domingo
            'energy_level_id' => EnergyLevel::query()->firstOrFail()->id,
            'weekly_goal' => [
                'goal' => 'Ordenar mis mañanas',
                'why_important' => 'Porque me da calma',
            ],
            'goals' => collect([1, 2, 3])->map(fn (int $ranura) => [
                'slot' => $ranura,
                'goal_type_id' => $tipos[$ranura - 1]->id,
                'description' => 'Objetivo '.$ranura,
                'is_done' => $ranura === 1 ? 1 : 0,
            ])->all(),
            'schedule' => [
                ['start_time' => '08:00', 'activity' => 'Caminata', 'is_done' => 1],
                ['start_time' => '10:00', 'activity' => 'Revisar el correo', 'is_done' => 0],
            ],
            'activities' => [
                ['daily_activity_id' => $caminata->id, 'is_done' => 1, 'minutes' => 30],
            ],
            'personal_done_today' => 'Escribí la primera acción',
            'personal_helped' => 'La lista corta',
            'personal_hard' => 'El teléfono',
            'weekly_reflection' => [
                'how_was_week' => 'Fue una buena semana',
                'what_achieved' => 'Terminé el informe',
                'what_was_hard' => 'Empezar temprano',
                'what_learned' => 'Aprendí a pedir ayuda',
                'how_improved' => 'Sostuve la caminata',
                'what_different' => 'Voy a preparar la ropa la noche anterior',
            ],
            'achievements' => 'Cumplí lo importante',
            'pending' => 'Ordenar el escritorio',
            'pending_when' => 'Mañana',
            'proud_of' => 'Por sostener la caminata',
            'notes' => [
                ['content' => 'Nota del día'],
            ],
        ];

        return array_replace_recursive($datos, $sobreescribir);
    }
}
