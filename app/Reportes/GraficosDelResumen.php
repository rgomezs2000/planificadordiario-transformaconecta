<?php

namespace App\Reportes;

/**
 * Los gráficos del resumen, dibujados en el servidor.
 *
 * El PDF no ejecuta JavaScript, así que cada gráfico sale como imagen ya hecha
 * (igual que la línea de tiempo del día) y viaja incrustado en el documento.
 * Todos usan la misma fuente que el texto del PDF y la paleta institucional.
 */
class GraficosDelResumen
{
    public const ANCHO = 1040;

    public const ALTO = 300;

    public const MARGEN_IZQ = 46;

    public const MARGEN_DER = 18;

    public const MARGEN_SUP = 26;

    public const MARGEN_INF = 46;

    /** Colores de la marca. */
    public const AZUL = '#0080D0';

    public const AZUL_OSCURO = '#002060';

    public const TURQUESA = '#00AE9C';

    public const NARANJA = '#F0600C';

    public const ROJO = '#D90B0B';

    public const AMARILLO = '#F2B705';

    public const GRIS_LINEA = '#CFD9E6';

    public const GRIS_TEXTO = '#6B7A90';

    /* ==================================================================== */
    /*  Performance vs procrastinación */
    /* ==================================================================== */

    /**
     * Cada punto es el rendimiento promedio de los días según cuántas señales
     * de procrastinación marcaron. Se marcan los dos puntos críticos: hasta
     * dónde no cuesta (a favor) y desde dónde se cae (en contra).
     *
     * @param  array<string, mixed>  $analisis
     */
    public static function performanceVsProcrastinacion(array $analisis): string
    {
        $grupos = $analisis['puntoCritico']['grupos'] ?? [];

        [$lienzo, $fuente, $fuenteNegrita] = self::lienzo();

        $izquierda = self::MARGEN_IZQ;
        $derecha = self::ANCHO - self::MARGEN_DER;
        $arriba = self::MARGEN_SUP;
        $abajo = self::ALTO - self::MARGEN_INF;

        $maxSenales = max(1, (int) collect($grupos)->max('senales'));
        $x = fn (float $senales): float => $izquierda + ($senales / $maxSenales) * ($derecha - $izquierda);
        $y = fn (float $rendimiento): float => $abajo - ($rendimiento / 100) * ($abajo - $arriba);

        // Rejilla horizontal cada 25 puntos.
        for ($valor = 0; $valor <= 100; $valor += 25) {
            imageline($lienzo, $izquierda, (int) round($y($valor)), $derecha, (int) round($y($valor)),
                self::color($lienzo, self::GRIS_LINEA));
            self::texto($lienzo, $fuente, 8, self::color($lienzo, self::GRIS_TEXTO),
                $izquierda - 8, (int) round($y($valor)) + 3, $valor.'%', 'derecha');
        }

        // Rejilla vertical por cantidad de señales.
        for ($senales = 0; $senales <= $maxSenales; $senales++) {
            imageline($lienzo, (int) round($x($senales)), $arriba, (int) round($x($senales)), $abajo,
                self::color($lienzo, self::GRIS_LINEA));
            self::texto($lienzo, $fuente, 8, self::color($lienzo, self::GRIS_TEXTO),
                $x($senales), $abajo + 16, (string) $senales, 'centro');
        }

        self::texto($lienzo, $fuente, 9, self::color($lienzo, self::GRIS_TEXTO),
            ($izquierda + $derecha) / 2, $abajo + 34,
            'Señales de procrastinación marcadas en el día', 'centro');

        if ($grupos === []) {
            self::texto($lienzo, $fuente, 11, self::color($lienzo, self::GRIS_TEXTO),
                ($izquierda + $derecha) / 2, ($arriba + $abajo) / 2,
                'Sin datos suficientes para el cruce', 'centro');

            return self::binario($lienzo);
        }

        // La referencia: el rendimiento medio de todo el período.
        $referencia = (float) ($analisis['puntoCritico']['referencia'] ?? 0);

        for ($px = $izquierda; $px < $derecha; $px += 12) {
            imageline($lienzo, $px, (int) round($y($referencia)), $px + 6, (int) round($y($referencia)),
                self::color($lienzo, self::AZUL_OSCURO));
        }

        self::texto($lienzo, $fuenteNegrita, 8, self::color($lienzo, self::AZUL_OSCURO),
            $derecha, (int) round($y($referencia)) - 6, 'promedio '.$referencia.'%', 'derecha');

        // La línea que une los promedios de cada grupo.
        $anterior = null;

        foreach ($grupos as $grupo) {
            $punto = [$x((float) $grupo['senales']), $y((float) $grupo['rendimiento'])];

            if ($anterior !== null) {
                imageline($lienzo, (int) round($anterior[0]), (int) round($anterior[1]),
                    (int) round($punto[0]), (int) round($punto[1]), self::color($lienzo, self::AZUL));
            }

            $anterior = $punto;
        }

        // Los puntos, con su color según lo que representan.
        foreach ($grupos as $grupo) {
            $aFavor = ($analisis['puntoCritico']['aFavor']['senales'] ?? null) === $grupo['senales'];
            $enContra = ($analisis['puntoCritico']['enContra']['senales'] ?? null) === $grupo['senales'];

            $color = $enContra ? self::ROJO : ($aFavor ? self::TURQUESA : self::AZUL);
            $radio = ($aFavor || $enContra) ? 7 : 5;

            $cx = (int) round($x((float) $grupo['senales']));
            $cy = (int) round($y((float) $grupo['rendimiento']));

            imagefilledellipse($lienzo, $cx, $cy, $radio * 2, $radio * 2, self::color($lienzo, $color));

            $etiqueta = $grupo['rendimiento'].'%';
            self::texto($lienzo, $fuenteNegrita, 8, self::color($lienzo, $color),
                $cx, $cy - 12, $etiqueta, 'centro');
        }

        // Los dos puntos críticos, con su cartel. Van abajo a la izquierda
        // porque la curva arranca arriba: ahí no tapan ningún punto.
        self::cartel($lienzo, $fuenteNegrita, $izquierda + 6, $abajo - 46, self::TURQUESA,
            'A favor: hasta '.($analisis['puntoCritico']['aFavor']['senales'] ?? '—').' señales no cuesta');

        self::cartel($lienzo, $fuenteNegrita, $izquierda + 6, $abajo - 24, self::ROJO,
            ($analisis['puntoCritico']['enContra'] ?? null)
                ? 'En contra: desde '.$analisis['puntoCritico']['enContra']['senales'].' señales cae '
                    .($analisis['puntoCritico']['caida'] ?? 0).' puntos'
                : 'En contra: no se observó caída con estos días');

        return self::binario($lienzo);
    }

