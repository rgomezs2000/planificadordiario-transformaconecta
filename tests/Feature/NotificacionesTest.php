<?php

namespace Tests\Feature;

use App\Models\DailyActivity;
use App\Models\DailyPlan;
use App\Models\EnergyLevel;
use App\Models\GoalType;
use App\Models\Notificacion;
use App\Notificaciones\BuzonDual;
use App\Notificaciones\Candidata;
use App\Notificaciones\GeneradorDeNotificaciones;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reglas del recordatorio diario y buzón MySQL.
 *
 * Firestore se apaga en estas pruebas (FIREBASE_HABILITADO=false): lo que se
 * comprueba es el cálculo de las reglas, la idempotencia del buzón y las rutas
 * que usa la pantalla. El espejo NoSQL tiene su propio archivo.
 */
class NotificacionesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        config([
            'notificaciones.habilitadas' => true,
            'notificaciones.margen_minutos' => 10,
            'notificaciones.avisar_diario_ausente' => true,
            'notificaciones.hora_diario_ausente' => '08:00',
            // Las pruebas de objetivos y franjas cuentan avisos exactos; la
            // regla de actividades tiene sus propias pruebas más abajo.
            'notificaciones.avisar_actividades' => false,
            'notificaciones.segundos_entre_generaciones' => 55,
            'firebase.habilitado' => false,
        ]);
    }

    /* ======================================================================
     |  Reglas
     ====================================================================== */

    public function test_solo_notifica_los_objetivos_y_franjas_sin_marcar(): void
    {
        $this->travelTo(Carbon::parse('2026-10-20 10:30:00'));

        $plan = $this->planDeHoy([1], [
            '08:00:00' => ['is_done' => false],
            '09:00:00' => ['is_done' => true],
            '11:00:00' => ['is_done' => false],
        ]);

        $candidatas = app(GeneradorDeNotificaciones::class)->generar();
        $claves = collect($candidatas)->pluck('clave')->all();

        $franja = $plan->scheduleEntries()->where('start_time', '08:00:00')->firstOrFail();

        $this->assertCount(3, $candidatas);
        $this->assertContains('objetivo:'.$plan->id.':2:2026-10-20', $claves);
        $this->assertContains('objetivo:'.$plan->id.':3:2026-10-20', $claves);
        $this->assertContains('franja:'.$franja->id.':2026-10-20', $claves);
        $this->assertNotContains('objetivo:'.$plan->id.':1:2026-10-20', $claves);
    }

    public function test_no_notifica_nada_si_todo_esta_marcado(): void
    {
        $this->travelTo(Carbon::parse('2026-10-20 22:00:00'));

        $this->planDeHoy([1, 2, 3], [
            '08:00:00' => ['is_done' => true],
            '12:00:00' => ['is_done' => true],
        ]);

        $this->assertSame([], app(GeneradorDeNotificaciones::class)->generar());
    }

    public function test_una_franja_no_se_avisa_antes_de_su_hora_mas_el_margen(): void
    {
        $this->travelTo(Carbon::parse('2026-10-20 08:05:00'));

        $this->planDeHoy([1, 2, 3], ['08:00:00' => ['is_done' => false]]);

        $this->assertSame([], app(GeneradorDeNotificaciones::class)->generar());

        $this->travelTo(Carbon::parse('2026-10-20 08:11:00'));

        $candidatas = app(GeneradorDeNotificaciones::class)->generar();
        $this->assertCount(1, $candidatas);
        $this->assertSame(Candidata::TIPO_FRANJA, $candidatas[0]->tipo);
    }

    public function test_avisa_que_falta_el_diario_solo_despues_de_la_hora_configurada(): void
    {
        $this->travelTo(Carbon::parse('2026-10-20 07:00:00'));
        $this->assertSame([], app(GeneradorDeNotificaciones::class)->generar());

        $this->travelTo(Carbon::parse('2026-10-20 09:00:00'));
        $candidatas = app(GeneradorDeNotificaciones::class)->generar();

        $this->assertCount(1, $candidatas);
        $this->assertSame(Candidata::TIPO_DIARIO_AUSENTE, $candidatas[0]->tipo);
        $this->assertSame('diario-ausente:2026-10-20', $candidatas[0]->clave);
    }

    public function test_puede_apagarse_el_aviso_de_diario_ausente(): void
    {
        config(['notificaciones.avisar_diario_ausente' => false]);
        $this->travelTo(Carbon::parse('2026-10-20 09:00:00'));

        $this->assertSame([], app(GeneradorDeNotificaciones::class)->generar());
    }

    public function test_pregunta_por_las_actividades_que_siguen_sin_marcarse(): void
    {
        config(['notificaciones.avisar_actividades' => true]);
        $this->travelTo(Carbon::parse('2026-10-20 22:00:00'));

        $plan = $this->planDeHoy([1, 2, 3], ['08:00:00' => ['is_done' => true]]);
        $meditacion = DailyActivity::query()->where('clave', 'meditacion')->firstOrFail();
        $caminata = DailyActivity::query()->where('clave', 'caminata')->firstOrFail();

        $plan->activities()->updateExistingPivot($meditacion->id, ['is_done' => true, 'minutes' => 15]);

        $candidatas = app(GeneradorDeNotificaciones::class)->generar();
        $claves = collect($candidatas)->pluck('clave')->all();

        $this->assertContains('actividad:'.$plan->id.':'.$caminata->id.':2026-10-20', $claves);
        $this->assertNotContains('actividad:'.$plan->id.':'.$meditacion->id.':2026-10-20', $claves);
        $this->assertSame(
            Candidata::TIPO_ACTIVIDAD,
            collect($candidatas)->firstWhere('clave', 'actividad:'.$plan->id.':'.$caminata->id.':2026-10-20')->tipo
        );
    }

    public function test_recuerda_los_temas_del_dia_en_whatsapp(): void
    {
        config(['notificaciones.avisar_actividades' => true]);
        $this->travelTo(Carbon::parse('2026-10-20 11:30:00'));

        $plan = $this->planDeHoy([1, 2, 3], []);
        $whatsapp = DailyActivity::query()->where('clave', 'temas_whatsapp')->firstOrFail();

        $candidatas = app(GeneradorDeNotificaciones::class)->generar();
        $claves = collect($candidatas)->pluck('clave')->all();

        $this->assertContains('actividad:'.$plan->id.':'.$whatsapp->id.':2026-10-20', $claves);

        $aviso = collect($candidatas)
            ->firstWhere('clave', 'actividad:'.$plan->id.':'.$whatsapp->id.':2026-10-20');

        $this->assertSame(Candidata::TIPO_ACTIVIDAD, $aviso->tipo);
        $this->assertStringContainsString('WhatsApp', $aviso->titulo);
    }

    public function test_una_actividad_no_se_avisa_antes_de_su_hora(): void
    {
        config(['notificaciones.avisar_actividades' => true]);
        $this->travelTo(Carbon::parse('2026-10-20 08:00:00'));

        $this->planDeHoy([1, 2, 3], []);

        // A las 8:00 ninguna actividad llegó a su hora de recordatorio.
        $this->assertSame([], app(GeneradorDeNotificaciones::class)->generar());
    }

    public function test_al_marcar_la_actividad_se_resuelve_su_aviso(): void
    {
        config(['notificaciones.avisar_actividades' => true]);
        $this->travelTo(Carbon::parse('2026-10-20 22:00:00'));

        $plan = $this->planDeHoy([1, 2, 3], []);
        $actividad = DailyActivity::query()->where('clave', 'meditacion')->firstOrFail();

        $buzon = app(BuzonDual::class);
        $buzon->sincronizar();

        $aviso = Notificacion::query()
            ->where('daily_activity_id', $actividad->id)
            ->firstOrFail();

        $this->assertNull($aviso->leida_en);

        $plan->activities()->updateExistingPivot($actividad->id, ['is_done' => true]);

        $resultado = $buzon->sincronizar();

        $this->assertGreaterThanOrEqual(1, $resultado['resueltas']);
        $this->assertNotNull($aviso->fresh()->leida_en);
    }

    /* ======================================================================
     |  Buzón MySQL
     ====================================================================== */

    public function test_la_sincronizacion_guarda_en_mysql_y_no_duplica(): void
    {
        $this->travelTo(Carbon::parse('2026-10-20 10:30:00'));
        $this->planDeHoy([], ['08:00:00' => ['is_done' => false]]);

        $buzon = app(BuzonDual::class);

        $primera = $buzon->sincronizar();
        $this->assertSame(4, $primera['creadas']);
        $this->assertDatabaseCount('notificaciones', 4);

        $segunda = $buzon->sincronizar();
        $this->assertSame(0, $segunda['creadas']);
        $this->assertSame(4, Notificacion::count());
    }

    public function test_los_recordatorios_de_tareas_ya_cumplidas_se_marcan_leidos(): void
    {
        $this->travelTo(Carbon::parse('2026-10-20 10:30:00'));
        $plan = $this->planDeHoy([], ['08:00:00' => ['is_done' => false]]);

        $buzon = app(BuzonDual::class);
        $this->assertSame(4, $buzon->sincronizar()['creadas']);

        $plan->goals()->update(['is_done' => true]);
        $plan->scheduleEntries()->update(['is_done' => true]);

        $resultado = $buzon->sincronizar();

        $this->assertSame(4, $resultado['resueltas']);
        $this->assertSame(0, Notificacion::query()->whereNull('leida_en')->count());
        $this->assertSame(0, Notificacion::query()->whereNull('leida_en')->whereNull('descartada_en')->count());
    }

    public function test_el_aviso_de_diario_ausente_se_resuelve_al_crear_el_diario(): void
    {
        $this->travelTo(Carbon::parse('2026-10-20 09:00:00'));

        $buzon = app(BuzonDual::class);
        $this->assertSame(1, $buzon->sincronizar()['creadas']);

        $ausente = Notificacion::query()
            ->where('tipo', Candidata::TIPO_DIARIO_AUSENTE)
            ->firstOrFail();

        $this->planDeHoy();
        $resultado = $buzon->sincronizar();

        $this->assertSame(1, $resultado['resueltas']);
        $this->assertNotNull($ausente->fresh()->leida_en);

        // El aviso del diario queda leído; los 3 objetivos recién creados, no.
        $this->assertSame(3, Notificacion::query()->whereNull('leida_en')->count());
    }

    public function test_el_contador_genera_las_reglas_y_devuelve_los_numeros(): void
    {
        $this->travelTo(Carbon::parse('2026-10-20 10:30:00'));
        $this->planDeHoy([1], ['08:00:00' => ['is_done' => false]]);

        $respuesta = $this->getJson(route('notificaciones.contador'))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.no_leidas', 3)
            ->assertJsonPath('data.total', 3);

        $respuesta->assertJsonCount(3, 'data.ultimas');
        $this->assertSame(3, Notificacion::count());

        // La segunda consulta no vuelve a crear las mismas notificaciones.
        $this->getJson(route('notificaciones.contador'))->assertOk();
        $this->assertSame(3, Notificacion::count());
    }

    public function test_la_tabla_devuelve_las_notificaciones_y_sus_filtros(): void
    {
        $this->travelTo(Carbon::parse('2026-10-20 10:30:00'));
        $this->planDeHoy([], ['08:00:00' => ['is_done' => false]]);

        $this->postJson(route('notificaciones.sincronizar'))->assertOk();

        $this->getJson(route('notificaciones.tabla'))
            ->assertOk()
            ->assertJsonCount(4, 'data.notificaciones')
            ->assertJsonPath('data.notificaciones.0.leida', false);

        $this->getJson(route('notificaciones.tabla', ['tipo' => Candidata::TIPO_FRANJA]))
            ->assertOk()
            ->assertJsonCount(1, 'data.notificaciones');

        $this->getJson(route('notificaciones.tabla', ['estado' => 'no_leidas']))
            ->assertOk()
            ->assertJsonCount(4, 'data.notificaciones');
    }

    public function test_marcar_una_notificacion_como_leida_baja_el_contador(): void
    {
        $this->travelTo(Carbon::parse('2026-10-20 10:30:00'));
        $this->planDeHoy([], ['08:00:00' => ['is_done' => false]]);
        $this->postJson(route('notificaciones.sincronizar'))->assertOk();

        $notificacion = Notificacion::query()->firstOrFail();

        $this->patchJson(route('notificaciones.leida', $notificacion->uuid))
            ->assertOk()
            ->assertJsonPath('data.conteo.no_leidas', 3);

        $this->assertNotNull($notificacion->fresh()->leida_en);

        $this->getJson(route('notificaciones.tabla', ['estado' => 'no_leidas']))
            ->assertOk()
            ->assertJsonCount(3, 'data.notificaciones');
    }

    public function test_marcar_un_lote_exige_seleccion_y_actualiza_solo_las_pendientes(): void
    {
        $this->travelTo(Carbon::parse('2026-10-20 10:30:00'));
        $this->planDeHoy([], ['08:00:00' => ['is_done' => false]]);
        $this->postJson(route('notificaciones.sincronizar'))->assertOk();

        $this->patchJson(route('notificaciones.lote.leidas'), ['uuids' => []])
            ->assertStatus(422)
            ->assertJsonPath('ok', false);

        $uuids = Notificacion::query()->limit(2)->pluck('uuid')->all();

        $this->patchJson(route('notificaciones.lote.leidas'), ['uuids' => $uuids])
            ->assertOk()
            ->assertJsonPath('data.actualizadas', 2)
            ->assertJsonPath('data.conteo.no_leidas', 2);
    }

    public function test_archivar_saca_la_notificacion_de_la_bandeja_sin_borrar_la_fila(): void
    {
        $this->travelTo(Carbon::parse('2026-10-20 10:30:00'));
        $this->planDeHoy([], ['08:00:00' => ['is_done' => false]]);
        $this->postJson(route('notificaciones.sincronizar'))->assertOk();

        $notificacion = Notificacion::query()->firstOrFail();

        $this->deleteJson(route('notificaciones.descartar', $notificacion->uuid))
            ->assertOk()
            ->assertJsonPath('data.conteo.total', 3);

        $this->assertNotNull($notificacion->fresh()->descartada_en);
        $this->assertDatabaseCount('notificaciones', 4);

        $this->getJson(route('notificaciones.tabla'))
            ->assertOk()
            ->assertJsonCount(3, 'data.notificaciones');
    }

    public function test_la_pagina_del_buzon_se_dibuja_y_no_separa_por_motor(): void
    {
        $this->get(route('notificaciones.index'))
            ->assertOk()
            ->assertSee('Bandeja de notificaciones')
            ->assertSee('campanaNotificaciones', false)
            ->assertDontSee('Bandeja Firestore')
            ->assertDontSee('data-url-firestore', false);
    }

    public function test_una_notificacion_inexistente_responde_404(): void
    {
        $uuid = '00000000-0000-4000-8000-000000000000';

        $this->getJson(route('notificaciones.mostrar', $uuid))->assertNotFound();
        $this->patchJson(route('notificaciones.leida', $uuid))->assertNotFound();
    }

    /* ======================================================================
     |  Auxiliares
     ====================================================================== */

    /**
     * Crea el diario de hoy con los tres objetivos y marca las franjas
     * indicadas. $hechos son las ranuras (1, 2, 3) ya cumplidas; $franjas es
     * ['08:00:00' => ['is_done' => true|false, 'activity' => ...]].
     */
    private function planDeHoy(array $hechos = [], array $franjas = []): DailyPlan
    {
        $tipos = GoalType::query()->orderBy('sort_order')->get()->values();

        $objetivos = [];

        foreach ([1, 2, 3] as $ranura) {
            $objetivos[] = [
                'slot' => $ranura,
                'goal_type_id' => $tipos[$ranura - 1]->id,
                'description' => 'Objetivo '.$ranura,
                'is_done' => in_array($ranura, $hechos, true),
            ];
        }

        $plan = DailyPlan::createDay([
            'plan_date' => Carbon::today()->toDateString(),
            'energy_level_id' => EnergyLevel::query()->firstOrFail()->id,
            'goals' => $objetivos,
            'achievements' => 'Lo logrado',
            'pending' => 'Lo pendiente',
            'pending_when' => 'Mañana',
            'proud_of' => 'Mi esfuerzo',
        ]);

        foreach ($franjas as $hora => $franja) {
            $plan->scheduleEntries()
                ->where('start_time', $hora)
                ->update([
                    'activity' => $franja['activity'] ?? 'Actividad de las '.$hora,
                    'is_done' => (bool) ($franja['is_done'] ?? false),
                ]);
        }

        return $plan->fresh();
    }
}
