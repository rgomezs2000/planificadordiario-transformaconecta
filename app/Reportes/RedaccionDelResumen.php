<?php

namespace App\Reportes;

use App\Helpers\Helper;

/**
 * La redacción del resumen: convierte los números del análisis en frases.
 *
 * Va aparte de AnalisisDeDesempeno a propósito: aquel calcula y éste escribe.
 *
 * El tono es neutral y diplomático. Se describe lo que los registros muestran,
 * sin juzgar a la persona y sin dramatizar: ni "no hiciste nada" ni "¡vas
 * genial!". Las frases evitan los imperativos y las valoraciones, y cuando un
 * dato no alcanza para afirmar algo, se dice con la misma serenidad.
 */
class RedaccionDelResumen
{
    /**
     * Los títulos de las secciones del documento.
     *
     * "Aspectos a mejorar" en lugar de "lo que hago mal": el contenido es el
     * mismo, dicho sin poner a la persona en el banquillo.
     */
    public static function titulos(): array
    {
        return [
            'desempeno' => 'Desempeño del período',
            'bien' => 'Aspectos que se sostuvieron',
            'mal' => 'Aspectos a mejorar',
            'metas' => 'Metas y objetivos alcanzados',
            'energia' => 'Relación con la energía del día',
            'procrastinacion' => 'Procrastinación y rendimiento',
            'tendencia' => 'Evolución en el período',
        ];
    }

    /**
     * El análisis escrito, agrupado por tema.
     *
     * @param  array<string, mixed>  $analisis
     * @return array<string, array<int, string>>
     */
    public static function observaciones(array $analisis): array
    {
        if ((int) ($analisis['dias'] ?? 0) === 0) {
            return ['desempeno' => ['No hay registros que coincidan con la búsqueda.']];
        }

        return array_filter([
            'desempeno' => self::desempeno($analisis),
            'bien' => self::sostenido($analisis),
            'mal' => self::aMejorar($analisis),
            'metas' => self::metas($analisis),
            'energia' => self::energia($analisis),
            'procrastinacion' => self::procrastinacion($analisis),
            'tendencia' => self::tendencia($analisis),
        ]);
    }

    /* ------------------------------------------------------------------ */

    /** @param  array<string, mixed>  $analisis */
    private static function desempeno(array $analisis): array
    {
        $global = $analisis['global'];
        $dias = (int) $analisis['dias'];

        $desde = $analisis['desde'] ? Helper::toCarbon($analisis['desde']) : null;
        $hasta = $analisis['hasta'] ? Helper::toCarbon($analisis['hasta']) : null;

        $frases = [];

        $frases[] = 'El resumen comprende '.$dias.($dias === 1 ? ' día' : ' días')
            .($desde && $hasta ? ', del '.Helper::date($desde).' al '.Helper::date($hasta).'.' : '.');

        $frases[] = 'El rendimiento promedio del período fue de '.$global['rendimiento'].' sobre 100. '
            .'El registro más alto fue '.$global['rendimiento_mejor'].' y el más bajo '
            .$global['rendimiento_peor'].'.';

        $frases[] = 'Del horario previsto se completaron '.$global['horario_hechas'].' de '
            .$global['horario_total'].' actividades ('.$global['horario_pct'].'%)'
            .' y '.$global['objetivos_hechos'].' de '.$global['objetivos_total']
            .' objetivos ('.$global['objetivos_pct'].'%)'
            .($global['preparativos_total'] > 0
                ? ', con los preparativos en '.$global['preparativos_pct'].'% ('
                    .$global['preparativos_hechos'].' de '.$global['preparativos_total'].').'
                : '. En el período no se registraron preparativos.');

        return $frases;
    }

    /** @param  array<string, mixed>  $analisis */
    private static function sostenido(array $analisis): array
    {
        $frases = [];

        $siempre = collect($analisis['actividades'])
            ->where('pct', '>=', 100)
            ->sortByDesc('total')
            ->take(4)
            ->pluck('actividad')
            ->all();

        if ($siempre !== []) {
            $frases[] = 'Se cumplieron en todos los registros: '.self::enumerar($siempre).'.';
        }

        $franjas = collect($analisis['franjas'])->where('total', '>', 0)->sortByDesc('pct');
        $mejor = $franjas->first();

        if ($mejor && $mejor['pct'] >= 80 && $franjas->count() > 1) {
            $frases[] = 'La franja con mayor cumplimiento fue la '.mb_strtolower($mejor['franja'])
                .', con '.$mejor['pct'].'% ('.$mejor['hechas'].' de '.$mejor['total'].').';
        }

        $dias = collect($analisis['porDiaSemana'])->sortByDesc('rendimiento');

        if ($dias->count() > 1) {
            $dia = $dias->first();
            $frases[] = 'El día de la semana con mejor rendimiento promedio fue el '
                .mb_strtolower($dia['dia']).', con '.$dia['rendimiento'].'.';
        }

        if ($frases === []) {
            $frases[] = 'Con los registros disponibles todavía no hay una actividad que se repita '
                .'lo suficiente como para señalarla como sostenida.';
        }

        return $frases;
    }