    /* ==================================================================== */
    /*  Rendimiento día por día */
    /* ==================================================================== */

    /**
     * Una barra por día, para ver la evolución.
     *
     * @param  array<string, mixed>  $analisis
     */
    public static function rendimientoPorDia(array $analisis): string
    {
        $dias = $analisis['detalle'] ?? [];

        [$lienzo, $fuente, $fuenteNegrita] = self::lienzo();

        $izquierda = self::MARGEN_IZQ;
        $derecha = self::ANCHO - self::MARGEN_DER;
        $arriba = self::MARGEN_SUP;
        $abajo = self::ALTO - self::MARGEN_INF;

        for ($valor = 0; $valor <= 100; $valor += 25) {
            $linea = (int) round($abajo - ($valor / 100) * ($abajo - $arriba));
            imageline($lienzo, $izquierda, $linea, $derecha, $linea, self::color($lienzo, self::GRIS_LINEA));
            self::texto($lienzo, $fuente, 8, self::color($lienzo, self::GRIS_TEXTO),
                $izquierda - 8, $linea + 3, $valor.'%', 'derecha');
        }

        if ($dias === []) {
            self::texto($lienzo, $fuente, 11, self::color($lienzo, self::GRIS_TEXTO),
                ($izquierda + $derecha) / 2, ($arriba + $abajo) / 2, 'Sin días para graficar', 'centro');

            return self::binario($lienzo);
        }

        $cantidad = count($dias);
        $anchoUtil = $derecha - $izquierda;
        $paso = $anchoUtil / $cantidad;
        $anchoBarra = max(3, min(46, $paso - 6));

        // Con muchos días no entran todas las fechas: se muestra una de cada tanto.
        $salto = (int) max(1, ceil($cantidad / 14));

        foreach (array_values($dias) as $indice => $dia) {
            $centro = $izquierda + ($paso * ($indice + 0.5));
            $alto = (($dia['rendimiento'] ?? 0) / 100) * ($abajo - $arriba);

            $color = match (true) {
                $dia['rendimiento'] >= 80 => self::TURQUESA,
                $dia['rendimiento'] >= 50 => self::AZUL,
                $dia['rendimiento'] >= 25 => self::AMARILLO,
                default => self::ROJO,
            };

            imagefilledrectangle($lienzo, (int) round($centro - $anchoBarra / 2), (int) round($abajo - $alto),
                (int) round($centro + $anchoBarra / 2), $abajo, self::color($lienzo, $color));

            if ($anchoBarra >= 20) {
                self::texto($lienzo, $fuenteNegrita, 7.5, self::color($lienzo, self::AZUL_OSCURO),
                    $centro, (int) round($abajo - $alto) - 5, (string) $dia['rendimiento'], 'centro');
            }

            if ($indice % $salto === 0) {
                // Sólo el día y el mes: el año se repite y no aporta.
                $etiqueta = mb_substr((string) $dia['fecha'], 8, 2).'/'.mb_substr((string) $dia['fecha'], 5, 2);
                self::texto($lienzo, $fuente, 7.5, self::color($lienzo, self::GRIS_TEXTO),
                    $centro, $abajo + 16, $etiqueta, 'centro');
            }
        }

        self::texto($lienzo, $fuente, 9, self::color($lienzo, self::GRIS_TEXTO),
            ($izquierda + $derecha) / 2, $abajo + 34, 'Rendimiento de cada día (0 a 100)', 'centro');

        return self::binario($lienzo);
    }

