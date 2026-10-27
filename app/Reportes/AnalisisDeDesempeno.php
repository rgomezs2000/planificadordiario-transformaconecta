<?php

namespace App\Reportes;

use App\Helpers\Helper;
use App\Models\DailyActivity;
use App\Models\DailyPlan;
use App\Models\WeeklyReflection;
use Illuminate\Support\Collection;

/**
 * Análisis del desempeño registrado en los diarios.
 *
 * De las marcas que ya guarda cada diario —actividades del horario cumplidas,
 * objetivos logrados, actividades diarias hechas, metas de la semana,
 * preparativos tildados y preguntas de procrastinación señaladas— saca los
 * números del resumen: rendimiento, qué se hace bien y qué no, metas
 * alcanzadas, cómo influye la energía, cuánto pesa la procrastinación, qué
 * actividades acompañan un mejor día y dónde está el punto crítico.
 *
 * Todo lo que devuelve son datos; los textos, los gráficos y el PDF se arman
 * después con esto. Ninguna cuenta supone nada que el diario no tenga marcado.
 */
class AnalisisDeDesempeno
{
    /**
     * Cuánto pesa cada parte en el puntaje del día (suman 100).
     *
     * El horario sigue mandando, pero las actividades diarias —los hábitos que
     * la hoja nueva pide marcar— entran con peso propio: son la evidencia más
     * fina de lo que se sostiene fuera de la agenda.
     */
    public const PESO_HORARIO = 45;

    public const PESO_OBJETIVOS = 25;

    public const PESO_ACTIVIDADES = 20;

    public const PESO_PREPARATIVOS = 10;

    /** Cuántos puntos tiene que caer el rendimiento para marcar el punto en contra. */
    public const CAIDA_CRITICA = 8;

    /** Mínimo de días por grupo para considerar firme una comparación. */
    public const MINIMO_POR_GRUPO = 2;

    /** Mínimo de días con señales para leer la correlación. */
    public const MINIMO_CORRELACION = 3;

    /** Los tramos del día, para ver en qué franja se cumple más. */
    public const FRANJAS = [
        'Madrugada' => [0, 5],
        'Mañana' => [6, 11],
        'Tarde' => [12, 17],
        'Noche' => [18, 23],
    ];

    /**
     * El análisis completo de los diarios que se le pasen (ya filtrados).
     *
     * @param  Collection<int, DailyPlan>  $planes
     */
    public static function de(Collection $planes, string $alcance = 'Todos los diarios'): array
    {
        $planes = $planes->sortBy(fn (DailyPlan $plan) => $plan->plan_date)->values();

        // Las reflexiones se traen de una vez por semana, no una consulta por
        // día: la reflexión es de la semana, no del diario.
        $reflexiones = self::reflexionesDeLasSemanas($planes);

        $dias = $planes
            ->map(fn (DailyPlan $plan) => self::unDia($plan))
            ->map(function (array $dia) use ($reflexiones) {
                $semana = Helper::startOfWeek($dia['fecha']);
                $reflexion = $semana ? $reflexiones->get($semana->toDateString()) : null;

                $respondidas = $reflexion
                    ? count(array_filter(
                        $reflexion->respuestas(),
                        fn (array $fila) => ! Helper::isBlank($fila['respuesta'])
                    ))
                    : 0;

                $dia['tiene_reflexion'] = $dia['es_domingo'] && $respondidas > 0;
                $dia['reflexion_respondidas'] = $respondidas;

                return $dia;
            })
            ->values();

        $analisis = [
            'alcance' => $alcance,
            'dias' => $dias->count(),
            'desde' => $dias->first()['fecha'] ?? null,
            'hasta' => $dias->last()['fecha'] ?? null,
            'global' => self::global($dias),
            'detalle' => $dias->all(),
            'porEnergia' => self::porEnergia($dias),
            'porDiaSemana' => self::porDiaSemana($dias),
            'actividades' => self::actividades($planes),
            'franjas' => self::franjas($planes),
            'objetivos' => self::objetivos($planes),
            'procrastinacion' => self::procrastinacion($planes),
            'actividadesDiarias' => self::actividadesDiarias($planes),
            'metasSemanales' => self::metasSemanales($planes),
            'reflexionesSemanales' => self::reflexionesSemanales($reflexiones),
            'impactoActividades' => self::impactoActividades($dias),
            'procrastinacionVsActividades' => self::procrastinacionVsActividades($dias),
            'correlacion' => self::correlacion($dias),
            'tendencia' => $dias->map(fn (array $dia) => [
                'fecha' => $dia['fecha'],
                'etiqueta' => $dia['etiqueta'],
                'rendimiento' => $dia['rendimiento'],
                'senales' => $dia['senales'],
            ])->all(),
            'avisos' => [],
        ];

        $analisis['puntoCritico'] = self::puntoCritico($dias);
        $analisis['avisos'] = self::avisos($analisis);

        return $analisis;
    }

