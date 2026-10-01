<?php

namespace App\Graficos;

/**
 * Dibuja la agenda del día como imagen PNG, para el PDF.
 *
 * El PDF no ejecuta JavaScript, así que el gráfico se dibuja en el servidor con
 * GD y viaja como imagen dentro del documento. Usa la misma fuente que el resto
 * del PDF (DejaVu Sans, la que trae dompdf) y los mismos datos y colores que el
 * gráfico de pantalla, para que los dos se vean igual.
 */
class AgendaPng
{
    public const ANCHO = 1040;

    /**
     * Alto del gráfico sin la leyenda. Es ajustado a propósito: con la leyenda
     * abajo, el PDF tiene que seguir entrando en una sola página.
     */
    public const ALTO = 196;

    public const MARGEN_LATERAL = 16;

    public const MARGEN_SUPERIOR = 30;

    public const MARGEN_INFERIOR = 30;

    public const ALTO_BARRA = 44;

    public const TAMANO_HORA = 8.5;

    public const TAMANO_LEYENDA = 7.5;

    /** Cuántas columnas tiene la leyenda que va debajo del gráfico. */
    public const COLUMNAS_LEYENDA = 3;

    /** Alto de cada renglón de la leyenda. */
    public const ALTO_LEYENDA = 13;

    /** Devuelve el PNG en binario. */
    public static function generar(array $datos): string
    {
        $ancho = self::ANCHO;

        // El lienzo se agranda para que entre la leyenda: el PDF no tiene
        // puntero, así que ahí los colores se explican con una leyenda.
        $tramos = $datos['tramos'] ?? [];
        $filasLeyenda = $tramos === [] ? 0 : (int) ceil(count($tramos) / self::COLUMNAS_LEYENDA);
        $alto = self::ALTO + ($filasLeyenda > 0 ? 12 + ($filasLeyenda * self::ALTO_LEYENDA) : 0);

        $lienzo = imagecreatetruecolor($ancho, $alto);
        imagealphablending($lienzo, true);
        imagesavealpha($lienzo, true);

        $blanco = imagecolorallocate($lienzo, 255, 255, 255);
        imagefilledrectangle($lienzo, 0, 0, $ancho, $alto, $blanco);

        $fuente = self::fuente();
        $fuenteNegrita = self::fuente(true);

        $izquierda = self::MARGEN_LATERAL;
        $derecha = $ancho - self::MARGEN_LATERAL;
        $arriba = self::MARGEN_SUPERIOR;
        $abajo = self::ALTO - self::MARGEN_INFERIOR;

        $desde = (int) $datos['inicio'];
        $hasta = (int) $datos['fin'];
        $rango = max(1, $hasta - $desde);
        $anchoUtil = $derecha - $izquierda;

        $x = fn (int $minutos): float => $izquierda + (($minutos - $desde) / $rango) * $anchoUtil;

        // 1. Las horas: una línea por hora (cada dos si el día es largo).
        $paso = $rango > 12 * 60 ? 120 : 60;

        for ($minuto = (int) (ceil($desde / 60) * 60); $minuto <= $hasta; $minuto += $paso) {
            $linea = (int) round($x($minuto));

            imageline($lienzo, $linea, $arriba - 12, $linea, $abajo, self::color($lienzo, AgendaDelDia::GRIS_LINEA));
            self::texto($lienzo, $fuente, self::TAMANO_HORA, self::color($lienzo, AgendaDelDia::GRIS_TEXTO),
                $x($minuto), $abajo + 15, AgendaDelDia::aHora($minuto), 'centro');
        }

        if ($tramos === []) {
            self::texto($lienzo, $fuente, 11, self::color($lienzo, AgendaDelDia::GRIS_TEXTO),
                ($izquierda + $derecha) / 2, $arriba + (self::ALTO_BARRA / 2),
                'Todavía no hay franjas de horario para graficar', 'centro');

            return self::binario($lienzo);
        }

        // 2. Los tramos: el fondo va partido en pedazos, y donde dos
        //    actividades se pisan el pedazo sale transparente.
        foreach ($datos['piezas'] as $pieza) {
            $x0 = $x($pieza['inicio']);
            $x1 = $x($pieza['fin']);
            $opacidad = $pieza['cruce'] ? 98 : ($pieza['hecho'] ? 0 : 56);

            imagefilledrectangle($lienzo, (int) round($x0), $arriba, (int) round($x1), $arriba + self::ALTO_BARRA,
                self::color($lienzo, $pieza['color'], $opacidad));
        }

        // 3. El contorno de cada actividad. Adentro de las barras no va texto:
        //    los colores se explican en la leyenda de abajo.
        foreach ($tramos as $tramo) {
            imagerectangle($lienzo, (int) round($x($tramo['inicio'])), $arriba,
                (int) round($x($tramo['fin'])), $arriba + self::ALTO_BARRA,
                self::color($lienzo, $tramo['color']));
        }

        // 4. La leyenda: un cuadradito del color de cada barra con su hora y su
        //    actividad, en columnas, para poder leer el gráfico sin puntero.
        $anchoColumna = ($derecha - $izquierda) / self::COLUMNAS_LEYENDA;
        $yLeyenda = self::ALTO + 4;

        foreach ($tramos as $indice => $tramo) {
            $columna = $indice % self::COLUMNAS_LEYENDA;
            $fila = intdiv($indice, self::COLUMNAS_LEYENDA);
            $x0 = $izquierda + ($columna * $anchoColumna);
            $y = $yLeyenda + ($fila * self::ALTO_LEYENDA);

            imagefilledrectangle($lienzo, (int) round($x0), $y - 7, (int) round($x0 + 8), $y + 1,
                self::color($lienzo, $tramo['color']));

            $leyenda = $tramo['hora_inicio'].' · '.($tramo['actividad'] !== '' ? $tramo['actividad'] : 'Actividad');
            $leyenda = self::recortar($fuente, self::TAMANO_LEYENDA, $leyenda, $anchoColumna - 16);

            if ($leyenda !== '') {
                self::texto($lienzo, $fuente, self::TAMANO_LEYENDA, self::color($lienzo, '#10233F'),
                    $x0 + 12, $y, $leyenda, 'izquierda');
            }
        }

        return self::binario($lienzo);
    }