    /* ==================================================================== */
    /*  Rendimiento según la energía */
    /* ==================================================================== */

    /**
     * Barras horizontales: cómo rinde cada nivel de energía.
     *
     * @param  array<string, mixed>  $analisis
     */
    public static function rendimientoPorEnergia(array $analisis): string
    {
        $niveles = $analisis['porEnergia'] ?? [];

        [$lienzo, $fuente, $fuenteNegrita] = self::lienzo();

        $izquierda = self::MARGEN_IZQ;
        $derecha = self::ANCHO - self::MARGEN_DER - 90;
        $arriba = self::MARGEN_SUP + 6;
        $abajo = self::ALTO - self::MARGEN_INF;
        $anchoUtil = $derecha - $izquierda;

        if ($niveles === []) {
            self::texto($lienzo, $fuente, 11, self::color($lienzo, self::GRIS_TEXTO),
                ($izquierda + $derecha) / 2, ($arriba + $abajo) / 2, 'Sin días para comparar', 'centro');

            return self::binario($lienzo);
        }

        $alto = ($abajo - $arriba) / count($niveles);

        foreach (array_values($niveles) as $indice => $nivel) {
            $y = $arriba + ($alto * $indice) + ($alto / 2);
            $largo = (($nivel['rendimiento'] ?? 0) / 100) * $anchoUtil;

            // El fondo de la barra, para ver el 100%.
            imagefilledrectangle($lienzo, $izquierda, (int) round($y - 11), $derecha, (int) round($y + 11),
                self::color($lienzo, '#F1F5FA'));

            $color = match ($nivel['slug'] ?? '') {
                'baja' => self::ROJO,
                'media' => self::AMARILLO,
                'alta' => self::TURQUESA,
                default => self::AZUL,
            };

            imagefilledrectangle($lienzo, $izquierda, (int) round($y - 11), (int) round($izquierda + $largo),
                (int) round($y + 11), self::color($lienzo, $color));

            self::texto($lienzo, $fuenteNegrita, 9, self::color($lienzo, self::AZUL_OSCURO),
                $izquierda, (int) round($y - 17), $nivel['energia'], 'izquierda');

            self::texto($lienzo, $fuente, 8, self::color($lienzo, self::GRIS_TEXTO),
                $izquierda, (int) round($y + 25),
                $nivel['dias'].($nivel['dias'] === 1 ? ' día' : ' días').' · '.$nivel['horario_pct'].'% del horario',
                'izquierda');

            self::texto($lienzo, $fuenteNegrita, 11, self::color($lienzo, $color),
                $derecha + 10, (int) round($y + 4), $nivel['rendimiento'].'%', 'izquierda');
        }

        self::texto($lienzo, $fuente, 9, self::color($lienzo, self::GRIS_TEXTO),
            ($izquierda + $derecha) / 2, $abajo + 34, 'Rendimiento promedio según la energía del día', 'centro');

        return self::binario($lienzo);
    }

