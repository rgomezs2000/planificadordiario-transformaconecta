<?php

namespace App\Reportes;

use App\Helpers\Helper;
use App\Models\DailyPlan;
use Illuminate\Support\Collection;

/**
 * Análisis del desempeño registrado en los diarios.
 *
 * De las marcas que ya guarda cada diario —actividades del horario cumplidas,
 * objetivos logrados, preparativos tildados y preguntas de procrastinación
 * señaladas— saca los números del resumen: rendimiento, qué se hace bien y qué
 * no, metas alcanzadas, cómo influye la energía, cuánto pesa la procrastinación
 * y dónde está el punto crítico.
 *
 * Todo lo que devuelve son datos; los textos, los gráficos y el PDF se arman
 * después con esto. Ninguna cuenta supone nada que el diario no tenga marcado.
 */
class AnalisisDeDesempeno
{
    /** Cuánto pesa cada cosa en el puntaje del día (suman 100). */
    public const PESO_HORARIO = 60;

    public const PESO_OBJETIVOS = 30;

    public const PESO_PREPARATIVOS = 10;

    /** Cuántos puntos tiene que caer el rendimiento para marcar el punto en contra. */
    public const CAIDA_CRITICA = 8;

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
        $dias = $planes
            ->sortBy(fn (DailyPlan $plan) => $plan->plan_date)
            ->map(fn (DailyPlan $plan) => self::unDia($plan))
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
        $horario = $plan->scheduleEntries;
        $objetivos = $plan->goals;
        $preparativos = $plan->preparationItems;
        $reflexiones = $plan->reflectionAnswers;

        $horarioTotal = $horario->count();
        $horarioHechas = $horario->where('is_done', true)->count();
        $objetivosTotal = $objetivos->count();
        $objetivosHechos = $objetivos->where('is_done', true)->count();
        $preparativosTotal = $preparativos->count();
        $preparativosHechos = $preparativos->filter(fn ($item) => (bool) $item->pivot->is_checked)->count();

        $senales = $reflexiones->where('is_checked', true)->count();

        return [
            'id' => $plan->id,
            'fecha' => $plan->plan_date->toDateString(),
            'etiqueta' => Helper::longDate($plan->plan_date, withWeekday: true),
            'dia_semana' => Helper::dayName($plan->plan_date, capitalize: true),
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
            'senales' => $senales,
            'senales_total' => $reflexiones->count(),
            'rendimiento' => self::rendimiento($horarioHechas, $horarioTotal, $objetivosHechos, $objetivosTotal, $preparativosHechos, $preparativosTotal),
            'cierre' => trim((string) $plan->achievements) !== '',
        ];
    }

    /**
     * El puntaje del día, de 0 a 100.
     *
     * Cada parte pesa según lo que aporta al día: el horario 60, los objetivos
     * 30 y los preparativos 10. Una parte sin datos no resta: se reparte su
     * peso entre las que sí tienen, así un diario sin preparativos no queda
     * castigado por algo que no cargó.
     */
    public static function rendimiento(int $hechas, int $total, int $objetivosHechos, int $objetivosTotal, int $preparativosHechos, int $preparativosTotal): float
    {
        $partes = [
            [self::PESO_HORARIO, $hechas, $total],
            [self::PESO_OBJETIVOS, $objetivosHechos, $objetivosTotal],
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
            'senales' => $suma('senales'),
            'senales_promedio' => self::redondear($dias->avg('senales')),
            'dias_con_cierre' => $dias->where('cierre', true)->count(),
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
            ])
            ->sortByDesc('rendimiento')
            ->values()
            ->all();
    }

    /* ==================================================================== */
    /*  Actividades, franjas y objetivos */
    /* ==================================================================== */

    /**
     * Cumplimiento por actividad: lo que siempre se hace y lo que se posterga.
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
     * desde dónde el rendimiento se cae de verdad.
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
