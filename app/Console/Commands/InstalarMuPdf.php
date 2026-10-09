<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use ZipArchive;

/**
 * Deja el conversor de PDF a JPG dentro del proyecto.
 *
 * Los JPG de los diarios los saca `mutool`, el ejecutable de MuPDF: no necesita
 * instalador ni permisos de administrador, así que se guarda en el disco privado
 * de la aplicación y la app lo encuentra sola.
 *
 *     php artisan mupdf:instalar
 *
 * El comando elige el paquete según el sistema (Windows, Linux o macOS) y deja
 * el ejecutable en storage/app/private/binarios/mupdf, que queda ignorado por
 * git. Es una instalación de una sola vez: después, el botón "Generar imagen"
 * funciona sin volver a tocar nada.
 *
 * Ojo con la cuenta: el comando tiene que escribirlo alguien con permiso en
 * storage/ (el mismo usuario con el que corre el servidor web; en Windows,
 * conviene una consola como administrador). Si no, avisa y no hace nada.
 */
class InstalarMuPdf extends Command
{
    protected $signature = 'mupdf:instalar {--forzar : vuelve a instalarlo aunque ya esté}';

    protected $description = 'Instala el conversor mutool (MuPDF) que usa el botón Generar imagen';

    /** Versión de MuPDF que se instala. */
    private const VERSION = '1.28.5';

    private const REPOSITORIO = 'https://github.com/ArtifexSoftware/mupdf-downloads/releases/download';

    public function handle(): int
    {
        $carpeta = dirname(self::rutaDestino());
        $ejecutable = self::rutaDestino();

        if (! is_dir($carpeta) && ! @mkdir($carpeta, 0775, true) && ! is_dir($carpeta)) {
            $this->error('No se pudo crear '.$carpeta);
            $this->line('Esta consola corre como "'.get_current_user().'", que no tiene permiso de');
            $this->line('escritura en storage/. Ejecuta el comando desde una consola con permisos');
            $this->line('(el mismo usuario que usa el servidor web; en Windows, como administrador).');

            return self::FAILURE;
        }

        $paquete = $this->paqueteDelSistema();

        $this->line('sistema: '.PHP_OS_FAMILY.' ('.$paquete['etiqueta'].')');
        $this->line('destino: '.$ejecutable);

        if (is_file($ejecutable) && ! $this->option('forzar')) {
            $this->info('mutool ya está instalado.');

            return $this->probar($ejecutable) ? self::SUCCESS : self::FAILURE;
        }

        $comprimido = $carpeta.'/'.$paquete['archivo'];

        if (! $this->descargar($paquete, $comprimido)) {
            return self::FAILURE;
        }

        $this->line('extrayendo...');

        if (! $this->extraer($comprimido, $carpeta, $ejecutable)) {
            return self::FAILURE;
        }

        @unlink($comprimido);

        if (! $this->probar($ejecutable)) {
            return self::FAILURE;
        }

        $this->info('LISTO: '.$ejecutable);
        $this->line('El botón "Generar imagen" ya puede convertir los PDF a JPG.');

        return self::SUCCESS;
    }

    /** Dónde se espera el ejecutable (lo mismo que usa DocumentoDelDiario). */
    public static function rutaDestino(): string
    {
        $nombre = PHP_OS_FAMILY === 'Windows' ? 'mutool.exe' : 'mutool';

        return storage_path('app/private/binarios/mupdf/'.$nombre);
    }

    /**
     * El paquete que corresponde al sistema.
     *
     * @return array{archivo: string, etiqueta: string, formato: string, interno: string}
     */
    private function paqueteDelSistema(): array
    {
        $comun = 'mupdf-'.self::VERSION;

        return match (PHP_OS_FAMILY) {
            'Windows' => [
                'archivo' => $comun.'-windows.zip',
                'etiqueta' => 'paquete de Windows',
                'formato' => 'zip',
                'interno' => 'mutool.exe',
            ],
            'Darwin' => [
                'archivo' => $comun.'-macos.tar.gz',
                'etiqueta' => 'paquete de macOS',
                'formato' => 'tar',
                'interno' => 'mutool',
            ],
            default => [
                'archivo' => $comun.'-linux-x64.zip',
                'etiqueta' => 'paquete de Linux x64',
                'formato' => 'zip',
                'interno' => 'mutool',
            ],
        };
    }

    /** Descarga el paquete mostrando el avance. */
    private function descargar(array $paquete, string $destino): bool
    {
        $url = self::REPOSITORIO.'/'.self::VERSION.'/'.$paquete['archivo'];

        $this->line('descargando '.$url);

        $fh = @fopen($destino, 'wb');

        if ($fh === false) {
            $this->error('No se pudo abrir '.$destino.' para escritura.');

            return false;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fh,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 900,
            CURLOPT_PROGRESSFUNCTION => function ($recurso, $total, $hecho) {
                static $ultimo = 0;

                if ($total > 0 && $hecho - $ultimo > 8 * 1048576) {
                    $ultimo = $hecho;
                    $this->line('  '.number_format($hecho / 1048576, 1).' MB de '.number_format($total / 1048576, 1).' MB');
                }

                return 0;
            },
        ]);

