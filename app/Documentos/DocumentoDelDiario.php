<?php

namespace App\Documentos;

use App\Graficos\AgendaDelDia;
use App\Graficos\AgendaPng;
use App\Helpers\Helper;
use App\Models\DailyPlan;
use App\Models\ScheduleEntry;
use Barryvdh\DomPDF\Facade\Pdf;
use Dompdf\Dompdf;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Archivos generados de un diario: el PDF y su versión en JPG.
 *
 * Los dos se guardan en el disco privado de la aplicación y se reutilizan:
 *
 *     storage/app/private/documentos/pdf/{id}/planificador-diario-FECHA[-muestra].pdf
 *     storage/app/private/documentos/jpg/{id}/planificador-diario-FECHA[-muestra]-v2.jpg
 *     storage/app/private/documentos/jpg/{id}/planificador-diario-FECHA[-muestra]-v2.zip
 *
 * El sufijo "-muestra" distingue las dos variantes (con y sin marca de agua) y
 * el "-v2" es la revisión de la conversión (ver REVISION_DE_LA_CONVERSION):
 * dentro del ZIP las páginas van sin ninguno de los dos, para que se llamen
 * igual en todas las variantes.
 *
 * Reglas de la caché, en este orden:
 *
 *   1. Si el archivo pedido ya está en su carpeta, se devuelve tal cual.
 *   2. Si falta el JPG, se convierte el PDF guardado; si tampoco está el PDF,
 *      primero se genera el PDF y recién después la imagen.
 *   3. Si falta el PDF, se genera desde la base de datos y se guarda.
 *
 * La marca de agua es una variante aparte: con la casilla marcada y sin marcar
 * son dos archivos distintos, así que marcar y desmarcar no se pisan entre sí.
 *
 * La conversión a JPG la hace el binario `mutool` de MuPDF (ver config/mupdf.php).
 * No se usa Imagick ni Ghostscript a propósito: `mutool` es un ejecutable suelto,
 * sin instalador, y funciona igual en Windows y en Linux. Ojo con un detalle de
 * Windows: ese mutool viene compilado sin soporte JPEG, así que la conversión se
 * pide en PNG y el JPG final lo escribe GD.
 */
class DocumentoDelDiario
{
    /** Disco, dentro de storage/app/private, donde viven los archivos. */
    private const CARPETA_BASE = 'documentos';

    /**
     * Revisión de la conversión a imagen.
     *
     * Va en el nombre del JPG y del ZIP, no en el del PDF: si algún día se
     * cambia la calidad, el enfoque o la resolución, las imágenes guardadas con
     * la versión anterior se dejan de encontrar y se regeneran solas, en vez de
     * seguir sirviéndose viejas. El PDF no la lleva porque su contenido lo
     * define el diario y su nombre es el que ve el usuario al descargarlo.
     *
     * Ojo: al cambiarla, las imágenes de la revisión anterior quedan huérfanas
     * en su carpeta y conviene borrarlas a mano.
     */
    private const REVISION_DE_LA_CONVERSION = 'v2';

    public function __construct(private readonly DailyPlan $plan) {}

    /** Atajo para no repetir el `new` en el modelo y en el controlador. */
    public static function de(DailyPlan $plan): static
    {
        return new static($plan);
    }

    /* ======================================================================
     |  Rutas de la caché
     ====================================================================== */

    /**
     * Nombre del archivo del diario, con la extensión pedida.
     *
     * Lleva la fecha del diario y, si corresponde, el sufijo "-muestra": así el
     * archivo dice por sí solo de qué día es y si es un documento de prueba.
     */
    public function nombre(string $extension, bool $marca = false): string
    {
        $sufijo = $marca ? '-muestra' : '';

        return 'planificador-diario-'.Helper::date($this->plan->plan_date, 'Y-m-d')
            .$sufijo.'.'.$extension;
    }

