<?php

namespace Tests\Feature;

use App\Models\DailyPlan;
use App\Models\EnergyLevel;
use App\Models\GoalType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cumplimiento del horario desde la vista de sólo lectura.
 *
 * El día se abre en /diario/{id} con las franjas en modo lectura, pero las
 * casillas del horario quedan activas: el botón del final de la sección
 * confirma con bootbox y manda todas las franjas a diario.horario, que guarda
 * el estado y devuelve el avance del día. Aquí se prueban la vista, el
 * guardado, el aislamiento entre diarios y la validación.
 */
class CumplimientoHorarioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    /* ======================================================================
     |  La vista
     ====================================================================== */

    public function test_la_vista_de_solo_lectura_permite_marcar_el_horario(): void
    {
        $plan = $this->diario('2026-10-20');

        $html = $this->get(route('diario.show', $plan))
            ->assertOk()
            ->getContent();

        // La cabecera puede marcar todas y el botón queda al final de la
        // sección, apuntando a la acción del controlador.
        $this->assertStringContainsString('id="horario-todos"', $html);
        $this->assertStringContainsString('id="horario-guardar"', $html);
        $this->assertStringContainsString('Guardar cumplimiento', $html);
        $this->assertStringContainsString(route('diario.horario', $plan), $html);

        // La casilla de la franja ya no nace deshabilitada en modo ver.
        $this->assertStringNotContainsString('aria-label="Franja cumplida" disabled', $html);
    }

    public function test_la_vista_de_modificar_no_lleva_el_boton_del_horario(): void
    {
        $plan = $this->diario('2026-10-20');

        $this->get(route('diario.edit', $plan))
            ->assertOk()
            ->assertSee('id="horario-todos"', false)
            ->assertDontSee('id="horario-guardar"', false);
    }

    /* ======================================================================
     |  Guardado por AJAX
     ====================================================================== */

    public function test_marcar_las_franjas_guarda_el_cumplimiento(): void
    {
        $plan = $this->diario('2026-10-20');
        $franjas = $plan->scheduleEntries()->orderBy('id')->take(3)->get()->values();

        $respuesta = $this->patchJson(route('diario.horario', $plan), [
            'franjas' => [
                ['id' => $franjas[0]->id, 'is_done' => 1],
                ['id' => $franjas[1]->id, 'is_done' => 1],
                // La tercera se queda como estaba: no debe contar como cambio.
                ['id' => $franjas[2]->id, 'is_done' => 0],
            ],
        ]);

        $respuesta->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.updated', 2)
            ->assertJsonPath('data.done', 2)
            ->assertJsonPath('data.total', $plan->scheduleEntries()->count());

        $this->assertDatabaseHas('schedule_entries', ['id' => $franjas[0]->id, 'is_done' => true]);
        $this->assertDatabaseHas('schedule_entries', ['id' => $franjas[1]->id, 'is_done' => true]);
        $this->assertDatabaseHas('schedule_entries', ['id' => $franjas[2]->id, 'is_done' => false]);

        $this->assertStringContainsString('2 de 15', $respuesta->json('message'));
    }

    public function test_marcar_todas_las_franjas_las_deja_cumplidas(): void
    {
        $plan = $this->diario('2026-10-20');
        $franjas = $plan->scheduleEntries()->orderBy('id')->get();

        $respuesta = $this->patchJson(route('diario.horario', $plan), [
            'franjas' => $franjas
                ->map(fn ($franja) => ['id' => $franja->id, 'is_done' => 1])
                ->all(),
        ]);

        $respuesta->assertOk()
            ->assertJsonPath('data.updated', $franjas->count())
            ->assertJsonPath('data.done', $franjas->count())
            ->assertJsonPath('data.total', $franjas->count());

        $this->assertSame(
            $franjas->count(),
            $plan->scheduleEntries()->where('is_done', true)->count()
        );
    }

    public function test_si_nada_cambio_lo_indica_en_el_mensaje(): void
    {
        $plan = $this->diario('2026-10-20');
        $franjas = $plan->scheduleEntries()->orderBy('id')->take(2)->get()->values();

        $respuesta = $this->patchJson(route('diario.horario', $plan), [
            'franjas' => $franjas
                ->map(fn ($franja) => ['id' => $franja->id, 'is_done' => 0])
                ->all(),
        ]);

        $respuesta->assertOk()
            ->assertJsonPath('data.updated', 0)
            ->assertJsonPath('data.done', 0);

        $this->assertStringContainsString('no hubo cambios', $respuesta->json('message'));
    }

    public function test_volver_a_desmarcar_una_franja_la_deja_pendiente(): void
    {
        $plan = $this->diario('2026-10-20');
        $franja = $plan->scheduleEntries()->orderBy('id')->firstOrFail();

        $this->patchJson(route('diario.horario', $plan), [
            'franjas' => [['id' => $franja->id, 'is_done' => 1]],
        ])->assertOk();

        $this->patchJson(route('diario.horario', $plan), [
            'franjas' => [['id' => $franja->id, 'is_done' => 0]],
        ])->assertOk()->assertJsonPath('data.done', 0);

        $this->assertDatabaseHas('schedule_entries', ['id' => $franja->id, 'is_done' => false]);
    }

    /* ======================================================================
     |  Seguridad y validación
     ====================================================================== */

    public function test_una_franja_de_otro_diario_no_se_puede_marcar(): void
    {
        $plan = $this->diario('2026-10-20');
        $otro = $this->diario('2026-10-21');
        $ajena = $otro->scheduleEntries()->orderBy('id')->firstOrFail();

        $this->patchJson(route('diario.horario', $plan), [
            'franjas' => [['id' => $ajena->id, 'is_done' => 1]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('franjas.0.id');

        $this->assertDatabaseHas('schedule_entries', ['id' => $ajena->id, 'is_done' => false]);
    }

    public function test_un_diario_inexistente_responde_404(): void
    {
        $this->patchJson(route('diario.horario', 999999), [
            'franjas' => [['id' => 1, 'is_done' => 1]],
        ])->assertNotFound()
            ->assertJsonPath('ok', false);
    }

    public function test_el_estado_de_cumplimiento_tiene_que_ser_booleano(): void
    {
        $plan = $this->diario('2026-10-20');
        $franja = $plan->scheduleEntries()->orderBy('id')->firstOrFail();

        $this->patchJson(route('diario.horario', $plan), [
            'franjas' => [['id' => $franja->id, 'is_done' => 'quizá']],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('franjas.0.is_done');
    }

    public function test_la_lista_de_franjas_es_obligatoria(): void
    {
        $plan = $this->diario('2026-10-20');

        $this->patchJson(route('diario.horario', $plan), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('franjas');
    }

    /* ======================================================================
     |  Auxiliares
     ====================================================================== */

    /** Crea un día completo con sus 15 franjas generadas del catálogo. */
    private function diario(string $fecha): DailyPlan
    {
        $tipos = GoalType::query()->orderBy('sort_order')->get()->values();

        $objetivos = [];

        foreach ([1, 2, 3] as $ranura) {
            $objetivos[] = [
                'slot' => $ranura,
                'goal_type_id' => $tipos[$ranura - 1]->id,
                'description' => 'Objetivo '.$ranura,
            ];
        }

        return DailyPlan::createDay([
            'plan_date' => $fecha,
            'energy_level_id' => EnergyLevel::query()->firstOrFail()->id,
            'goals' => $objetivos,
            'achievements' => 'Lo logrado',
            'pending' => 'Lo pendiente',
            'pending_when' => 'Mañana',
            'proud_of' => 'Mi esfuerzo',
        ]);
    }
}