    /* ==================================================================== */
    /*  Un día */
    /* ==================================================================== */

    /**
     * Los números de un solo diario.
     *
     * @return array<string, mixed>
     */
    public static function unDia(DailyPlan $plan): array
    {
        $plan->loadMissing([
            'energyLevel',
            'weeklyGoal',
            'goals.goalType',
            'scheduleEntries',
            'activities',
            'preparationItems',
            'reflectionAnswers.question',
        ]);

        $horario = $plan->scheduleEntries;
        $objetivos = $plan->goals;
        $preparativos = $plan->preparationItems;
        $reflexiones = $plan->reflectionAnswers;
        $actividades = $plan->activities;

        $horarioTotal = $horario->count();
        $horarioHechas = $horario->where('is_done', true)->count();
        $objetivosTotal = $objetivos->count();
        $objetivosHechos = $objetivos->where('is_done', true)->count();
        $preparativosTotal = $preparativos->count();
        $preparativosHechos = $preparativos->filter(fn ($item) => (bool) $item->pivot->is_checked)->count();

        $actividadesTotal = $actividades->count();
        $actividadesHechas = $actividades->filter(fn (DailyActivity $a) => (bool) $a->pivot->is_done);
        $minutosActividades = (int) $actividades->sum(fn (DailyActivity $a) => (int) ($a->pivot->minutes ?? 0));

        $senales = $reflexiones->where('is_checked', true)->count();

        return [
            'id' => $plan->id,
            'fecha' => $plan->plan_date->toDateString(),
            'etiqueta' => Helper::longDate($plan->plan_date, withWeekday: true),
            'dia_semana' => Helper::dayName($plan->plan_date, capitalize: true),
            'es_domingo' => $plan->plan_date->isSunday(),
            'energia' => $plan->energyLevel?->name ?? 'Sin registrar',
            'energia_slug' => $plan->energyLevel?->slug ?? '',
            'horario_total' => $horarioTotal,
            'horario_hechas' => $horarioHechas,
            'horario_pct' => self::porcentaje($horarioHechas, $horarioTotal),
            'objetivos_total' => $objetivosTotal,
            'objetivos_hechos' => $objetivosHechos,
            'objetivos_pct' => self::porcentaje($objetivosHechos, $objetivosTotal),
            'preparativos_total' => $preparativosTotal,
            'preparativos_hechos' => $preparativosHechos,
            'preparativos_pct' => self::porcentaje($preparativosHechos, $preparativosTotal),
            'actividades_total' => $actividadesTotal,
            'actividades_hechas' => $actividadesHechas->count(),
            'actividades_pct' => self::porcentaje($actividadesHechas->count(), $actividadesTotal),
            'actividades_hechas_claves' => $actividadesHechas->pluck('clave')->values()->all(),
            'actividad_minutos' => $minutosActividades,
            'meta_semanal' => Helper::strip($plan->metaSemanal()?->goal),
            'tiene_seguimiento_personal' => ! Helper::isBlank($plan->personal_done_today)
                || ! Helper::isBlank($plan->personal_helped)
                || ! Helper::isBlank($plan->personal_hard),
            'senales' => $senales,
            'senales_total' => $reflexiones->count(),
            'rendimiento' => self::rendimiento(
                $horarioHechas,
                $horarioTotal,
                $objetivosHechos,
                $objetivosTotal,
                $preparativosHechos,
                $preparativosTotal,
                $actividadesHechas->count(),
                $actividadesTotal
            ),
            'cierre' => trim((string) $plan->achievements) !== '',
        ];
    }

