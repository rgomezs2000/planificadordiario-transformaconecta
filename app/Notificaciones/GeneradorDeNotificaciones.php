<?php

namespace App\Notificaciones;

use App\Helpers\Helper;
use App\Models\DailyPlan;
use App\Models\GoalType;
use App\Models\PlanGoal;
use App\Models\ScheduleEntry;
use Carbon\CarbonImmutable;

/**
 * Las reglas del recordatorio diario.
 *
 * Trabaja siempre sobre el diario del día que está transcurriendo y devuelve
 * las notificaciones que corresponden según cuatro reglas:
 *
 *   1. Si el día tiene diario y alguno de los tres objetivos principales
 *      (Debo hacer, Quiero hacer, Algo para mí) no está marcado, se recuerda
 *      cumplirlo. Los que ya están marcados no se notifican.
 *   2. Si el diario se cargó de antemano y una franja del horario ya pasó su
 *      hora (más el margen de gracia) sin marcarse, se recuerda realizar esa
 *      tarea. Las franjas marcadas no se notifican.
 *   3. Si una actividad diaria (meditación, estudio, caminata…) ya pasó su
 *      hora y sigue sin marcarse, se pregunta si se realizó. Las que ya están
 *      marcadas no se notifican.
 *   4. Si el día no tiene diario cargado, se avisa que falta crearlo (esto se
 *      puede apagar en config/notificaciones.php).
 *
 * El generador no guarda nada: sólo calcula. Guardar y replicar es tarea de
 * BuzonDual, y la clave única de cada candidata evita repetir el aviso.
 */
class GeneradorDeNotificaciones
{
    /** Nombres de las tres ranuras, por si el catálogo no estuviera cargado. */
    public const NOMBRES_RANURA = [
        PlanGoal::SLOT_MUST => 'Debo hacer',
        PlanGoal::SLOT_WANT => 'Quiero hacer',
        PlanGoal::SLOT_FOR_ME => 'Algo para mí',
    ];

    /**
     * Calcula las notificaciones del día.
     *
     * @return array<int, Candidata>
     */
    public function generar(?CarbonImmutable $ahora = null): array
    {
        if (! config('notificaciones.habilitadas', true)) {
            return [];
        }

        $ahora = $ahora?->setTimezone(config('app.timezone')) ?? CarbonImmutable::now(config('app.timezone'));
        $hoy = $ahora->startOfDay();

        $plan = DailyPlan::query()
            ->whereDate('plan_date', $hoy->toDateString())
            ->with(['goals.goalType', 'scheduleEntries', 'activities'])
            ->first();

        if (! $plan) {
            return $this->diarioAusente($ahora, $hoy);
        }

        $nombres = $this->nombresDeRanura();

        return array_merge(
            $this->objetivosPendientes($plan, $nombres, $ahora, $hoy),
            $this->franjasPendientes($plan, $ahora, $hoy),
            $this->actividadesPendientes($plan, $ahora, $hoy),
        );
    }

    /* ======================================================================
     |  Regla 1 · objetivos principales
     ====================================================================== */

    /**
     * Un aviso por cada ranura de objetivo sin marcar. Si la ranura ni
     * siquiera tiene descripción, igual se avisa: el objetivo está pendiente.
     *
     * @param  array<int, string>  $nombres
     * @return array<int, Candidata>
     */
    private function objetivosPendientes(
        DailyPlan $plan,
        array $nombres,
        CarbonImmutable $ahora,
        CarbonImmutable $hoy
    ): array {
        $porRanura = $plan->goals->keyBy('slot');
        $avisos = [];

        foreach (self::NOMBRES_RANURA as $ranura => $nombreDefecto) {
            /** @var PlanGoal|null $objetivo */
            $objetivo = $porRanura->get($ranura);

            if ($objetivo?->is_done) {
                continue;
            }

            $nombre = $objetivo?->goalType?->name ?? $nombres[$ranura] ?? $nombreDefecto;
            $descripcion = Helper::strip($objetivo?->description);

            $mensaje = $descripcion !== ''
                ? 'Tu objetivo «'.$descripcion.'» todavía no está marcado. Márcalo en tu diario cuando lo hayas cumplido.'
                : 'Todavía no registraste este objetivo en tu diario de hoy. Escríbelo y márcalo cuando lo cumplas.';

            $avisos[] = new Candidata(
                clave: 'objetivo:'.$plan->id.':'.$ranura.':'.$hoy->toDateString(),
                tipo: Candidata::TIPO_OBJETIVO,
                titulo: 'Cumple tu objetivo: '.$nombre,
                mensaje: $mensaje,
                fechaHora: $ahora,
                prioridad: $ranura === PlanGoal::SLOT_MUST ? 'alta' : 'media',
                fechaReferencia: $hoy,
                dailyPlanId: $plan->id,
                planGoalId: $objetivo?->id,
                meta: [
                    'ranura' => $ranura,
                    'objetivo_tipo' => $nombre,
                    'descripcion' => $descripcion !== '' ? $descripcion : null,
                    'fecha' => $hoy->toDateString(),
                ],
            );
        }

        return $avisos;
    }

