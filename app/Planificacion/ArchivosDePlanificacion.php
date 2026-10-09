<?php

namespace App\Planificacion;

use App\Helpers\Helper;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Los archivos del módulo "Planificación periódica".
 *
 * Todo vive dentro del disco privado de la aplicación (storage/app/private),
 * en una carpeta por etapa:
 *
 *   excel/formato/       la planilla en blanco que se descarga
 *   excel/jobs/          lo que se monta en el módulo y lo que lee el cron
 *   excel/procesados/    los libros ya cubiertos por completo
 *   excel/descartados/   los libros que se sacaron de la cola a mano
 *   excel/errores/       los libros que no se pudieron abrir
 *   excel/ejemplos/      un libro de ejemplo, sólo para pruebas
 *
 * El disco es "local", el mismo que ya usan los PDF y las imágenes del diario,
 * así que no hace falta configurar nada nuevo.
 */
class ArchivosDePlanificacion
{
    /** El disco privado de la aplicación. */
    public const DISCO = 'local';

    /* Carpetas, relativas a la raíz del disco. */
    public const CARPETA_FORMATO = 'excel/formato';

    public const CARPETA_JOBS = 'excel/jobs';

    public const CARPETA_PROCESADOS = 'excel/procesados';

    public const CARPETA_DESCARTADOS = 'excel/descartados';

    public const CARPETA_ERRORES = 'excel/errores';

    public const CARPETA_EJEMPLOS = 'excel/ejemplos';

    /** La única extensión que se procesa. */
    public const EXTENSION = 'xlsx';

    /** Cómo se llama la planilla en blanco que se descarga. */
    public const PLANTILLA = 'planilla-planificador-diario.xlsx';

    /** Cuánto puede pesar un libro montado (20 MB). */
    public const PESO_MAXIMO = 20480;

    public static function disco(): Filesystem
    {
        return Storage::disk(self::DISCO);
    }

    /* ======================================================================
     |  Rutas
     ====================================================================== */

    /** La ruta de la planilla en blanco, relativa al disco. */
    public static function rutaPlantilla(): string
    {
        return self::CARPETA_FORMATO.'/'.self::PLANTILLA;
    }

    /** La ruta absoluta de un archivo del disco privado. */
    public static function rutaAbsoluta(string $relativa): string
    {
        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, self::disco()->path($relativa));
    }

    /** La ruta de un libro dentro de la cola. */
    public static function rutaEnCola(string $nombre): string
    {
        return self::CARPETA_JOBS.'/'.$nombre;
    }

    /* ======================================================================
     |  La planilla en blanco
     ====================================================================== */

    /** ¿La planilla estática ya está guardada? */
    public static function existePlantilla(): bool
    {
        return self::disco()->exists(self::rutaPlantilla());
    }

    /**
     * Guarda (o reemplaza) la planilla en blanco. El link del módulo no cambia
     * aunque el archivo se reemplace.
     */
    public static function guardarPlantilla(string $contenido): string
    {
        self::disco()->put(self::rutaPlantilla(), $contenido);

        return self::rutaPlantilla();
    }

    /** Datos de la planilla para mostrarla en el módulo. */
    public static function datosDeLaPlantilla(): array
    {
        $ruta = self::rutaPlantilla();

        if (! self::disco()->exists($ruta)) {
            return ['existe' => false, 'nombre' => self::PLANTILLA, 'peso' => null, 'modificado' => null];
        }

        return [
            'existe' => true,
            'nombre' => self::PLANTILLA,
            'peso' => Helper::fileSize(self::disco()->size($ruta)),
            'modificado' => Helper::dateTime(self::disco()->lastModified($ruta)),
        ];
    }

    /* ======================================================================
     |  Los libros de la cola
     ====================================================================== */

    /**
     * Los libros que hay en la cola, del más antiguo al más nuevo.
     *
     * El orden importa: si dos libros cubren la misma fecha y esa fecha todavía
     * no está en la base, gana el primero que corre. Ordenar por la fecha de
     * modificación deja la corrida igual todas las noches.
     *
     * @return list<array{nombre: string, ruta: string, peso: int, modificado: int}>
     */
    public static function librosEnCola(): array
    {
        $libros = [];

        foreach (self::disco()->files(self::CARPETA_JOBS) as $ruta) {
            if (strtolower(pathinfo($ruta, PATHINFO_EXTENSION)) !== self::EXTENSION) {
                continue;
            }

            $libros[] = [
                'nombre' => basename($ruta),
                'ruta' => $ruta,
                'peso' => (int) self::disco()->size($ruta),
                'modificado' => (int) self::disco()->lastModified($ruta),
            ];
        }

        usort($libros, fn (array $a, array $b) => [$a['modificado'], $a['nombre']] <=> [$b['modificado'], $b['nombre']]);

        return $libros;
    }

    /**
     * Guarda el libro que se montó en el módulo y devuelve su nombre final.
     *
     * Si ya existía uno con el mismo nombre se reemplaza: el módulo sólo
     * guarda, y el nombre es la identidad del libro para la cola.
     */
    public static function guardarLibro(UploadedFile $archivo, ?string $nombre = null): string
    {
        $nombre = Helper::safeFileName($nombre ?? $archivo->getClientOriginalName());
        $contenido = (string) file_get_contents($archivo->getRealPath());

        self::disco()->put(self::rutaEnCola($nombre), $contenido);

        return $nombre;
    }

    /** ¿Ese nombre ya está en la cola? */
    public static function estaEnCola(string $nombre): bool
    {
        return self::disco()->exists(self::rutaEnCola($nombre));
    }

    /**
     * Mueve un libro de la cola a otra carpeta. Si ya había uno con ese nombre
     * en el destino, se reemplaza.
     */
    public static function moverDeLaCola(string $nombre, string $carpeta): bool
    {
        $origen = self::rutaEnCola($nombre);
        $destino = $carpeta.'/'.$nombre;

        if (! self::disco()->exists($origen)) {
            return false;
        }

        if (self::disco()->exists($destino)) {
            self::disco()->delete($destino);
        }

        return (bool) self::disco()->move($origen, $destino);
    }

    /** Saca el libro de la cola sin procesarlo. */
    public static function descartar(string $nombre): bool
    {
        return self::moverDeLaCola($nombre, self::CARPETA_DESCARTADOS);
    }

    /**
     * Guarda una copia con la fecha y la hora en el nombre, para no perder el
     * historial de lo que ya se procesó.
     */
    public static function nombreConMarcaDeTiempo(string $nombre): string
    {
        $base = pathinfo($nombre, PATHINFO_FILENAME);
        $extension = pathinfo($nombre, PATHINFO_EXTENSION) ?: self::EXTENSION;

        return $base.'-'.Helper::now()->format('Ymd-His').'.'.$extension;
    }
}