    /**
     * El puntaje del día, de 0 a 100.
     *
     * Cada parte pesa según lo que aporta al día: el horario 45, los objetivos
     * 25, las actividades diarias 20 y los preparativos 10. Una parte sin
     * datos no resta: se reparte su peso entre las que sí tienen, así un
     * diario sin preparativos no queda castigado por algo que no cargó.
     */
    public static function rendimiento(
        int $hechas,
        int $total,
        int $objetivosHechos,
        int $objetivosTotal,
        int $preparativosHechos,
        int $preparativosTotal,
        int $actividadesHechas = 0,
        int $actividadesTotal = 0
    ): float {
        $partes = [
            [self::PESO_HORARIO, $hechas, $total],
            [self::PESO_OBJETIVOS, $objetivosHechos, $objetivosTotal],
            [self::PESO_ACTIVIDADES, $actividadesHechas, $actividadesTotal],
            [self::PESO_PREPARATIVOS, $preparativosHechos, $preparativosTotal],
        ];

        $puntos = 0.0;
        $pesoUsado = 0;

        foreach ($partes as [$peso, $logrado, $cantidad]) {
            if ($cantidad <= 0) {
                continue;
            }

            $puntos += $peso * ($logrado / $cantidad);
            $pesoUsado += $peso;
        }

        return $pesoUsado === 0 ? 0.0 : round($puntos / $pesoUsado * 100, 1);
    }

    /* ==================================================================== */
    /*  Los totales */
    /* ==================================================================== */

    /** @param  Collection<int, array<string, mixed>>  $dias */
    private static function global(Collection $dias): array
    {
        $suma = fn (string $clave) => (int) $dias->sum($clave);

        $horarioTotal = $suma('horario_total');
        $objetivosTotal = $suma('objetivos_total');
        $preparativosTotal = $suma('preparativos_total');
        $actividadesTotal = $suma('actividades_total');

        return [
            'rendimiento' => self::redondear($dias->avg('rendimiento')),
            'rendimiento_mejor' => self::redondear($dias->max('rendimiento')),
            'rendimiento_peor' => self::redondear($dias->min('rendimiento')),
            'horario_total' => $horarioTotal,
            'horario_hechas' => $suma('horario_hechas'),
            'horario_pct' => self::porcentaje($suma('horario_hechas'), $horarioTotal),
            'objetivos_total' => $objetivosTotal,
            'objetivos_hechos' => $suma('objetivos_hechos'),
            'objetivos_pct' => self::porcentaje($suma('objetivos_hechos'), $objetivosTotal),
            'preparativos_total' => $preparativosTotal,
            'preparativos_hechos' => $suma('preparativos_hechos'),
            'preparativos_pct' => self::porcentaje($suma('preparativos_hechos'), $preparativosTotal),
            'actividades_total' => $actividadesTotal,
            'actividades_hechas' => $suma('actividades_hechas'),
            'actividades_pct' => self::porcentaje($suma('actividades_hechas'), $actividadesTotal),
            'actividad_minutos' => $suma('actividad_minutos'),
            'actividad_minutos_promedio' => self::redondear($dias->avg('actividad_minutos')),
            'senales' => $suma('senales'),
            'senales_promedio' => self::redondear($dias->avg('senales')),
            'dias_con_cierre' => $dias->where('cierre', true)->count(),
            'dias_con_meta_semanal' => $dias->filter(fn (array $dia) => $dia['meta_semanal'] !== '')->count(),
            'dias_con_reflexion' => $dias->where('tiene_reflexion', true)->count(),
            'dias_con_actividades' => $dias->where('actividades_total', '>', 0)->count(),
        ];
    }

    /** @param  Collection<int, array<string, mixed>>  $dias */
    private static function porEnergia(Collection $dias): array
    {
        return $dias
            ->groupBy('energia')
            ->map(fn (Collection $grupo, string $energia) => [
                'energia' => $energia,
                'slug' => $grupo->first()['energia_slug'],
                'dias' => $grupo->count(),
                'rendimiento' => self::redondear($grupo->avg('rendimiento')),
                'horario_pct' => self::porcentaje((int) $grupo->sum('horario_hechas'), (int) $grupo->sum('horario_total')),
                'actividades_pct' => self::porcentaje((int) $grupo->sum('actividades_hechas'), (int) $grupo->sum('actividades_total')),
                'senales' => self::redondear($grupo->avg('senales')),
            ])
            ->sortByDesc('rendimiento')
            ->values()
            ->all();
    }