    /**
     * Nombre de un archivo de imagen (JPG o ZIP), que además lleva la revisión
     * de la conversión para que un cambio de calidad no sirva imágenes viejas.
     */
    private function nombreImagen(string $extension, bool $marca = false): string
    {
        $sufijo = $marca ? '-muestra' : '';

        return 'planificador-diario-'.Helper::date($this->plan->plan_date, 'Y-m-d')
            .$sufijo.'-'.self::REVISION_DE_LA_CONVERSION.'.'.$extension;
    }

    /** Ruta completa del PDF guardado. */
    public function rutaPdf(bool $marca = false): string
    {
        return $this->rutaArchivo('pdf', $this->nombre('pdf', $marca));
    }

    /** Ruta completa del JPG guardado. */
    public function rutaJpg(bool $marca = false): string
    {
        return $this->rutaArchivo('jpg', $this->nombreImagen('jpg', $marca));
    }

    /** Ruta completa del ZIP con las páginas en JPG. */
    public function rutaZip(bool $marca = false): string
    {
        return $this->rutaArchivo('jpg', $this->nombreImagen('zip', $marca));
    }

    /** ¿El PDF ya está guardado? */
    public function tienePdf(bool $marca = false): bool
    {
        return is_file($this->rutaPdf($marca));
    }

    /** ¿El JPG de una sola página ya está guardado? */
    public function tieneJpg(bool $marca = false): bool
    {
        return is_file($this->rutaJpg($marca));
    }

    /** ¿El ZIP de páginas ya está guardado? */
    public function tieneZip(bool $marca = false): bool
    {
        return is_file($this->rutaZip($marca));
    }

    /** Carpeta que guarda los archivos de un tipo ("pdf" o "jpg"). */
    private function carpeta(string $tipo): string
    {
        return storage_path(
            'app/private/'.self::CARPETA_BASE.'/'.$tipo.'/'.$this->plan->getKey()
        );
    }

    /** Ruta completa de un archivo, sin comprobar si existe. */
    private function rutaArchivo(string $tipo, string $nombre): string
    {
        return $this->carpeta($tipo).DIRECTORY_SEPARATOR.$nombre;
    }

    /**
     * Deja lista la carpeta del tipo pedido.
     *
     * @throws RuntimeException si no se puede crear (permisos del servidor).
     */
    private function prepararCarpeta(string $tipo): string
    {
        $carpeta = $this->carpeta($tipo);

        if (! is_dir($carpeta) && ! @mkdir($carpeta, 0775, true) && ! is_dir($carpeta)) {
            throw new RuntimeException(
                'No se pudo crear la carpeta '.$carpeta.'. Revisa los permisos de escritura de storage/app/private.'
            );
        }

        return $carpeta;
    }

    /* ======================================================================
     |  PDF
     ====================================================================== */

    /**
     * Devuelve la ruta del PDF, generándolo y guardándolo si todavía no está.
     *
     * Éste es el punto de entrada normal: la caché se consulta siempre antes de
     * volver a renderizar el documento.
     */
    public function asegurarPdf(bool $marca = false): string
    {
        $ruta = $this->rutaPdf($marca);

        if (is_file($ruta)) {
            return $ruta;
        }

        return $this->generarPdf($marca);
    }

    /**
     * Rehace el PDF desde la base de datos y lo guarda, pase lo que pase con el
     * que hubiera antes.
     *
     * Al rehacerlo se borran los JPG guardados: son una copia del PDF anterior
     * y dejarían de coincidir con el nuevo.
     */
    public function generarPdf(bool $marca = false): string
    {
        $carpeta = $this->prepararCarpeta('pdf');
        $ruta = $carpeta.DIRECTORY_SEPARATOR.$this->nombre('pdf', $marca);

        $this->plan->loadFull();

        $dompdf = $this->renderizar($marca);

        if (@file_put_contents($ruta, $dompdf->output()) === false) {
            throw new RuntimeException('No se pudo guardar el PDF en '.$ruta.'.');
        }

        // Las imágenes de ESTA variante ya no representan al PDF nuevo. Las de
        // la otra variante no se tocan: son de otro PDF y siguen valiendo.
        $this->borrarImagenes($marca);

        return $ruta;
    }