    /* ==================================================================== */
    /*  Herramientas de dibujo */
    /* ==================================================================== */

    /** data:image/png;base64,... para incrustar en el HTML del PDF. */
    public static function dataUri(string $png): string
    {
        return 'data:image/png;base64,'.base64_encode($png);
    }

    /** @return array{0: \GdImage, 1: string, 2: string} */
    private static function lienzo(): array
    {
        $lienzo = imagecreatetruecolor(self::ANCHO, self::ALTO);
        imagealphablending($lienzo, true);
        imagesavealpha($lienzo, true);
        imagefilledrectangle($lienzo, 0, 0, self::ANCHO, self::ALTO, self::color($lienzo, '#FFFFFF'));

        return [$lienzo, self::fuente(), self::fuente(true)];
    }

    /** Un cartelito de color con su texto, arriba a la izquierda del gráfico. */
    private static function cartel(\GdImage $lienzo, string $fuente, int $x, int $y, string $color, string $texto): void
    {
        imagefilledrectangle($lienzo, $x, $y, $x + 10, $y + 10, self::color($lienzo, $color));
        self::texto($lienzo, $fuente, 8, self::color($lienzo, self::AZUL_OSCURO), $x + 16, $y + 9, $texto, 'izquierda');
    }

    private static function binario(\GdImage $lienzo): string
    {
        ob_start();
        imagepng($lienzo);
        $contenido = (string) ob_get_clean();

        imagedestroy($lienzo);

        return $contenido;
    }

    private static function color(\GdImage $lienzo, string $hex, int $alfa = 0): int
    {
        $hex = ltrim($hex, '#');

        return (int) imagecolorallocatealpha(
            $lienzo,
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
            max(0, min(127, $alfa))
        );
    }

    private static function texto(\GdImage $lienzo, string $fuente, float $tamano, int $color, float $x, float $y, string $texto, string $alineacion): void
    {
        if ($alineacion === 'centro') {
            $x -= self::anchoTexto($fuente, $tamano, $texto) / 2;
        } elseif ($alineacion === 'derecha') {
            $x -= self::anchoTexto($fuente, $tamano, $texto);
        }

        imagettftext($lienzo, $tamano, 0, (int) round($x), (int) round($y), $color, $fuente, $texto);
    }

    private static function anchoTexto(string $fuente, float $tamano, string $texto): float
    {
        $caja = imagettfbbox($tamano, 0, $fuente, $texto);

        return $caja === false ? 0.0 : (float) abs($caja[2] - $caja[0]);
    }

    private static function fuente(bool $negrita = false): string
    {
        $archivo = $negrita ? 'DejaVuSans-Bold.ttf' : 'DejaVuSans.ttf';
        $ruta = base_path('vendor/dompdf/dompdf/lib/fonts/'.$archivo);

        return is_file($ruta) ? $ruta : 'C:\Windows\Fonts\arial'.($negrita ? 'bd' : '').'.ttf';
    }
}