    /* ======================================================================
     |  Regla 2 · franjas horarias
     ====================================================================== */

    /**
     * Un aviso por cada franja con actividad cuya hora ya pasó y sigue sin
     * marcarse. Las franjas vacías (sin actividad) no generan recordatorio:
     * no hay tarea que recordar.
     *
     * @return array<int, Candidata>
     */
    private function franjasPendientes(
        DailyPlan $plan,
        CarbonImmutable $ahora,
        CarbonImmutable $hoy
    ): array {
        $margen = max(0, (int) config('notificaciones.margen_minutos', 10));
        $avisos = [];

        foreach ($plan->scheduleEntries as $franja) {
            if ($franja->is_done) {
                continue;
            }

            $actividad = Helper::strip($franja->activity);

            if ($actividad === '') {
                continue;
            }

            $inicio = $this->inicioDeLaFranja($franja, $hoy);

            if (! $inicio) {
                continue;
            }

            $vence = $inicio->addMinutes($margen);

            // Sólo cuando la hora ya pasó: las franjas futuras no se avisan.
            if ($vence->greaterThan($ahora)) {
                continue;
            }

            $hora = Helper::timeLabel($franja->start_time) ?? $inicio->format('H:i');
            $minutosTarde = (int) $inicio->diffInMinutes($ahora);

            $avisos[] = new Candidata(
                clave: 'franja:'.$franja->id.':'.$hoy->toDateString(),
                tipo: Candidata::TIPO_FRANJA,
                titulo: 'Franja pendiente de las '.$hora,
                mensaje: 'A las '.$hora.' tenías planificado «'.$actividad.'» y ya pasó la hora sin '
                    .'marcarla. Si la hiciste, márcala en tu diario; si no, acompáñala con una nueva hora.',
                fechaHora: $ahora,
                prioridad: $minutosTarde >= 120 ? 'alta' : 'media',
                fechaReferencia: $hoy,
                dailyPlanId: $plan->id,
                scheduleEntryId: $franja->id,
                meta: [
                    'hora' => $hora,
                    'actividad' => $actividad,
                    'minutos_de_retraso' => $minutosTarde,
                    'margen_minutos' => $margen,
                    'fecha' => $hoy->toDateString(),
                ],
            );
        }

        return $avisos;
    }

    /* ======================================================================
     |  Regla 3 · actividades diarias
     ====================================================================== */

