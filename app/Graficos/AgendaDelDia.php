<?php

namespace App\Graficos;

/**
 * Agenda del día para el gráfico de tiempo.
 *
 * De las franjas del horario saca los tramos (qué actividad, desde qué hora
 * hasta qué hora, si está cumplida y de qué color) y arma el JSON que usa el
 * navegador para dibujar. La misma clase genera el PNG que va dentro del PDF,
 * así los dos gráficos salen siempre de los mismos datos.
 *
 * Las horas se manejan en minutos desde las 00:00: es más simple de ubicar y
 * de comparar que andar con cadenas.
 */
class AgendaDelDia
{
    /** Colores institucionales, uno por actividad, en orden. */
    public const PALETA = [
        '#0080D0', // azul
        '#F0600C', // naranja
        '#00AE9C', // turquesa
        '#002060', // azul oscuro
        '#009CE4', // celeste
        '#D90B0B', // rojo
        '#006C60', // turquesa oscuro
        '#FC9000', // naranja claro
    ];

    /** Minutos que dura la última actividad si no hay otra después. */
    public const DURACION_ULTIMA = 60;

    /** Gris del texto y de las líneas de hora. */
    public const GRIS_LINEA = '#CFD9E6';

    public const GRIS_TEXTO = '#6B7A90';

    /**
     * Convierte las franjas del horario en tramos ordenados.
     *
     * Cada actividad va desde su hora hasta la hora de la siguiente: así el día
     * queda pintado de punta a punta, como en la hoja impresa. Si dos empiezan
     * a la misma hora, la primera se queda con una hora de duración y se
     * cruzan: ese cruce se pinta transparente.
     *
     * @param  iterable<int, array{start_time?: mixed, activity?: mixed, is_done?: mixed}>  $franjas
     * @return array<int, array{actividad: string, inicio: int, fin: int, hecho: bool, color: string, cruce: bool, hora_inicio: string, hora_fin: string}>
     */
    public static function tramos(iterable $franjas): array
    {
        $items = [];
        $orden = 0;

        foreach ($franjas as $franja) {
            $inicio = self::aMinutos($franja['start_time'] ?? null);

            if ($inicio === null) {
                continue;
            }

            $items[] = [
                'actividad' => trim((string) ($franja['activity'] ?? '')),
                'inicio' => $inicio,
                'hecho' => (bool) ($franja['is_done'] ?? false),
                'orden' => $orden++,
            ];
        }

        usort($items, fn (array $a, array $b) => $a['inicio'] <=> $b['inicio'] ?: $a['orden'] <=> $b['orden']);

        $total = count($items);

        // Primero se cierra cada tramo (necesita el inicio del siguiente) y
        // recién después se mira si se cruzan: el cruce compara finales, así que
        // todos tienen que estar puestos.
        foreach ($items as $indice => &$item) {
            $siguiente = $items[$indice + 1]['inicio'] ?? null;
            $fin = $siguiente ?? ($item['inicio'] + self::DURACION_ULTIMA);

            // Si la siguiente empieza antes (o a la misma hora), se le da una
            // hora para que el tramo se vea y quede marcado el cruce.
            if ($fin <= $item['inicio']) {
                $fin = $item['inicio'] + self::DURACION_ULTIMA;
            }

            $item['fin'] = $fin;
        }

        unset($item);

        foreach ($items as $indice => &$item) {
            $item['color'] = self::PALETA[$indice % count(self::PALETA)];
            $item['hora_inicio'] = self::aHora($item['inicio']);
            $item['hora_fin'] = self::aHora($item['fin']);
            $item['cruce'] = self::seCruza($items, $indice);
        }

        unset($item);

        return $items;
    }

    /** ¿Este tramo se pisa con algún otro? */
    private static function seCruza(array $items, int $indice): bool
    {
        $mio = $items[$indice];

        foreach ($items as $otroIndice => $otro) {
            if ($otroIndice === $indice) {
                continue;
            }

            if ($otro['inicio'] < $mio['fin'] && $mio['inicio'] < $otro['fin']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Los tramos partidos en pedazos, para poder pintar transparente sólo la
     * parte donde dos actividades se pisan.
     *
     * @return array<int, array{tramo: int, inicio: int, fin: int, cruce: bool, color: string, hecho: bool, actividad: string, hora_inicio: string, hora_fin: string}>
     */
    public static function piezas(array $tramos): array
    {
        if ($tramos === []) {
            return [];
        }

        // Todos los cortes: cada inicio y cada fin.
        $cortes = [];

        foreach ($tramos as $tramo) {
            $cortes[$tramo['inicio']] = true;
            $cortes[$tramo['fin']] = true;
        }

        $cortes = array_keys($cortes);
        sort($cortes);

        $piezas = [];

        for ($i = 0; $i < count($cortes) - 1; $i++) {
            $desde = $cortes[$i];
            $hasta = $cortes[$i + 1];

            // Qué tramos cubren este pedacito.
            $encima = [];

            foreach ($tramos as $indice => $tramo) {
                if ($tramo['inicio'] <= $desde && $hasta <= $tramo['fin']) {
                    $encima[] = $indice;
                }
            }

            foreach ($encima as $indice) {
                $tramo = $tramos[$indice];

                $piezas[] = [
                    'tramo' => $indice,
                    'inicio' => $desde,
                    'fin' => $hasta,
                    'cruce' => count($encima) > 1,
                    'color' => $tramo['color'],
                    'hecho' => $tramo['hecho'],
                    'actividad' => $tramo['actividad'],
                    'hora_inicio' => $tramo['hora_inicio'],
                    'hora_fin' => $tramo['hora_fin'],
                ];
            }
        }

        return $piezas;
    }

    /**
     * El JSON que consume el gráfico del navegador.
     *
     * Es la misma estructura que viaja al servidor cuando se guarda el diario,
     * así el gráfico y la base hablan del mismo objeto.
     *
     * @param  iterable<int, array{start_time?: mixed, activity?: mixed, is_done?: mixed}>  $franjas
     * @return array{inicio: int, fin: int, tramos: array<int, mixed>, piezas: array<int, mixed>}
     */
    public static function datos(iterable $franjas): array
    {
        $tramos = self::tramos($franjas);

        if ($tramos === []) {
            return ['inicio' => 7 * 60, 'fin' => 19 * 60, 'tramos' => [], 'piezas' => []];
        }

        $inicio = $tramos[0]['inicio'];
        $fin = max(array_column($tramos, 'fin'));

        // Un poco de aire a cada lado y los bordes en horas redondas.
        $inicio = (int) (floor($inicio / 60) * 60);
        $fin = (int) (ceil($fin / 60) * 60);

        if ($fin - $inicio < 120) {
            $fin = $inicio + 120;
        }

        return [
            'inicio' => $inicio,
            'fin' => $fin,
            'tramos' => $tramos,
            'piezas' => self::piezas($tramos),
        ];
    }

    /** Minutos desde las 00:00 a partir de "8:15", "08:15" o "08:15:00". */
    public static function aMinutos(mixed $hora): ?int
    {
        if ($hora === null || $hora === '') {
            return null;
        }

        if (is_string($hora) && preg_match('/^\s*(\d{1,2}):(\d{2})/', $hora, $partes)) {
            $horas = (int) $partes[1];
            $minutos = (int) $partes[2];

            if ($horas > 23 || $minutos > 59) {
                return null;
            }

            return ($horas * 60) + $minutos;
        }

        return null;
    }

    /** 495 -> "08:15" */
    public static function aHora(int $minutos): string
    {
        return sprintf('%02d:%02d', intdiv($minutos, 60) % 24, $minutos % 60);
    }
}