    /**
     * Arma el documento con dompdf y, si se pidió, le estampa la marca de agua.
     *
     * Se crea un dompdf nuevo en cada llamada a propósito: la marca se dibuja
     * con un guion de página, y reutilizar una instancia ya renderizada iría
     * acumulando marcas sobre el mismo documento.
     */
    private function renderizar(bool $marca): Dompdf
    {
        $html = view('diario.pdf', [
            'plan' => $this->plan,
            'marca' => $marca,
            // El gráfico del día viaja como imagen ya hecha: el PDF no ejecuta
            // JavaScript, así que se dibuja en el servidor.
            'grafico' => AgendaPng::dataUri(AgendaDelDia::datos(
                $this->plan->scheduleEntries->map(fn (ScheduleEntry $franja) => [
                    'start_time' => $franja->start_time,
                    'activity' => $franja->activity,
                    'is_done' => (bool) $franja->is_done,
                ])->all()
            )),
        ])->render();

        $dompdf = Pdf::loadHTML($html)->setPaper('letter')->getDomPDF();
        $dompdf->render();

        if ($marca) {
            $this->estamparMuestra($dompdf);
        }

        return $dompdf;
    }

    /**
     * Marca de agua SPECIMEN, centrada y en diagonal, sobre todas las páginas.
     *
     * Se dibuja con el lienzo de dompdf porque el PDF no admite rotar texto con
     * CSS. El ángulo va en negativo a propósito: dompdf compone la matriz de
     * rotación como [cos, -sin, sin, cos], así que un ángulo positivo haría
     * bajar la marca de izquierda a derecha. Con -45 sube, como se pidió.
     *
     * La posición se calcula a mano: se resta media longitud del texto en la
     * dirección de la diagonal para que el centro caiga en el centro de la
     * página, y se descuenta la altura de la fuente porque el texto se coloca
     * por su parte de arriba, no por su línea base.
     *
     * El color es gris oscuro, pero con muy poca opacidad: así sobre el papel
     * blanco queda un gris clarísimo y sobre el texto negro casi no se nota. Si
     * se pintara un gris claro opaco, taparía las letras del documento.
     */
    private function estamparMuestra(Dompdf $dompdf): void
    {
        $canvas = $dompdf->getCanvas();
        $metricas = $dompdf->getFontMetrics();
        $fuente = $metricas->getFont('Helvetica', 'bold');

        $texto = 'SPECIMEN';
        $tamano = 88;
        $angulo = -45;
        $color = [0.28, 0.28, 0.28];
        $transparencia = 0.16;

        $ancho = $canvas->get_width();
        $alto = $canvas->get_height();

        $largo = $metricas->getTextWidth($texto, $fuente, $tamano);

        // Ojo: CPDF coloca el texto descontando la altura de fuente SIN el
        // factor de interlineado que aplica getFontHeight(). Se divide por ese
        // factor para descontar exactamente lo mismo y no quedar descentrado.
        $altura = $metricas->getFontHeight($fuente, $tamano)
            / $dompdf->getOptions()->getFontHeightRatio();

        $radianes = deg2rad(abs($angulo));
        $avanceX = cos($radianes);
        $avanceY = sin($radianes);

        // El centro óptico de las mayúsculas va media altura por encima de la
        // línea base, así que la base baja ese tanto para quedar centrada.
        $centroX = $ancho / 2;
        $centroY = ($alto / 2) + ($tamano * 0.36);

        $x = $centroX - ($largo / 2) * $avanceX;
        $y = $centroY + ($largo / 2) * $avanceY - $altura;

        // Se dibuja con page_script y no con page_text porque en un PDF la
        // transparencia es un estado de dibujo: afecta a lo que se pinta
        // DESPUÉS de fijarla. Aquí se fija en cada página, justo antes de la
        // marca, y se devuelve a 1 para no afectar al resto.
        $canvas->page_script(
            function ($numero, $total, $lienzo) use ($x, $y, $texto, $fuente, $tamano, $color, $angulo, $transparencia) {
                $lienzo->set_opacity($transparencia);
                $lienzo->text($x, $y, $texto, $fuente, $tamano, $color, 0, 0, $angulo);
                $lienzo->set_opacity(1);
            }
        );
    }