    /** @param  array<string, mixed>  $analisis */
    private static function aMejorar(array $analisis): array
    {
        $frases = [];

        $menores = collect($analisis['actividades'])
            ->where('pct', '<', 100)
            ->sortBy('pct')
            ->take(4)
            ->map(fn (array $fila) => $fila['actividad'].' ('.$fila['pct'].'% de '.$fila['total'].')')
            ->all();

        if ($menores !== []) {
            $frases[] = 'Las actividades con menor cumplimiento fueron: '.implode(' · ', $menores).'.';
        }

        $franjas = collect($analisis['franjas'])->where('total', '>', 0)->sortBy('pct');
        $menor = $franjas->first();

        if ($menor && $franjas->count() > 1 && $menor['pct'] < 80) {
            $frases[] = 'La franja con menor cumplimiento fue la '.mb_strtolower($menor['franja'])
                .', con '.$menor['pct'].'% ('.$menor['hechas'].' de '.$menor['total'].').';
        }

        $pendientes = collect($analisis['objetivos'])
            ->flatMap(fn (array $fila) => $fila['pendientes'])
            ->take(3)
            ->all();

        if ($pendientes !== []) {
            $frases[] = 'Quedaron objetivos pendientes: '.self::enumerar($pendientes).'.';
        }

        if ($frases === []) {
            $frases[] = 'En los registros analizados no se observa una caída sostenida: '
                .'ninguna actividad ni franja del día baja de forma reiterada.';
        }

        return $frases;
    }

    /** @param  array<string, mixed>  $analisis */
    private static function metas(array $analisis): array
    {
        return collect($analisis['objetivos'])
            ->map(fn (array $fila) => $fila['tipo'].': '.$fila['hechos'].' de '.$fila['total']
                .' alcanzados ('.$fila['pct'].'%).')
            ->values()
            ->all();
    }

    /** @param  array<string, mixed>  $analisis */
    private static function energia(array $analisis): array
    {
        $niveles = $analisis['porEnergia'];

        if (count($niveles) < 2) {
            return ['Todos los registros del período corresponden al mismo nivel de energía, '
                .'por lo que no hay comparación posible entre niveles.'];
        }

        $alto = collect($niveles)->first();
        $bajo = collect($niveles)->last();

        return [
            'Los registros con energía '.mb_strtolower($alto['energia']).' promedian un rendimiento de '
                .$alto['rendimiento'].' ('.$alto['dias'].($alto['dias'] === 1 ? ' día' : ' días')
                .'), mientras que los de energía '.mb_strtolower($bajo['energia']).' promedian '
                .$bajo['rendimiento'].' ('.$bajo['dias'].($bajo['dias'] === 1 ? ' día' : ' días').').',
        ];
    }

    /** @param  array<string, mixed>  $analisis */
    private static function procrastinacion(array $analisis): array
    {
        $global = $analisis['global'];
        $procrastinacion = $analisis['procrastinacion'];
        $critico = $analisis['puntoCritico'];

        if ((int) $global['senales'] === 0) {
            return ['No se registraron señales de procrastinación en el período, '
                .'por lo que su relación con el rendimiento no puede medirse.'];
        }

        $frases = [];

        $frases[] = 'Se registraron '.$global['senales'].' señales de procrastinación, '
            .'un promedio de '.$global['senales_promedio'].' por día, presentes en '
            .$procrastinacion['dias_con_senales'].' de '.$procrastinacion['dias'].' días.';

        $preguntas = collect($procrastinacion['preguntas'])->where('señales', '>', 0)->take(3);

        if ($preguntas->isNotEmpty()) {
            $frases[] = 'Las preguntas marcadas con más frecuencia fueron: '.$preguntas
                ->map(fn (array $p) => mb_strtolower($p['pregunta']).' ('.$p['señales'].')')
                ->implode(' · ').'.';
        }

        $aFavor = $critico['aFavor'];
        $enContra = $critico['enContra'];

        if ($aFavor && $enContra) {
            $frases[] = 'El punto crítico se ubica entre '.$aFavor['senales'].' y '.$enContra['senales']
                .' señales: hasta '.$aFavor['senales'].' el rendimiento se mantiene en '
                .$aFavor['rendimiento'].', y desde '.$enContra['senales'].' desciende a '
                .$enContra['rendimiento'].', una diferencia de '.$critico['caida'].' puntos.';
        } elseif ($aFavor) {
            $frases[] = 'Con hasta '.$aFavor['senales'].' señales el rendimiento se mantiene en '
                .$aFavor['rendimiento'].'. En los registros de este período no se observa '
                .'todavía un descenso sostenido.';
        } else {
            $frases[] = 'Con los registros de este período el punto crítico todavía no puede '
                .'ubicarse con precisión: hacen falta más días con y sin señales para comparar.';
        }

        return $frases;
    }

    /** @param  array<string, mixed>  $analisis */
    private static function tendencia(array $analisis): array
    {
        $dias = $analisis['detalle'];

        if (count($dias) < 2) {
            return ['Con un solo registro no hay una evolución que comparar.'];
        }

        $mitad = (int) floor(count($dias) / 2);
        $primera = round((float) collect(array_slice($dias, 0, $mitad))->avg('rendimiento'), 1);
        $segunda = round((float) collect(array_slice($dias, $mitad))->avg('rendimiento'), 1);
        $diferencia = round($segunda - $primera, 1);

        $sentido = match (true) {
            $diferencia > 5 => 'un aumento',
            $diferencia < -5 => 'un descenso',
            default => 'una variación menor',
        };

        return [
            'Entre la primera y la segunda parte del período el rendimiento pasó de '.$primera
            .' a '.$segunda.', '.$sentido.' de '.abs($diferencia).' puntos. '
            .'Es la comparación entre dos mitades del mismo período, no una proyección.',
        ];
    }

    /** "a, b y c" */
    private static function enumerar(array $items): string
    {
        $items = array_values(array_filter($items));

        if (count($items) <= 1) {
            return (string) ($items[0] ?? '');
        }

        $ultimo = array_pop($items);

        return implode(', ', $items).' y '.$ultimo;
    }
}
