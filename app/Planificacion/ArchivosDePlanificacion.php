<?php

namespace App\Planificacion;

use App\Helpers\Helper;
use Carbon\Carbon;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
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

    /** Dónde el comando va dejando cómo va la corrida (lo lee la barra de progreso). */
    public const ARCHIVO_CORRIDA = 'excel/corrida.json';

    /** El log propio de la corrida, al lado de los demás logs de la aplicación. */
    public const LOG_CORRIDA = 'logs/planificacion-corrida.log';

    /**
     * Cuántos minutos sin señales se aguantan antes de dar la corrida por
     * interrumpida. El comando reescribe su estado a cada paso, así que un
     * silencio largo quiere decir que el proceso ya no está.
     */
    public const MINUTOS_SIN_SENALES = 15;

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

    /**
     * Todas las planillas guardadas: la genérica (la que se duplica a mano) y
     * las de un período, que ya traen una hoja por día.
     *
     * La genérica va primero y las demás de la más nueva a la más vieja.
     *
     * @return list<array{nombre: string, peso: string, modificado: string, generica: bool, periodo: ?string}>
     */
    public static function plantillas(): array
    {
        $plantillas = [];

        foreach (self::disco()->files(self::CARPETA_FORMATO) as $ruta) {
            if (strtolower(pathinfo($ruta, PATHINFO_EXTENSION)) !== self::EXTENSION) {
                continue;
            }

            $nombre = basename($ruta);

            $plantillas[] = [
                'nombre' => $nombre,
                'peso' => Helper::fileSize((int) self::disco()->size($ruta)),
                'modificado' => Helper::dateTime((int) self::disco()->lastModified($ruta)),
                'generica' => $nombre === self::PLANTILLA,
                'periodo' => self::periodoDeLaPlantilla($nombre),
                'orden' => (int) self::disco()->lastModified($ruta),
            ];
        }

        usort($plantillas, function (array $a, array $b) {
            // La genérica primero; después, la más nueva primero.
            if ($a['generica'] !== $b['generica']) {
                return $a['generica'] ? -1 : 1;
            }

            return $b['orden'] <=> $a['orden'];
        });

        return array_map(function (array $plantilla) {
            unset($plantilla['orden']);

            return $plantilla;
        }, $plantillas);
    }

    /** ¿Ese archivo es una de las planillas guardadas? */
    public static function esPlantilla(string $nombre): bool
    {
        foreach (self::plantillas() as $plantilla) {
            if ($plantilla['nombre'] === $nombre) {
                return true;
            }
        }

        return false;
    }

    /** El período que cubre una planilla por su nombre ("2026-10-01_2026-10-31"). */
    private static function periodoDeLaPlantilla(string $nombre): ?string
    {
        if (preg_match('/^planilla-(\d{4}-\d{2}-\d{2})_(\d{4}-\d{2}-\d{2})\.xlsx$/', $nombre, $partes) !== 1) {
            return null;
        }

        return Helper::rangeLabel($partes[1], $partes[2]);
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

    /* ======================================================================
     |  La corrida: cómo va y su log
     ====================================================================== */

    /**
     * Cómo va la corrida, tal como lo dejó el comando.
     *
     * El comando escribe este archivo a medida que avanza (y también los
     * progresos en su log), y el módulo lo lee para mostrar la barra de progreso
     * del botón "Ejecutar corrida". Nunca lo escribe el navegador.
     *
     * @return array<string, mixed>|null
     */
    public static function estadoDeLaCorrida(): ?array
    {
        if (! self::disco()->exists(self::ARCHIVO_CORRIDA)) {
            return null;
        }

        try {
            $datos = json_decode((string) self::disco()->get(self::ARCHIVO_CORRIDA), true);
        } catch (\Throwable) {
            return null;
        }

        return is_array($datos) ? $datos : null;
    }

    /** Deja anotado cómo va la corrida. */
    public static function guardarEstadoDeLaCorrida(array $estado): void
    {
        // El momento de la última señal: si no lo pone quien llama, es ahora.
        $estado['actualizado_en'] = $estado['actualizado_en'] ?? Helper::now()->toDateTimeString();

        self::disco()->put(
            self::ARCHIVO_CORRIDA,
            json_encode($estado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    /** Se olvida de la corrida anterior (para no mostrar una barra vieja). */
    public static function olvidarEstadoDeLaCorrida(): void
    {
        if (self::disco()->exists(self::ARCHIVO_CORRIDA)) {
            self::disco()->delete(self::ARCHIVO_CORRIDA);
        }
    }

    /**
     * La corrida quedó "en curso" y hace rato que no da señales: se interrumpió
     * (por ejemplo, si se apagó la máquina en el medio).
     */
    public static function laCorridaSeInterrumpio(): bool
    {
        $estado = self::estadoDeLaCorrida();

        if (($estado['estado'] ?? null) !== 'en_curso') {
            return false;
        }

        // La última señal del comando: si hace mucho que no dice nada, el
        // proceso ya no está.
        $ultima = $estado['actualizado_en'] ?? $estado['iniciada_en'] ?? null;

        if (! is_string($ultima) || $ultima === '') {
            return true;
        }

        try {
            $minutos = (Helper::now()->getTimestamp() - Carbon::parse($ultima)->getTimestamp()) / 60;

            return $minutos > self::MINUTOS_SIN_SENALES;
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * Escribe una línea en el log propio de la corrida.
     *
     * Es un archivo de texto aparte de los logs de la aplicación: ahí queda el
     * paso a paso de cada corrida (los progresos, lo que se saltó, las
     * observaciones y cómo terminó).
     */
    public static function anotarEnLaCorrida(string $linea): void
    {
        try {
            File::append(
                self::rutaDelLogDeLaCorrida(),
                '['.Helper::now()->format('Y-m-d H:i:s').'] '.$linea.PHP_EOL
            );
        } catch (\Throwable $e) {
            // Un log que no se puede escribir no puede tumbar la corrida.
            report($e);
        }
    }

    /** La ruta absoluta del log de la corrida. */
    public static function rutaDelLogDeLaCorrida(): string
    {
        // En las pruebas el log va aparte, para no ensuciar el de verdad.
        return app()->runningUnitTests()
            ? storage_path('logs/pruebas-planificacion-corrida.log')
            : storage_path(self::LOG_CORRIDA);
    }
}