    /* ======================================================================
     |  JPG y ZIP
     ====================================================================== */

    /**
     * Prepara la descarga de la imagen: devuelve la ruta del archivo a bajar
     * (el JPG si el PDF tiene una sola página, el ZIP si tiene dos o más) y su
     * tipo de contenido.
     *
     * Todo sale de la caché cuando ya existe. Si falta la imagen, se convierte
     * el PDF guardado; y si el PDF tampoco está, se genera primero.
     *
     * @return array{ruta: string, tipo: string, nombre: string}
     */
    public function asegurarImagen(bool $marca = false): array
    {
        if ($this->tieneJpg($marca)) {
            return $this->descarga($this->rutaJpg($marca), 'image/jpeg', $this->nombreImagen('jpg', $marca));
        }

        if ($this->tieneZip($marca)) {
            return $this->descarga($this->rutaZip($marca), 'application/zip', $this->nombreImagen('zip', $marca));
        }

        // Antes de convertir se descartan las imágenes de ESTA variante: si no,
        // una conversión con menos páginas que la anterior dejaría páginas
        // sueltas que ya no corresponden a este PDF. Ojo: no se puede borrar
        // toda la carpeta, porque ahí vive también la imagen de la otra
        // variante (con y sin marca de agua son dos archivos distintos).
        $this->borrarImagenes($marca);

        // Y si el PDF tampoco está, se rehace desde la base de datos.
        $rutaPdf = $this->asegurarPdf($marca);

        return $this->convertir($rutaPdf, $marca);
    }

