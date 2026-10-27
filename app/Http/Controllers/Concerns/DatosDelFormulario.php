<?php

namespace App\Http\Controllers\Concerns;

use App\Graficos\AgendaDelDia;
use App\Helpers\Helper;
use App\Models\ActionBlockDuration;
use App\Models\ActionBlockOutcome;
use App\Models\DailyActivity;
use App\Models\DailyPlan;
use App\Models\EnergyLevel;
use App\Models\GoalType;
use App\Models\PreparationItem;
use App\Models\ReflectionQuestion;
use App\Models\ScheduleEntry;
use App\Models\WeeklyGoal;
use App\Models\WeeklyReflection;

/**
 * Datos que necesita la vista del formulario del diario.
 *
 * El mismo formulario sirve para crear, ver (sólo lectura) y modificar, así que
 * los tres modos comparten estos datos y sólo cambian el modo, la acción del
 * formulario y si la fecha está bloqueada.
 */
trait DatosDelFormulario
{
    /** Catálogos que alimentan el formulario. */
    protected function catalogs(): array
    {
        return [
            'energyLevels' => EnergyLevel::active()->get(),
            'goalTypes' => GoalType::active()->get(),
            'preparationItems' => PreparationItem::active()->get(),
            'reflectionQuestions' => ReflectionQuestion::active()
                ->category(ReflectionQuestion::CATEGORY_PROCRASTINATION)
                ->get(),
            'actionBlockDurations' => ActionBlockDuration::active()->get(),
            'actionBlockOutcomes' => ActionBlockOutcome::active()->get(),
            'dailyActivities' => DailyActivity::active()->get(),
            'weekDays' => $this->weekDays(),
        ];
    }

    /**
     * Los días de la semana tal como se marcan en el formulario: L M M J V S D.
     * El valor es el mismo que devuelve Date.getDay() en JavaScript
     * (0 = domingo), que es lo que usa public/js/script.js para marcar el día.
     */
    protected function weekDays(): array
    {
        return collect([1, 2, 3, 4, 5, 6, 0])
            ->map(fn (int $day) => [
                'value' => $day,
                'label' => Helper::DAYS_LETTER[$day],
            ])
            ->all();
    }

    /**
     * Arma todo lo que la vista del formulario necesita.
     *
     * @param  DailyPlan|null  $plan  El día ya guardado (ver y modificar).
     * @param  string  $modo  'crear', 'ver' o 'editar'.
     * @param  string|null  $planDate  Fecha para el modo crear.
     * @param  bool  $dateLocked  Fecha de sólo lectura (ruta /diario/today).
     */
    protected function datosFormulario(
        ?DailyPlan $plan = null,
        string $modo = 'crear',
        ?string $planDate = null,
        bool $dateLocked = false
    ): array {
        // La fecha con la que se abre el formulario: la del día guardado o la
        // que traiga la ruta. Hoy, si no hay ninguna, para precargar la meta de
        // la semana en curso.
        $fechaBase = $plan?->plan_date ?? Helper::toCarbon($planDate) ?? Helper::today();
        $diaSemana = $fechaBase ? (int) $fechaBase->dayOfWeek : (int) now()->dayOfWeek;

        return array_merge($this->catalogs(), [
            'plan' => $plan,
            'modo' => $modo,
            'planDate' => $plan?->plan_date?->format('Y-m-d') ?? $planDate,
            'dateLocked' => $dateLocked || $modo === 'ver',
            'accion' => $modo === 'editar'
                ? route('diario.update', $plan)
                : route('diario.store'),
            'metodo' => $modo === 'editar' ? 'PUT' : 'POST',
            'indiceHorario' => $plan ? $plan->scheduleEntries->count() : 0,
            // Meta de la semana y reflexión de esa misma semana, si ya existen.
            'metaSemanal' => $plan?->weeklyGoal ?? WeeklyGoal::forDate($fechaBase),
            'reflexionSemanal' => WeeklyReflection::forDate($fechaBase),
            'diaSemana' => $diaSemana,
            'esSabado' => $diaSemana === 6,
            'esDomingo' => $diaSemana === 0,
            'diaLetra' => Helper::dayLetter($fechaBase) ?? '',
            'diaNombre' => Helper::dayName($fechaBase, capitalize: true) ?? '',
            // Los tramos del gráfico del día. Es la misma estructura que viaja
            // al guardar, así el gráfico y la base hablan del mismo objeto.
            'grafico' => AgendaDelDia::datos(
                $plan
                    ? $plan->scheduleEntries->map(fn (ScheduleEntry $franja) => [
                        'start_time' => $franja->start_time,
                        'activity' => $franja->activity,
                        'is_done' => (bool) $franja->is_done,
                    ])->all()
                    : []
            ),
        ]);
    }
}