    /** @param  Collection<int, array<string, mixed>>  $dias */
    private static function porDiaSemana(Collection $dias): array
    {
        return $dias
            ->groupBy('dia_semana')
            ->map(fn (Collection $grupo, string $dia) => [
                'dia' => $dia,
                'dias' => $grupo->count(),
                'rendimiento' => self::redondear($grupo->avg('rendimiento')),
                'actividades_pct' => self::porcentaje((int) $grupo->sum('actividades_hechas'), (int) $grupo->sum('actividades_total')),
            ])
            ->sortByDesc('rendimiento')
            ->values()
            ->all();
    }

    /* ==================================================================== */
    /*  Actividades del horario, franjas y objetivos */
    /* ==================================================================== */

    /**
     * Cumplimiento por actividad del horario: lo que siempre se hace y lo que
     * se posterga.
     *
     * @param  Collection<int, DailyPlan>  $planes
     */
    private static function actividades(Collection $planes, int $minimo = 1): array
    {
        $porActividad = [];

        foreach ($planes as $plan) {
            foreach ($plan->scheduleEntries as $entrada) {
                $nombre = self::limpiarActividad($entrada->activity);

                if ($nombre === '') {
                    continue;
                }

                $porActividad[$nombre] ??= ['actividad' => $nombre, 'total' => 0, 'hechas' => 0];
                $porActividad[$nombre]['total']++;
                $porActividad[$nombre]['hechas'] += $entrada->is_done ? 1 : 0;
            }
        }

        return collect($porActividad)
            ->map(function (array $fila) {
                $fila['pct'] = self::porcentaje($fila['hechas'], $fila['total']);

                return $fila;
            })
            ->filter(fn (array $fila) => $fila['total'] >= $minimo)
            ->sortBy([['pct', 'desc'], ['total', 'desc']])
            ->values()
            ->all();
    }

    /**
     * Cumplimiento por franja del día, según la hora de cada actividad.
     *
     * @param  Collection<int, DailyPlan>  $planes
     */
    private static function franjas(Collection $planes): array
    {
        $franjas = collect(self::FRANJAS)
            ->map(fn (array $rango, string $nombre) => [
                'franja' => $nombre,
                'total' => 0,
                'hechas' => 0,
                'pct' => 0.0,
            ])
            ->all();

        foreach ($planes as $plan) {
            foreach ($plan->scheduleEntries as $entrada) {
                $minutos = self::aMinutos($entrada->start_time);

                if ($minutos === null) {
                    continue;
                }

                $hora = intdiv($minutos, 60);

                foreach (self::FRANJAS as $nombre => [$desde, $hasta]) {
                    if ($hora >= $desde && $hora <= $hasta) {
                        $franjas[$nombre]['total']++;
                        $franjas[$nombre]['hechas'] += $entrada->is_done ? 1 : 0;
                        break;
                    }
                }
            }
        }

        return collect($franjas)
            ->map(function (array $fila) {
                $fila['pct'] = self::porcentaje($fila['hechas'], $fila['total']);

                return $fila;
            })
            ->values()
            ->all();
    }