    /**
     * Convierte el PDF a JPG con `mutool`.
     *
     * @param  string|null  $carpetaSalida  Sólo para las pruebas: dónde dejar los
     *                                      JPG en vez de la carpeta del diario.
     * @return array{ruta: string, tipo: string, nombre: string}
     */
    private function convertir(string $rutaPdf, bool $marca, ?string $carpetaSalida = null): array
    {
        $binario = self::binario();

        if ($binario === null) {
            throw new RuntimeException(
                'No se encontró el conversor de PDF a imagen (mutool). '
                .'Instálalo en '.self::rutaBinarioPorDefecto().' o indícalo en MUPDF_BIN.'
            );
        }

        $carpeta = $carpetaSalida ?? $this->prepararCarpeta('jpg');
        $prefijo = $carpeta.DIRECTORY_SEPARATOR.'pagina-';

        // Ojo: la conversión se pide en PNG, no en JPG. El mutool que se
        // distribuye para Windows viene compilado sin soporte JPEG y responde
        // "Output format could not be determined" si se le pide un .jpg. El PNG
        // sale siempre; el JPG final lo hace GD, que sí está disponible en PHP.
        $comando = self::citar($binario)
            .' draw'
            .' -q'
            .' -r '.self::resolucion()
            .' -o '.self::citar($prefijo.'%d.png')
            .' '.self::citar($rutaPdf);

        $this->ejecutar($comando);

        $paginas = glob($prefijo.'*.png') ?: [];

        if ($paginas === []) {
            throw new RuntimeException('La conversión del PDF a imagen no produjo ninguna página.');
        }

        sort($paginas, SORT_NATURAL);

        // Cada PNG se convierte a JPG y se descarta el intermedio.
        foreach ($paginas as $indice => $pagina) {
            $destino = $carpeta.DIRECTORY_SEPARATOR.'pagina-'.($indice + 1).'.jpg';

            $this->aJpg($pagina, $destino);
            @unlink($pagina);

            $paginas[$indice] = $destino;
        }

        if (count($paginas) === 1) {
            $jpg = $carpetaSalida === null
                ? $this->rutaJpg($marca)
                : $carpetaSalida.DIRECTORY_SEPARATOR.$this->nombreImagen('jpg', $marca);

            if (realpath($paginas[0]) !== realpath($jpg)) {
                if (! @rename($paginas[0], $jpg)) {
                    copy($paginas[0], $jpg);
                    @unlink($paginas[0]);
                }
            }

            return $this->descarga($jpg, 'image/jpeg', $this->nombreImagen('jpg', $marca));
        }

        // Varias páginas: van juntas en un ZIP, numeradas en orden.
        $zip = $carpetaSalida === null
            ? $this->rutaZip($marca)
            : $carpetaSalida.DIRECTORY_SEPARATOR.$this->nombreImagen('zip', $marca);

        // El nombre de las páginas de dentro del ZIP va sin la revisión, para
        // que las imágenes se llamen igual en cualquier revisión.
        $this->comprimir($paginas, $zip, $this->nombre('jpg'));

        // Una vez dentro del ZIP, las páginas sueltas ya no hacen falta.
        foreach ($paginas as $pagina) {
            @unlink($pagina);
        }

        return $this->descarga($zip, 'application/zip', $this->nombreImagen('zip', $marca));
    }

    /**
     * Pasa una imagen del PDF (PNG, sin fondo) a JPG sobre papel blanco.
     *
     * El PNG de mutool lleva transparencia; si se guardara tal cual, el JPG
     * saldría con fondo negro donde no hay tinta. Por eso se aplana sobre un
     * lienzo blanco antes de comprimir.
     *
     * Después se afina un poco (ver afinar()) y recién ahí se comprime con la
     * calidad configurada. El tamaño de la imagen no cambia en ningún paso: sólo
     * mejora la definición del texto.
     */
    private function aJpg(string $origen, string $destino): void
    {
        if (! function_exists('imagecreatefrompng') || ! function_exists('imagejpeg')) {
            throw new RuntimeException(
                'La extensión GD de PHP no puede generar JPG (falta el soporte JPEG).'
            );
        }

        $png = @imagecreatefrompng($origen);

        if ($png === false) {
            throw new RuntimeException('No se pudo leer la página convertida: '.$origen);
        }

        $ancho = imagesx($png);
        $alto = imagesy($png);

        $lienzo = imagecreatetruecolor($ancho, $alto);
        $blanco = imagecolorallocate($lienzo, 255, 255, 255);
        imagefilledrectangle($lienzo, 0, 0, $ancho, $alto, $blanco);
        imagecopy($lienzo, $png, 0, 0, 0, 0, $ancho, $alto);

        $this->afinar($lienzo);

        $guardado = imagejpeg($lienzo, $destino, self::calidad());

        imagedestroy($lienzo);
        imagedestroy($png);

        if (! $guardado) {
            throw new RuntimeException('No se pudo guardar el JPG: '.$destino);
        }
    }