    /** data:image/png;base64,... para incrustar en el HTML del PDF. */
    public static function dataUri(array $datos): string
    {
        return 'data:image/png;base64,'.base64_encode(self::generar($datos));
    }

    /** El PNG en binario. */
    private static function binario(\GdImage $lienzo): string
    {
        ob_start();
        imagepng($lienzo);
        $contenido = (string) ob_get_clean();

        imagedestroy($lienzo);

        return $contenido;
    }

    /** Un color con opacidad: 0 es sólido y 127 transparente del todo. */
    private static function color(\GdImage $lienzo, string $hex, int $alfa = 0): int
    {
        $hex = ltrim($hex, '#');
        $rojo = (int) hexdec(substr($hex, 0, 2));
        $verde = (int) hexdec(substr($hex, 2, 2));
        $azul = (int) hexdec(substr($hex, 4, 2));

        return (int) imagecolorallocatealpha($lienzo, $rojo, $verde, $azul, max(0, min(127, $alfa)));
    }

    /** Escribe texto con la fuente TrueType, alineado a la izquierda o al centro. */
    private static function texto(\GdImage $lienzo, string $fuente, float $tamano, int $color, float $x, float $y, string $texto, string $alineacion): void
    {
        if ($alineacion === 'centro') {
            $x -= self::anchoTexto($fuente, $tamano, $texto) / 2;
        }

        imagettftext($lienzo, $tamano, 0, (int) round($x), (int) round($y), $color, $fuente, $texto);
    }

    /** Ancho del texto en píxeles. */
    private static function anchoTexto(string $fuente, float $tamano, string $texto): float
    {
        $caja = imagettfbbox($tamano, 0, $fuente, $texto);

        return $caja === false ? 0.0 : (float) abs($caja[2] - $caja[0]);
    }

    /** Recorta el texto con puntos suspensivos hasta que entre en el ancho. */
    private static function recortar(string $fuente, float $tamano, string $texto, float $ancho): string
    {
        while ($texto !== '' && self::anchoTexto($fuente, $tamano, $texto.'…') > $ancho) {
            $texto = mb_substr($texto, 0, mb_strlen($texto) - 1);
        }

        return $texto === '' ? '' : $texto.'…';
    }

    /** Blanco o azul oscuro, según lo que se lea mejor sobre ese color. */
    private static function colorDeTexto(string $hex): string
    {
        $hex = ltrim($hex, '#');
        $luz = (0.299 * hexdec(substr($hex, 0, 2)))
            + (0.587 * hexdec(substr($hex, 2, 2)))
            + (0.114 * hexdec(substr($hex, 4, 2)));

        return $luz < 150 ? '#FFFFFF' : '#10233F';
    }

    /** La fuente del PDF: DejaVu Sans, la que trae dompdf. */
    private static function fuente(bool $negrita = false): string
    {
        $archivo = $negrita ? 'DejaVuSans-Bold.ttf' : 'DejaVuSans.ttf';
        $ruta = base_path('vendor/dompdf/dompdf/lib/fonts/'.$archivo);

        if (is_file($ruta)) {
            return $ruta;
        }

        // Si algún día no está, se cae a la del sistema.
        return $negrita
            ? 'C:\Windows\Fonts\arialbd.ttf'
            : 'C:\Windows\Fonts\arial.ttf';
    }
}