    /**
     * Una pregunta por cada actividad diaria que ya pasó su hora y sigue sin
     * marcarse. Las que ya están marcadas no se notifican, y las que no
     * aplican a ese día (el cambio de sábanas fuera del sábado) tampoco.
     *
     * @return array<int, Candidata>
     */
    private function actividadesPendientes(
        DailyPlan $plan,
        CarbonImmutable $ahora,
        CarbonImmutable $hoy
    ): array {
        if (! config('notificaciones.avisar_actividades', true)) {
            return [];
        }

        $margen = max(0, (int) config('notificaciones.margen_actividades_minutos', 0));
        $horaReserva = (string) config('notificaciones.hora_actividades', '18:00');
        $avisos = [];

        foreach ($plan->activities as $actividad) {
            if ((bool) $actividad->pivot->is_done) {
                continue;
            }

            if (! $actividad->aplicaA($hoy)) {
                continue;
            }

            $hora = $this->horaDelDia((string) ($actividad->reminder_time ?: $horaReserva), $hoy);

            if (! $hora) {
                continue;
            }

            // Igual que las franjas: sólo se avisa cuando la hora ya pasó.
            if ($hora->addMinutes($margen)->greaterThan($ahora)) {
                continue;
            }

            $avisos[] = new Candidata(
                clave: 'actividad:'.$plan->id.':'.$actividad->id.':'.$hoy->toDateString(),
                tipo: Candidata::TIPO_ACTIVIDAD,
                titulo: $actividad->preguntaRecordatorio(),
                mensaje: 'Todavía no está marcada en tu diario de hoy. Si ya la hiciste, márcala '
                    .'para que cuente en tu seguimiento.',
                fechaHora: $ahora,
                prioridad: 'media',
                fechaReferencia: $hoy,
                dailyPlanId: $plan->id,
                dailyActivityId: $actividad->id,
                meta: [
                    'actividad' => $actividad->name,
                    'clave' => $actividad->clave,
                    'categoria' => $actividad->category,
                    'hora' => Helper::timeLabel($actividad->reminder_time) ?? $hora->format('H:i'),
                    'fecha' => $hoy->toDateString(),
                ],
            );
        }

        return $avisos;
    }

    /* ======================================================================
     |  Regla 4 · día sin diario
     ====================================================================== */

    /** @return array<int, Candidata> */
    private function diarioAusente(CarbonImmutable $ahora, CarbonImmutable $hoy): array
    {
        if (! config('notificaciones.avisar_diario_ausente', true)) {
            return [];
        }

        $horaAviso = (string) config('notificaciones.hora_diario_ausente', '08:00');
        $desde = $this->horaDelDia($horaAviso, $hoy) ?? $hoy;

        if ($ahora->lessThan($desde)) {
            return [];
        }

        return [
            new Candidata(
                clave: 'diario-ausente:'.$hoy->toDateString(),
                tipo: Candidata::TIPO_DIARIO_AUSENTE,
                titulo: 'No has cargado el diario de hoy',
                mensaje: 'Hoy es '.Helper::longDate($hoy).' y todavía no registras tu diario. '
                    .'Carga tus 3 objetivos principales y tu horario para empezar el día con rumbo.',
                fechaHora: $ahora,
                prioridad: 'alta',
                fechaReferencia: $hoy,
                meta: [
                    'fecha' => $hoy->toDateString(),
                    'hora_de_aviso' => $horaAviso,
                ],
            ),
        ];
    }

    /* ======================================================================
     |  Auxiliares
     ====================================================================== */

    /** Nombres de la ranura según el catálogo goal_types. */
    private function nombresDeRanura(): array
    {
        return GoalType::query()
            ->active()
            ->get()
            ->sortBy('sort_order')
            ->values()
            ->take(count(self::NOMBRES_RANURA))
            ->mapWithKeys(fn (GoalType $tipo, int $indice) => [$indice + 1 => $tipo->name])
            ->all();
    }

    /** La fecha del día con la hora de la franja, en la zona de la aplicación. */
    private function inicioDeLaFranja(ScheduleEntry $franja, CarbonImmutable $hoy): ?CarbonImmutable
    {
        if (blank($franja->start_time)) {
            return null;
        }

        $inicio = Helper::dateTimeFrom($hoy, $franja->start_time);

        return $inicio ? CarbonImmutable::instance($inicio) : null;
    }

    /** "08:00" sobre el día indicado. */
    private function horaDelDia(string $hora, CarbonImmutable $hoy): ?CarbonImmutable
    {
        if (! preg_match('/^\d{1,2}:\d{2}$/', trim($hora))) {
            return null;
        }

        [$horas, $minutos] = array_map('intval', explode(':', trim($hora)));

        if ($horas > 23 || $minutos > 59) {
            return null;
        }

        return $hoy->setTime($horas, $minutos);
    }
}