    /**
     * Afina los bordes del texto con una máscara de nitidez muy leve.
     *
     * El PDF se rasteriza a 150 ppp, que es lo que hace que el texto quede algo
     * difuso; con este filtro se recupera definición sin tocar el tamaño de la
     * imagen. La matriz es la identidad más un realce: el centro vale 17 y los
     * ocho vecinos -1, todo dividido por 9, así el brillo general no cambia y no
     * aparecen halos alrededor de las letras.
     *
     * Se puede apagar desde config/mupdf.php (o con MUPDF_ENFOQUE=false) para
     * dejar la conversión tal cual sale de mutool.
     */
    private function afinar(\GdImage $imagen): void
    {
        if (! config('mupdf.enfoque', true)) {
            return;
        }

        // El filtro escribe sobre la misma imagen: si el servidor no lo soporta
        // (falta la función o la imagen no es truecolor), se deja sin afinar.
        if (! function_exists('imageconvolution') || ! imageistruecolor($imagen)) {
            return;
        }

        @imageconvolution($imagen, [
            [-1, -1, -1],
            [-1, 17, -1],
            [-1, -1, -1],
        ], 9, 0);
    }

    /**
     * Guarda las páginas dentro de un ZIP, con el nombre del diario y el número
     * de página al final del archivo.
     *
     * @param  list<string>  $paginas
     * @param  string  $nombre  Nombre del diario para numerar las páginas, sin
     *                          el sufijo de muestra: así las imágenes de dentro
     *                          del ZIP se llaman igual en las dos variantes.
     */
    private function comprimir(array $paginas, string $destino, string $nombre): void
    {
        $zip = new ZipArchive;

        if ($zip->open($destino, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el ZIP '.$destino.'.');
        }

        $base = pathinfo($nombre, PATHINFO_FILENAME);

        foreach ($paginas as $indice => $pagina) {
            $zip->addFile($pagina, $base.'-pagina-'.($indice + 1).'.jpg');
        }

        $zip->close();
    }

    /* ======================================================================
     |  Bajas
     ====================================================================== */

    /**
     * Borra la imagen guardada de UNA variante (JPG y ZIP por si acaso).
     *
     * Es lo que hay que descartar antes de volver a convertir, sin tocar la
     * imagen de la otra variante: con la marca de agua y sin ella conviven en
     * la misma carpeta.
     */
    private function borrarImagenes(bool $marca = false): void
    {
        if (! is_dir($this->carpeta('jpg'))) {
            return;
        }

        @unlink($this->rutaJpg($marca));
        @unlink($this->rutaZip($marca));
    }

    /**
     * Borra lo generado para el diario, en los dos formatos.
     *
     * Se llama al eliminar el diario, para no dejar archivos huérfanos, y al
     * modificarlo, porque el PDF y el JPG guardados ya no representan al día.
     *
     * La carpeta del diario también se intenta borrar, pero si el servidor no
     * deja (hay cuentas que pueden borrar archivos y no carpetas, como pasa con
     * Apache en Windows), queda vacía y sin molestar: la próxima generación la
     * reutiliza. Por eso el intento no es un error.
     */
    public function limpiar(): void
    {
        foreach (['pdf', 'jpg'] as $tipo) {
            $this->borrarContenido($this->carpeta($tipo));
            @rmdir($this->carpeta($tipo));
        }

        @rmdir(dirname($this->carpeta('pdf')));
    }

    /** Borra todo lo que hay adentro de una carpeta, sin borrar la carpeta. */
    private function borrarContenido(string $carpeta): void
    {
        if (! is_dir($carpeta)) {
            return;
        }

        foreach (glob($carpeta.DIRECTORY_SEPARATOR.'*') ?: [] as $archivo) {
            if (is_file($archivo)) {
                @unlink($archivo);
            }
        }
    }

    /* ======================================================================
     |  El binario de conversión
     ====================================================================== */

    /** Ruta donde se espera el ejecutable si no se configuró otra. */
    public static function rutaBinarioPorDefecto(): string
    {
        $nombre = PHP_OS_FAMILY === 'Windows' ? 'mutool.exe' : 'mutool';

        return storage_path('app/private/binarios/mupdf/'.$nombre);
    }

    /** Resolución de la conversión, configurable en config/mupdf.php. */
    private static function resolucion(): int
    {
        $resolucion = (int) config('mupdf.resolucion', 150);

        return $resolucion > 0 ? $resolucion : 150;
    }

    /** Calidad del JPG, configurable en config/mupdf.php. */
    private static function calidad(): int
    {
        $calidad = (int) config('mupdf.calidad', 92);

        return ($calidad >= 0 && $calidad <= 100) ? $calidad : 92;
    }

    /**
     * El ejecutable de `mutool`, o null si no está en el servidor.
     *
     * Se busca primero en la ruta configurada (MUPDF_BIN) y después en el PATH,
     * para que sirva tanto el binario guardado dentro del proyecto como uno
     * instalado en el sistema (winget, apt, brew...).
     */
    public static function binario(): ?string
    {
        $configurado = trim((string) config('mupdf.bin', ''));

        if ($configurado !== '' && is_file($configurado)) {
            return $configurado;
        }

        if (is_file(self::rutaBinarioPorDefecto())) {
            return self::rutaBinarioPorDefecto();
        }

        return self::buscarEnElPath();
    }

    /** ¿El servidor puede convertir PDF a JPG? */
    public static function puedeConvertir(): bool
    {
        return self::binario() !== null;
    }

    /**
     * Busca `mutool` en el PATH del sistema.
     *
     * Se le pregunta a la terminal por dónde está, en vez de recorrer carpetas
     * a mano, porque así también se encuentran los lanzadores de Windows
     * (`mutool.exe`) y los enlaces de Linux.
     */
    private static function buscarEnElPath(): ?string
    {
        $orden = PHP_OS_FAMILY === 'Windows' ? 'where mutool 2>NUL' : 'command -v mutool 2>/dev/null';
        $lineas = [];

        @exec($orden, $lineas, $codigo);

        if ($codigo !== 0) {
            return null;
        }

        $ruta = trim((string) ($lineas[0] ?? ''));

        return $ruta !== '' && is_file($ruta) ? $ruta : null;
    }

    /* ======================================================================
     |  Internos auxiliares
     ====================================================================== */

    /**
     * Envuelve un argumento en comillas para el comando del sistema.
     *
     * Ojo: acá no se puede usar escapeshellarg(). En Windows esa función pasa el
     * texto por el analizador de variables del intérprete de comandos y se come
     * el "%" de "%d", que es justo el marcador con el que mutool numera las
     * páginas: el destino quedaba como "pagina- d.jpg" y la conversión fallaba
     * siempre. Las comillas dobles a mano sí respetan el marcador, y en Linux y
     * macOS las comillas simples son la forma correcta.
     */
    private static function citar(string $argumento): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            // Dentro de comillas dobles, una comilla se escapa duplicándola.
            return '"'.str_replace('"', '""', $argumento).'"';
        }

        return "'".str_replace("'", "'\\''", $argumento)."'";
    }

    /**
     * Corre un comando del sistema y falla con el motivo si no sale bien.
     *
     * `exec` se usa a propósito: el binario de MuPDF se ejecuta una vez por
     * documento y su salida es un archivo, no un flujo que haya que leer.
     */
    private function ejecutar(string $comando): void
    {
        $salida = [];
        $codigo = 0;

        try {
            @exec($comando.' 2>&1', $salida, $codigo);
        } catch (Throwable $e) {
            throw new RuntimeException('No se pudo ejecutar el conversor de PDF a JPG.', 0, $e);
        }

        if ($codigo !== 0) {
            throw new RuntimeException(
                'El conversor de PDF a JPG terminó con error ('.$codigo.'): '
                .trim(implode(' ', array_slice($salida, -3)))
            );
        }
    }

    /**
     * Arma la respuesta de descarga de un archivo ya guardado.
     *
     * @return array{ruta: string, tipo: string, nombre: string}
     */
    private function descarga(string $ruta, string $tipo, string $nombre): array
    {
        return ['ruta' => $ruta, 'tipo' => $tipo, 'nombre' => $nombre];
    }
}