        $ok = curl_exec($ch);
        $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        fclose($fh);

        clearstatcache(true, $destino);
        $tamano = (int) @filesize($destino);

        $this->line('http '.$codigo.' · '.number_format($tamano / 1048576, 1).' MB'.($error !== '' ? ' · '.$error : ''));

        if (! $ok || $codigo !== 200 || $tamano < 1048576) {
            $this->error('La descarga no salió bien.');

            return false;
        }

        return true;
    }

    /**
     * Saca el ejecutable del paquete, sin depender de la carpeta interna que
     * traiga el archivo.
     */
    private function extraer(string $comprimido, string $carpeta, string $destino): bool
    {
        $formato = str_ends_with($comprimido, '.zip') ? 'zip' : 'tar';
        $interno = basename($destino);

        $contenido = $formato === 'zip'
            ? $this->leerDelZip($comprimido, $interno)
            : $this->leerDelTar($comprimido, $interno);

        if ($contenido === null) {
            $this->error('El paquete no traía '.$interno.'.');

            return false;
        }

        if (@file_put_contents($destino, $contenido) === false) {
            $this->error('No se pudo escribir '.$destino.'.');

            return false;
        }

        @chmod($destino, 0755);

        $this->line('extraido '.$interno.' ('.number_format(strlen($contenido) / 1048576, 1).' MB)');

        return true;
    }

    /** Busca un archivo por nombre dentro del ZIP. */
    private function leerDelZip(string $comprimido, string $interno): ?string
    {
        $zip = new ZipArchive;

        if ($zip->open($comprimido) !== true) {
            return null;
        }

        $contenido = null;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            if (strtolower(basename((string) $zip->getNameIndex($i))) === strtolower($interno)) {
                $leido = $zip->getFromIndex($i);

                if ($leido !== false) {
                    $contenido = $leido;
                }

                break;
            }
        }

        $zip->close();

        return $contenido;
    }

    /**
     * Busca un archivo por nombre dentro del tar.gz.
     *
     * Se lee el tar a mano en vez de usar PharData porque PharData necesita
     * escribir el archivo intermedio en el disco temporal y en algunos hostings
     * eso está restringido.
     */
    private function leerDelTar(string $comprimido, string $interno): ?string
    {
        $fh = @gzopen($comprimido, 'rb');

        if ($fh === false) {
            return null;
        }

        $bloque = 512;

        while (! gzeof($fh)) {
            $cabecera = gzread($fh, $bloque);

            if ($cabecera === false || strlen($cabecera) < $bloque) {
                break;
            }

            // Dos bloques de ceros marcan el final del archivo.
            if (trim($cabecera, "\0") === '') {
                break;
            }

            $datos = unpack('a100nombre/a8modo/a8uid/a8gid/a12tamano/a12fecha/a8suma/a1tipo', $cabecera);
            $nombre = rtrim((string) $datos['nombre'], "\0");
            $tamano = (int) octdec(trim((string) $datos['tamano']));
            $tipo = (string) $datos['tipo'];

            // El nombre largo de los tar modernos viene en un bloque aparte.
            if (str_starts_with($nombre, '././@LongLink')) {
                $largo = gzread($fh, $tamano + (($bloque - ($tamano % $bloque)) % $bloque));
                $nombre = rtrim(substr((string) $largo, 0, $tamano), "\0");
                $cabecera = gzread($fh, $bloque);
                $datos = unpack('a100nombre/a8modo/a8uid/a8gid/a12tamano/a12fecha/a8suma/a1tipo', (string) $cabecera);
                $tamano = (int) octdec(trim((string) $datos['tamano']));
                $tipo = (string) $datos['tipo'];
            }

            $contenido = gzread($fh, $tamano);

            // Se descarta el relleno hasta el siguiente bloque.
            $relleno = ($bloque - ($tamano % $bloque)) % $bloque;

            if ($relleno > 0) {
                gzread($fh, $relleno);
            }

            if (($tipo === '0' || $tipo === "\0" || $tipo === '') && basename($nombre) === $interno) {
                gzclose($fh);

                return $contenido;
            }
        }

        gzclose($fh);

        return null;
    }

    /** Comprueba que el ejecutable arranque de verdad. */
    private function probar(string $ejecutable): bool
    {
        $salida = [];
        $codigo = 1;
        @exec('"'.$ejecutable.'" -v 2>&1', $salida, $codigo);

        $this->line('mutool -v: '.trim(implode(' | ', array_slice($salida, 0, 2))));

        if ($codigo !== 0) {
            $this->error('mutool no se pudo ejecutar (código '.$codigo.').');

            return false;
        }

        return true;
    }
}