    /**
     * Metas alcanzadas: los objetivos logrados y los que quedaron, por tipo.
     *
     * @param  Collection<int, DailyPlan>  $planes
     */
    private static function objetivos(Collection $planes): array
    {
        $porTipo = [];

        foreach ($planes as $plan) {
            foreach ($plan->goals as $objetivo) {
                $tipo = $objetivo->goalType?->name ?? 'Sin tipo';

                $porTipo[$tipo] ??= ['tipo' => $tipo, 'total' => 0, 'hechos' => 0, 'pendientes' => []];
                $porTipo[$tipo]['total']++;
                $porTipo[$tipo]['hechos'] += $objetivo->is_done ? 1 : 0;

                if (! $objetivo->is_done && count($porTipo[$tipo]['pendientes']) < 6) {
                    $porTipo[$tipo]['pendientes'][] = trim((string) $objetivo->description);
                }
            }
        }

        return collect($porTipo)
            ->map(function (array $fila) {
                $fila['pct'] = self::porcentaje($fila['hechos'], $fila['total']);
                $fila['pendientes'] = array_values(array_filter($fila['pendientes']));

                return $fila;
            })
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /* ==================================================================== */
    /*  Actividades diarias (hábitos) */
    /* ==================================================================== */

    /**
     * Cumplimiento de cada actividad diaria en el período.
     *
     * "total" es la cantidad de días en que la actividad estaba disponible
     * (el sábado suma las suyas), no la cantidad de diarios: así una actividad
     * semanal no queda castigada por los días en que no aplicaba.
     *
     * @param  Collection<int, DailyPlan>  $planes
     */
    private static function actividadesDiarias(Collection $planes): array
    {
        $porActividad = [];

        foreach ($planes as $plan) {
            foreach ($plan->activities as $actividad) {
                $clave = $actividad->clave;

                $porActividad[$clave] ??= [
                    'clave' => $clave,
                    'actividad' => $actividad->name,
                    'categoria' => $actividad->category,
                    'categoria_label' => $actividad->categoriaLabel(),
                    'total' => 0,
                    'hechas' => 0,
                    'minutos' => 0,
                    'dias_con_minutos' => 0,
                ];

                $porActividad[$clave]['total']++;

                if ((bool) $actividad->pivot->is_done) {
                    $porActividad[$clave]['hechas']++;
                    $porActividad[$clave]['minutos'] += (int) ($actividad->pivot->minutes ?? 0);

                    if ((int) ($actividad->pivot->minutes ?? 0) > 0) {
                        $porActividad[$clave]['dias_con_minutos']++;
                    }
                }
            }
        }

        return collect($porActividad)
            ->map(function (array $fila) {
                $fila['pct'] = self::porcentaje($fila['hechas'], $fila['total']);
                $fila['minutos_promedio'] = $fila['dias_con_minutos'] > 0
                    ? self::redondear($fila['minutos'] / $fila['dias_con_minutos'])
                    : 0.0;

                return $fila;
            })
            ->sortBy([['pct', 'desc'], ['total', 'desc']])
            ->values()
            ->all();
    }

    /**
     * Qué actividades acompañan un mejor rendimiento.
     *
     * Compara el rendimiento promedio de los días en que la actividad se hizo
     * con el de los días en que no, y deja la diferencia en puntos. Es una
     * asociación, no una causa: por eso se informa también cuántos días hay de
     * cada lado.
     *
     * @param  Collection<int, array<string, mixed>>  $dias
     */
    private static function impactoActividades(Collection $dias): array
    {
        $claves = $dias
            ->flatMap(fn (array $dia) => $dia['actividades_hechas_claves'])
            ->unique()
            ->values();

        $nombres = DailyActivity::query()
            ->whereIn('clave', $claves->all())
            ->get()
            ->keyBy('clave');

        $impactos = [];

        foreach ($claves as $clave) {
            $con = $dias->filter(fn (array $dia) => in_array($clave, $dia['actividades_hechas_claves'], true));
            $sin = $dias->reject(fn (array $dia) => in_array($clave, $dia['actividades_hechas_claves'], true));

            if ($con->isEmpty()) {
                continue;
            }

            $impactos[] = [
                'clave' => $clave,
                'actividad' => $nombres->get($clave)?->name ?? $clave,
                'dias_con' => $con->count(),
                'dias_sin' => $sin->count(),
                'rendimiento_con' => self::redondear($con->avg('rendimiento')),
                'rendimiento_sin' => $sin->isEmpty() ? null : self::redondear($sin->avg('rendimiento')),
                'diferencia' => $sin->isEmpty()
                    ? null
                    : self::redondear($con->avg('rendimiento') - $sin->avg('rendimiento')),
            ];
        }

        return collect($impactos)
            ->filter(fn (array $fila) => $fila['diferencia'] !== null)
            ->sortByDesc(fn (array $fila) => abs((float) $fila['diferencia']))
            ->values()
            ->all();
    }

    /* ==================================================================== */
    /*  Metas y reflexiones de la semana */
    /* ==================================================================== */

    /**
     * Las metas semanales que cubre el período.
     *
     * @param  Collection<int, DailyPlan>  $planes
     */
    private static function metasSemanales(Collection $planes): array
    {
        return $planes
            ->groupBy(fn (DailyPlan $plan) => Helper::startOfWeek($plan->plan_date)?->toDateString())
            ->filter(fn (Collection $grupo, mixed $semana) => $semana !== '' && $semana !== null)
            ->map(function (Collection $grupo, string $semana) {
                /** @var DailyPlan $primero */
                $primero = $grupo->first();
                $meta = $primero->metaSemanal();

                $seguimientos = $grupo->filter(fn (DailyPlan $plan) => ! Helper::isBlank($plan->personal_done_today)
                    || ! Helper::isBlank($plan->personal_helped)
                    || ! Helper::isBlank($plan->personal_hard))->count();

                $objetivosTotal = $grupo->sum(fn (DailyPlan $plan) => $plan->goals->count());
                $objetivosHechos = $grupo->sum(fn (DailyPlan $plan) => $plan->goals->where('is_done', true)->count());

                return [
                    'week_start' => $semana,
                    'etiqueta' => $meta?->etiqueta() ?? 'Semana del '.Helper::date($semana),
                    'goal' => $meta?->goal,
                    'why_important' => $meta?->why_important,
                    'dias' => $grupo->count(),
                    'dias_con_seguimiento' => $seguimientos,
                    'objetivos_total' => $objetivosTotal,
                    'objetivos_hechos' => $objetivosHechos,
                    'objetivos_pct' => self::porcentaje($objetivosHechos, $objetivosTotal),
                ];
            })
            ->sortBy('week_start')
            ->values()
            ->filter(fn (array $semana) => ! Helper::isBlank($semana['goal']))
            ->values()
            ->all();
    }

    /**
     * Las reflexiones semanales que caen dentro del período.
     *
     * @param  Collection<int, DailyPlan>  $planes
     * @return Collection<string, WeeklyReflection>
     */
    private static function reflexionesDeLasSemanas(Collection $planes): Collection
    {
        $semanas = $planes
            ->map(fn (DailyPlan $plan) => Helper::startOfWeek($plan->plan_date)?->toDateString())
            ->filter()
            ->unique()
            ->values();

        if ($semanas->isEmpty()) {
            return collect();
        }

        return WeeklyReflection::query()
            ->whereIn('week_start', $semanas->all())
            ->get()
            ->keyBy(fn (WeeklyReflection $reflexion) => $reflexion->week_start->toDateString());
    }

    /**
     * @param  Collection<string, WeeklyReflection>  $reflexiones
     */
    private static function reflexionesSemanales(Collection $reflexiones): array
    {
        return $reflexiones
            ->map(function (WeeklyReflection $reflexion) {
                $respondidas = array_filter(
                    $reflexion->respuestas(),
                    fn (array $fila) => ! Helper::isBlank($fila['respuesta'])
                );

                return [
                    'week_start' => $reflexion->week_start->toDateString(),
                    'etiqueta' => $reflexion->etiqueta(),
                    'respondidas' => count($respondidas),
                    'total' => count(WeeklyReflection::PREGUNTAS),
                    'respuestas' => $reflexion->respuestas(),
                ];
            })
            ->sortBy('week_start')
            ->values()
            ->all();
    }

    /* ==================================================================== */
    /*  Procrastinación y punto crítico */
    /* ==================================================================== */

    /**
     * Las preguntas de procrastinación que más se repiten.
     *
     * @param  Collection<int, DailyPlan>  $planes
     */
    private static function procrastinacion(Collection $planes): array
    {
        $porPregunta = [];
        $dias = 0;
        $diasConSenales = 0;

        foreach ($planes as $plan) {
            $dias++;

            if ($plan->reflectionAnswers->where('is_checked', true)->isNotEmpty()) {
                $diasConSenales++;
            }

            foreach ($plan->reflectionAnswers as $respuesta) {
                $texto = trim((string) ($respuesta->question?->text ?? $respuesta->question?->question ?? ''));

                if ($texto === '') {
                    continue;
                }

                $porPregunta[$texto] ??= ['pregunta' => $texto, 'señales' => 0, 'respuestas' => 0];
                $porPregunta[$texto]['respuestas']++;

                if ($respuesta->is_checked) {
                    $porPregunta[$texto]['señales']++;
                }
            }
        }

        return [
            'dias' => $dias,
            'dias_con_senales' => $diasConSenales,
            'dias_sin_senales' => $dias - $diasConSenales,
            'preguntas' => collect($porPregunta)
                ->sortByDesc('señales')
                ->values()
                ->all(),
        ];
    }

    /**
     * Dónde la procrastinación empieza a costar rendimiento.
     *
     * Compara el rendimiento medio de los días según cuántas señales marcaron.
     * El punto a favor es hasta dónde no se nota la caída; el punto en contra,
     * desde dónde el rendimiento se cae de verdad. Cada grupo informa cuántos
     * días lo sostienen: con un solo día no se puede afirmar una tendencia.
     *
     * @param  Collection<int, array<string, mixed>>  $dias
     */
    private static function puntoCritico(Collection $dias): array
    {
        $grupos = $dias
            ->groupBy('senales')
            ->map(fn (Collection $grupo, int $senales) => [
                'senales' => (int) $senales,
                'dias' => $grupo->count(),
                'rendimiento' => self::redondear($grupo->avg('rendimiento')),
                'solido' => $grupo->count() >= self::MINIMO_POR_GRUPO,
            ])
            ->sortBy('senales')
            ->values();

        $referencia = self::redondear($dias->avg('rendimiento'));
        $limite = $referencia - self::CAIDA_CRITICA;

        $aFavor = null;
        $enContra = null;

        foreach ($grupos as $grupo) {
            if ($grupo['rendimiento'] >= $limite) {
                // Todavía no se nota: si ya había un punto en contra, no se pisa.
                if ($enContra === null) {
                    $aFavor = $grupo;
                }

                continue;
            }

            $enContra ??= $grupo;
        }

        return [
            'referencia' => $referencia,
            'limite' => self::redondear($limite),
            'grupos' => $grupos->all(),
            'aFavor' => $aFavor,
            'enContra' => $enContra,
            'caida' => ($aFavor && $enContra) ? self::redondear($aFavor['rendimiento'] - $enContra['rendimiento']) : null,
        ];
    }

    /**
     * Correlación de Pearson entre las señales del día y el rendimiento.
     *
     * Va de -1 a 1. Se informa sólo con al menos MINIMO_CORRELACION días y se
     * acompaña de una lectura cualitativa prudente: una correlación no prueba
     * que una cosa cause la otra.
     *
     * @param  Collection<int, array<string, mixed>>  $dias
     */
    private static function correlacion(Collection $dias): array
    {
        $n = $dias->count();

        if ($n < self::MINIMO_CORRELACION) {
            return [
                'r' => null,
                'n' => $n,
                'fuerza' => 'sin datos suficientes',
                'sentido' => null,
                'lectura' => 'Hacen falta al menos '.self::MINIMO_CORRELACION.' días para leer una correlación.',
            ];
        }

        $x = $dias->pluck('senales')->map(fn ($valor) => (float) $valor)->all();
        $y = $dias->pluck('rendimiento')->map(fn ($valor) => (float) $valor)->all();

        $mediaX = array_sum($x) / $n;
        $mediaY = array_sum($y) / $n;

        $covarianza = 0.0;
        $varianzaX = 0.0;
        $varianzaY = 0.0;

        for ($i = 0; $i < $n; $i++) {
            $dx = $x[$i] - $mediaX;
            $dy = $y[$i] - $mediaY;

            $covarianza += $dx * $dy;
            $varianzaX += $dx * $dx;
            $varianzaY += $dy * $dy;
        }

        if ($varianzaX <= 0 || $varianzaY <= 0) {
            return [
                'r' => null,
                'n' => $n,
                'fuerza' => 'sin variación',
                'sentido' => null,
                'lectura' => 'Los registros no varían lo suficiente para calcular una correlación.',
            ];
        }

        $r = round($covarianza / sqrt($varianzaX * $varianzaY), 2);
        $absoluto = abs($r);

        $fuerza = match (true) {
            $absoluto >= 0.7 => 'fuerte',
            $absoluto >= 0.4 => 'moderada',
            $absoluto >= 0.2 => 'débil',
            default => 'muy débil',
        };

        return [
            'r' => $r,
            'n' => $n,
            'fuerza' => $fuerza,
            'sentido' => $r < 0 ? 'inversa' : ($r > 0 ? 'directa' : 'nula'),
            'lectura' => 'Con '.$n.' días, la asociación entre señales y rendimiento es '.$fuerza
                .($r < 0 ? ' e inversa' : ($r > 0 ? ' y directa' : '')).'.',
        ];
    }

    /**
     * El rendimiento y las actividades según haya señales o no.
     *
     * Responde a la pregunta del resumen: ¿los días con más señales también
     * son los días con menos hábitos sostenidos?
     *
     * @param  Collection<int, array<string, mixed>>  $dias
     */
    private static function procrastinacionVsActividades(Collection $dias): array
    {
        $con = $dias->filter(fn (array $dia) => $dia['senales'] > 0);
        $sin = $dias->filter(fn (array $dia) => $dia['senales'] === 0);

        $resumen = fn (Collection $grupo) => [
            'dias' => $grupo->count(),
            'rendimiento' => $grupo->isEmpty() ? null : self::redondear($grupo->avg('rendimiento')),
            'actividades_pct' => $grupo->isEmpty()
                ? null
                : self::porcentaje((int) $grupo->sum('actividades_hechas'), (int) $grupo->sum('actividades_total')),
            'horario_pct' => $grupo->isEmpty()
                ? null
                : self::porcentaje((int) $grupo->sum('horario_hechas'), (int) $grupo->sum('horario_total')),
            'objetivos_pct' => $grupo->isEmpty()
                ? null
                : self::porcentaje((int) $grupo->sum('objetivos_hechos'), (int) $grupo->sum('objetivos_total')),
        ];

        $conResumen = $resumen($con);
        $sinResumen = $resumen($sin);

        return [
            'con_senales' => $conResumen,
            'sin_senales' => $sinResumen,
            'brecha_rendimiento' => ($conResumen['rendimiento'] !== null && $sinResumen['rendimiento'] !== null)
                ? self::redondear($sinResumen['rendimiento'] - $conResumen['rendimiento'])
                : null,
            'brecha_actividades' => ($conResumen['actividades_pct'] !== null && $sinResumen['actividades_pct'] !== null)
                ? self::redondear($sinResumen['actividades_pct'] - $conResumen['actividades_pct'])
                : null,
        ];
    }

    /**
     * Lo que hay que aclarar sobre los registros, sin calificar el análisis.
     *
     * Los cruces se muestran siempre, con los días que haya: no hay un mínimo
     * que los bloquee ni una advertencia que los descalifique. Acá sólo van
     * datos de contexto, nunca una valoración sobre la persona.
     *
     * @param  array<string, mixed>  $analisis
     */
    private static function avisos(array $analisis): array
    {
        $avisos = [];
        $dias = (int) $analisis['dias'];

        if ($dias === 0) {
            return ['No hay diarios que coincidan con la búsqueda.'];
        }

        if ((int) $analisis['global']['senales'] === 0) {
            $avisos[] = 'No hay ninguna pregunta de procrastinación señalada en el período, '
                .'así que no se puede medir su impacto en el rendimiento.';
        }

        if ((int) $analisis['global']['horario_total'] === 0) {
            $avisos[] = 'Los diarios del período no tienen franjas de horario cargadas.';
        }

        if ((int) $analisis['global']['actividades_total'] === 0) {
            $avisos[] = 'Los diarios del período no tienen actividades diarias marcadas.';
        }

        if ((int) $analisis['global']['dias_con_meta_semanal'] === 0) {
            $avisos[] = 'Los diarios del período no tienen una meta semanal registrada.';
        }

        if ((int) $analisis['global']['dias_con_reflexion'] === 0) {
            $avisos[] = 'No hay reflexiones semanales escritas en el período.';
        }

        if ((int) $analisis['global']['dias_con_cierre'] < $dias) {
            $avisos[] = 'Hay '.($dias - (int) $analisis['global']['dias_con_cierre'])
                .' diario(s) sin el cierre del día escrito.';
        }

        return $avisos;
    }

    /* ==================================================================== */
    /*  Ayudas */
    /* ==================================================================== */

    /** Deja el nombre de la actividad comparable: sin espacios de más ni mayúsculas. */
    private static function limpiarActividad(mixed $actividad): string
    {
        $texto = preg_replace('/\s+/u', ' ', trim((string) $actividad)) ?? '';

        return mb_strtolower($texto, 'UTF-8');
    }

    private static function aMinutos(mixed $hora): ?int
    {
        if (is_string($hora) && preg_match('/^\s*(\d{1,2}):(\d{2})/', $hora, $partes)) {
            $horas = (int) $partes[1];

            return $horas > 23 ? null : ($horas * 60) + (int) $partes[2];
        }

        return null;
    }

    private static function porcentaje(int $parte, int $total): float
    {
        return $total <= 0 ? 0.0 : round($parte / $total * 100, 1);
    }

    private static function redondear(mixed $valor): float
    {
        return round((float) $valor, 1);
    }
}
