<?php

namespace App\Reportes;

use App\Helpers\Helper;

/**
 * La redacción del resumen: convierte los números del análisis en frases.
 *
 * Va aparte de AnalisisDeDesempeno a propósito: aquel calcula y éste escribe.
 *
 * El tono es neutral, diplomático y clínico en el mejor sentido: describe lo
 * que los registros muestran, sin juzgar a la persona y sin dramatizar. Ni "no
 * hiciste nada" ni "¡vas genial!". Las frases evitan los imperativos y las
 * valoraciones, distinguen asociación de causa y, cuando un dato no alcanza
 * para afirmar algo, lo dicen con la misma serenidad.
 *
 * El dictamen clínico y ético no diagnostica: lee los registros, explicita sus
 * límites y cuida la dignidad de quien se está mirando en ellos.
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
            'actividades' => 'Actividades diarias y hábitos',
            'bien' => 'Aspectos que se sostuvieron',
            'mal' => 'Aspectos a mejorar',
            'metas' => 'Metas y objetivos alcanzados',
            'energia' => 'Relación con la energía del día',
            'procrastinacion' => 'Procrastinación y rendimiento',
            'dictamen' => 'Dictamen clínico y ético',
            'recomendaciones' => 'Recomendaciones para la próxima semana',
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
            'actividades' => self::actividades($analisis),
            'bien' => self::sostenido($analisis),
            'mal' => self::aMejorar($analisis),
            'metas' => self::metas($analisis),
            'energia' => self::energia($analisis),
            'procrastinacion' => self::procrastinacion($analisis),
            'dictamen' => self::dictamen($analisis),
            'recomendaciones' => self::recomendaciones($analisis),
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

        if ((int) $global['actividades_total'] > 0) {
            $frases[] = 'De las actividades diarias se marcaron '.$global['actividades_hechas'].' de '
                .$global['actividades_total'].' ('.$global['actividades_pct'].'%)'
                .($global['actividad_minutos'] > 0
                    ? ', con '.Helper::minutesToHuman($global['actividad_minutos']).' registrados en total.'
                    : '.');
        }

        if ((int) $global['dias_con_meta_semanal'] > 0) {
            $frases[] = 'La meta semanal quedó registrada en '.$global['dias_con_meta_semanal'].' de '
                .$dias.' días del período.';
        }

        return $frases;
    }

    /** @param  array<string, mixed>  $analisis */
    private static function actividades(array $analisis): array
    {
        $actividades = collect($analisis['actividadesDiarias'] ?? []);

        if ($actividades->isEmpty()) {
            return ['En el período no se registraron actividades diarias, '
                .'así que no hay hábitos que comparar todavía.'];
        }

        $frases = [];

        $sostenidas = $actividades
            ->where('pct', '>=', 80)
            ->sortByDesc('pct')
            ->take(4)
            ->map(fn (array $fila) => mb_strtolower($fila['actividad']).' ('.$fila['pct'].'%)')
            ->all();

        if ($sostenidas !== []) {
            $frases[] = 'Las actividades que más se sostuvieron fueron: '.self::enumerar($sostenidas).'.';
        }

        $bajas = $actividades
            ->where('pct', '<', 60)
            ->sortBy('pct')
            ->take(4)
            ->map(fn (array $fila) => mb_strtolower($fila['actividad']).' ('.$fila['pct'].'%)')
            ->all();

        if ($bajas !== []) {
            $frases[] = 'Las que más se postergaron fueron: '.implode(' · ', $bajas).'.';
        }

        $minutos = $actividades
            ->filter(fn (array $fila) => ($fila['minutos_promedio'] ?? 0) > 0)
            ->sortByDesc('minutos_promedio')
            ->take(3)
            ->map(fn (array $fila) => mb_strtolower($fila['actividad']).' ('
                .round($fila['minutos_promedio']).' min por día)')
            ->all();

        if ($minutos !== []) {
            $frases[] = 'El tiempo dedicado se concentró en '.self::enumerar($minutos).'.';
        }

        // Una lectura por familia, sólo si hay más de una.
        $categorias = $actividades
            ->groupBy('categoria_label')
            ->map(fn ($grupo, $categoria) => [
                'categoria' => $categoria,
                'pct' => round($grupo->avg('pct'), 1),
            ])
            ->sortByDesc('pct')
            ->values();

        if ($categorias->count() > 1) {
            $mejor = $categorias->first();
            $peor = $categorias->last();

            $frases[] = 'Por familia, la de mayor cumplimiento fue '.mb_strtolower($mejor['categoria'])
                .' ('.$mejor['pct'].'%) y la de menor, '.mb_strtolower($peor['categoria'])
                .' ('.$peor['pct'].'%).';
        }

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
            $frases[] = 'Se cumplieron en todos los registros del horario: '.self::enumerar($siempre).'.';
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
            $frases[] = 'Las actividades del horario con menor cumplimiento fueron: '.implode(' · ', $menores).'.';
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
        $frases = collect($analisis['objetivos'])
            ->map(fn (array $fila) => $fila['tipo'].': '.$fila['hechos'].' de '.$fila['total']
                .' alcanzados ('.$fila['pct'].'%).')
            ->values()
            ->all();

        $semanas = collect($analisis['metasSemanales'] ?? []);

        if ($semanas->isNotEmpty()) {
            $conMeta = $semanas->filter(fn (array $semana) => ! Helper::isBlank($semana['goal']));
            $conSeguimiento = (int) $semanas->sum('dias_con_seguimiento');

            if ($conMeta->isNotEmpty()) {
                $frases[] = 'Se registraron '.$conMeta->count().' meta(s) semanal(es) en el período'
                    .($conSeguimiento > 0
                        ? ', con '.$conSeguimiento.' día(s) de seguimiento personal escrito.'
                        : '.');
            } else {
                $frases[] = 'Las semanas del período no tienen la meta semanal escrita.';
            }
        }

        $reflexiones = collect($analisis['reflexionesSemanales'] ?? []);

        if ($reflexiones->isNotEmpty()) {
            $completas = $reflexiones->filter(fn (array $fila) => $fila['respondidas'] > 0);
            $frases[] = 'La reflexión semanal se respondió en '.$completas->count().' de '
                .$reflexiones->count().' semana(s) del período.';
        }

        return array_values(array_filter($frases));
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

        $frases = [
            'Los registros con energía '.mb_strtolower($alto['energia']).' promedian un rendimiento de '
                .$alto['rendimiento'].' ('.$alto['dias'].($alto['dias'] === 1 ? ' día' : ' días')
                .'), mientras que los de energía '.mb_strtolower($bajo['energia']).' promedian '
                .$bajo['rendimiento'].' ('.$bajo['dias'].($bajo['dias'] === 1 ? ' día' : ' días').').',
        ];

        if ((int) ($analisis['global']['actividades_total'] ?? 0) > 0) {
            $frases[] = 'Las actividades diarias siguen el mismo orden: '.$alto['actividades_pct'].'% de '
                .'cumplimiento con energía '.mb_strtolower($alto['energia']).' frente a '
                .$bajo['actividades_pct'].'% con energía '.mb_strtolower($bajo['energia']).'.';
        }

        return $frases;
    }

    /** @param  array<string, mixed>  $analisis */
    private static function procrastinacion(array $analisis): array
    {
        $global = $analisis['global'];
        $procrastinacion = $analisis['procrastinacion'];
        $critico = $analisis['puntoCritico'];
        $correlacion = $analisis['correlacion'] ?? null;
        $cruce = $analisis['procrastinacionVsActividades'] ?? null;

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

        if ($correlacion && $correlacion['r'] !== null) {
            $frases[] = $correlacion['lectura'].' Se trata de una asociación, no de una relación causal.';
        }

        if ($cruce && $cruce['brecha_actividades'] !== null) {
            $frases[] = 'Los días con señales promediaron '.$cruce['con_senales']['actividades_pct'].'% de '
                .'actividades diarias, frente a '.$cruce['sin_senales']['actividades_pct'].'% de los días sin '
                .'señales: una diferencia de '.abs($cruce['brecha_actividades']).' puntos porcentuales.';
        }

        return $frases;
    }

    /**
     * El dictamen clínico y ético.
     *
     * No diagnostica ni etiqueta: lee los registros con cuidado, distingue lo
     * que se observa de lo que no puede afirmarse y deja explícito el marco
     * ético del documento. Es el apartado que explica cómo leer todo lo demás.
     *
     * @param  array<string, mixed>  $analisis
     */
    private static function dictamen(array $analisis): array
    {
        $global = $analisis['global'];
        $dias = (int) $analisis['dias'];
        $critico = $analisis['puntoCritico'];
        $correlacion = $analisis['correlacion'] ?? null;
        $cruce = $analisis['procrastinacionVsActividades'] ?? null;
        $impactos = collect($analisis['impactoActividades'] ?? []);
        $actividades = collect($analisis['actividadesDiarias'] ?? []);

        $frases = [];

        $frases[] = 'Este dictamen no es un diagnóstico clínico ni una evaluación psicológica: '
            .'es una lectura de los registros que la propia persona escribió, con el fin de '
            .'devolverle información útil y tratable con cuidado.';

        $frases[] = 'En '.$dias.($dias === 1 ? ' día' : ' días').' de registro, el rendimiento promedio '
            .'fue de '.$global['rendimiento'].' sobre 100. La constancia del horario ('.$global['horario_pct']
            .'%) y de los objetivos ('.$global['objetivos_pct'].'%) describe una persona que '
            .(($global['horario_pct'] + $global['objetivos_pct']) / 2 >= 60
                ? 'sostiene una base de cumplimiento y que, como cualquiera, tiene días dispares.'
                : 'está construyendo su base de cumplimiento; los días dispares también forman parte del proceso.');

        if ((int) $global['senales'] > 0) {
            $sentido = $correlacion && $correlacion['r'] !== null
                ? ' La asociación observada es '.$correlacion['fuerza']
                    .($correlacion['sentido'] === 'inversa' ? ' e inversa' : '')
                    .' (r = '.$correlacion['r'].'), lo que significa que a más señales, '
                    .($correlacion['sentido'] === 'inversa'
                        ? 'menor rendimiento tiende a observarse en estos registros.'
                        : 'el rendimiento tiende a acompañar de otra manera; conviene leerlo con cautela.')
                : '';

            $frases[] = 'Sobre la procrastinación: las señales marcadas no describen un rasgo de la persona, '
                .'sino momentos en que una tarea se vuelve difícil de sostener.'.$sentido;

            if ($critico['aFavor'] && $critico['enContra']) {
                $frases[] = 'El registro sugiere un umbral práctico: hasta '.$critico['aFavor']['senales']
                    .' señales el rendimiento se mantiene en '.$critico['aFavor']['rendimiento']
                    .', y a partir de '.$critico['enContra']['senales'].' desciende a '
                    .$critico['enContra']['rendimiento'].'. Ese umbral es una observación del período, '
                    .'no una regla fija.';
            }

            if ($cruce && $cruce['brecha_actividades'] !== null) {
                $frases[] = 'Los días con señales también muestran menos actividades diarias sostenidas '
                    .'('.$cruce['con_senales']['actividades_pct'].'% frente a '
                    .$cruce['sin_senales']['actividades_pct'].'%). La coincidencia es consistente con '
                    .'la idea de que los hábitos pequeños protegen el ánimo de arranque, pero estos '
                    .'registros no permiten afirmar una causa.';
            }
        } else {
            $frases[] = 'Sobre la procrastinación: no se marcaron señales en el período. Eso no equivale '
                .'a "no procrastinar"; significa que la pregunta no se respondió o que no se reconoció '
                .'como señal.';
        }

        if ($impactos->isNotEmpty()) {
            $mejor = $impactos->first();

            if ($mejor['diferencia'] !== null) {
                $frases[] = 'Sobre las actividades que se cumplieron: los días en que se sostuvo '
                    .mb_strtolower($mejor['actividad']).' promediaron '.$mejor['rendimiento_con']
                    .' puntos, frente a '.$mejor['rendimiento_sin'].' de los días en que no se marcó '
                    .'('.$mejor['dias_con'].' y '.$mejor['dias_sin'].' días, respectivamente). '
                    .'Es una pista para probar, no una receta.';
            }
        }

        if ($actividades->isNotEmpty()) {
            $menor = $actividades->sortBy('pct')->first();

            $frases[] = 'La actividad con menor continuidad fue '.mb_strtolower($menor['actividad'])
                .' ('.$menor['pct'].'% de los días en que estaba disponible). Nombrarla no es un '
                .'reproche: es el punto donde una acción mínima puede hacer más diferencia.';
        }

        $frases[] = 'Lo que estos datos no dicen: no miden salud mental, no distinguen enfermedad de '
            .'cansancio, no capturan el contexto (sueño, trabajo, cuidados, duelo) y dependen de lo que '
            .'se recuerda y se anima a marcar. Un período corto o con pocos registros no alcanza para '
            .'generalizar.';

        $frases[] = 'Marco ético: el registro pertenece a quien lo escribe. Se lee sin juicio, se guarda '
            .'con cuidado y no se usa para etiquetar a nadie. Si el cansancio, la postergación persistente '
            .'o el malestar afectan el ánimo, el sueño o la salud, corresponde acompañamiento profesional.';

        return $frases;
    }

    /**
     * Recomendaciones concretas, derivadas de los propios registros.
     *
     * Son sugerencias, no indicaciones: cada una se apoya en un dato del
     * período y se puede probar o descartar.
     *
     * @param  array<string, mixed>  $analisis
     */
    private static function recomendaciones(array $analisis): array
    {
        $global = $analisis['global'];
        $actividades = collect($analisis['actividadesDiarias'] ?? []);
        $impactos = collect($analisis['impactoActividades'] ?? []);
        $critico = $analisis['puntoCritico'];
        $energia = collect($analisis['porEnergia']);
        $semanas = collect($analisis['metasSemanales'] ?? []);

        $frases = [];

        $menor = $actividades->sortBy('pct')->first();

        if ($menor) {
            $frases[] = 'Elegir una sola actividad de las de menor continuidad ('.mb_strtolower($menor['actividad'])
                .') y anclarla a algo que ya ocurre todos los días: después del desayuno, al cerrar el trabajo '
                .'o antes de dormir. Una acción de dos minutos cuenta.';
        }

        $mejor = $impactos->first();

        if ($mejor && $mejor['diferencia'] !== null && $mejor['diferencia'] > 0) {
            $frases[] = 'Los días en que se sostuvo '.mb_strtolower($mejor['actividad']).' rindieron '
                .$mejor['diferencia'].' puntos más en promedio. Vale la pena tratarla como actividad ancla '
                .'y protegerle un lugar fijo.';
        }

        if ($critico['enContra']) {
            $frases[] = 'Cuando aparezcan '.$critico['enContra']['senales'].' señales o más, reducir el '
                .'bloque de acción a 15 minutos y escribir la primera acción concreta. El objetivo es '
                .'empezar, no terminar.';
        }

        if ($energia->count() > 1) {
            $baja = $energia->last();

            $frases[] = 'Reservar las tareas que exigen más concentración para los días de energía alta y '
                .'dejar en los de energía '.mb_strtolower($baja['energia']).' las acciones de mantenimiento '
                .'(orden, caminata corta, respiración).';
        }

        if ($semanas->isNotEmpty()) {
            $frases[] = 'Revisar la meta semanal el domingo y traducirla en una acción diaria concreta: '
                .'la meta se sostiene con el seguimiento de cada día, no con el enunciado.';
        }

        $frases[] = 'Mantener el registro aunque el día no salga como se esperaba. Los días flojos también '
            .'son información: permiten ver qué se sostiene cuando la energía baja.';

        $frases[] = 'Estas recomendaciones son sugerencias basadas en los registros del período, no '
            .'indicaciones clínicas. Se pueden probar, ajustar o descartar.';

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
