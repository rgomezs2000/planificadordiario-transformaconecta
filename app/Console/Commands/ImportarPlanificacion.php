<?php

namespace App\Console\Commands;

use App\Errores\RegistroDeErrores;
use App\Helpers\Helper;
use App\Models\DailyPlan;
use App\Models\PlanificacionHoja;
use App\Models\PlanificacionImportacion;
use App\Planificacion\ArchivosDePlanificacion;
use App\Planificacion\ImportadorDePlanificacion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Carga en el planificador los días que falten de los libros montados.
 *
 *     php artisan planificacion:importar
 *     php artisan planificacion:importar --dry-run
 *     php artisan planificacion:importar --archivo=planilla-octubre.xlsx
 *
 * Es el comando que corre el cron todas las noches a las 00:00. Su regla es una
 * sola: **sólo crea los días que no están en la base**. Un día que ya existe no
 * se vuelve a cargar nunca —sea pasado, de hoy o futuro— y el Excel no pisa lo
 * que hay guardado. Si un día se elimina desde el listado, la corrida siguiente
 * lo vuelve a crear con lo que traiga el libro.
 *
 * Una hoja a la que le falte un campo obligatorio se salta completa y queda
 * anotada en el reporte; se reintenta en la corrida siguiente, cuando el libro
 * vuelva a montarse corregido.
 *
 * Con --dry-run no se escribe nada: ni en la base de datos ni en las carpetas.
 * Sirve para revisar un libro antes de dejarlo en la cola.
 */
class ImportarPlanificacion extends Command
{
    protected $signature = 'planificacion:importar
                            {--archivo= : un solo libro (el nombre dentro de excel/jobs o una ruta completa)}
                            {--dry-run : muestra qué haría, sin escribir en la base ni mover archivos}';

    protected $description = 'Crea en el diario los días que falten de los libros de Planificación periódica';

    /** La hora de la corrida diaria: la medianoche. La usan el cron y el módulo. */
    public const HORA = '00:00';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $libros = $this->libros();

        if ($libros === []) {
            $this->info('No hay libros en la cola ('.ArchivosDePlanificacion::CARPETA_JOBS.').');

            return self::SUCCESS;
        }

        $this->line('corrida: '.Helper::dateTime(Helper::now())
            .'  ·  hoy es '.Helper::longDate(Helper::today(), withWeekday: true)
            .'  ·  libros: '.count($libros));

        if ($dryRun) {
            $this->warn('--dry-run: no se escribe nada en la base de datos ni se mueve ningún archivo.');
        }

        $this->newLine();

        $errores = 0;
        $creados = 0;

        foreach ($libros as $libro) {
            try {
                $lectura = ImportadorDePlanificacion::leer($libro['ruta']);
            } catch (Throwable $e) {
                $errores++;
                $this->reportarFallo($libro['nombre'], $e, $dryRun);

                continue;
            }

            $this->mostrar($libro['nombre'], $lectura);

            if ($dryRun) {
                continue;
            }

            $resultado = $this->procesar($libro, $lectura);

            $creados += $resultado['creados'];
            $errores += $resultado['errores'];
        }

        $this->newLine();

        if ($dryRun) {
            $this->info('Revisión terminada. No se escribió nada: vuelve a correrlo sin --dry-run para cargar los días.');

            return self::SUCCESS;
        }

        $this->info('Corrida terminada: '.Helper::pluralize($creados, 'día creado', 'días creados').'.');

        return $errores === 0 ? self::SUCCESS : self::FAILURE;
    }

    /* ======================================================================
     |  Los libros de esta corrida
     ====================================================================== */

    /**
     * Los libros a procesar, en orden: el más viejo en la cola primero.
     *
     * @return list<array{nombre: string, ruta: string, en_cola: bool}>
     */
    private function libros(): array
    {
        $archivo = $this->option('archivo');

        if ($archivo) {
            // Una ruta completa: sirve para revisar un libro suelto con --dry-run.
            if (is_file($archivo)) {
                return [[
                    'nombre' => basename($archivo),
                    'ruta' => (string) realpath($archivo),
                    'en_cola' => false,
                ]];
            }

            // O el nombre de un libro que está en la cola.
            $nombre = Helper::safeFileName($archivo);

            if (! ArchivosDePlanificacion::estaEnCola($nombre)) {
                $this->error('No se encontró el libro «'.$archivo.'» en '.ArchivosDePlanificacion::CARPETA_JOBS.'.');

                return [];
            }

            return [[
                'nombre' => $nombre,
                'ruta' => ArchivosDePlanificacion::rutaAbsoluta(ArchivosDePlanificacion::rutaEnCola($nombre)),
                'en_cola' => true,
            ]];
        }

        return array_map(fn (array $libro) => [
            'nombre' => $libro['nombre'],
            'ruta' => ArchivosDePlanificacion::rutaAbsoluta($libro['ruta']),
            'en_cola' => true,
        ], ArchivosDePlanificacion::librosEnCola());
    }

    /* ======================================================================
     |  Mostrar el plan
     ====================================================================== */

    /** La tabla con lo que va a pasar hoja por hoja. */
    private function mostrar(string $nombre, array $lectura): void
    {
        $filas = [];

        foreach ($lectura['hojas'] as $hoja) {
            $filas[] = [
                $hoja['hoja'],
                $hoja['fecha'] ? Helper::date($hoja['fecha']) : '—',
                $this->etiqueta($hoja['accion']),
                $hoja['motivo'] ?? '—',
            ];
        }

        $this->line('<options=bold>Libro: '.$nombre.'</>');

        if ($filas === []) {
            $this->line('  El libro no tiene ninguna hoja con fecha.');
        } else {
            $this->table(['Hoja', 'Fecha', 'Acción', 'Motivo'], $filas);
        }

        $resumen = $lectura['resumen'];

        $this->line('  por crear: '.$resumen[ImportadorDePlanificacion::CREAR]
            .'  ·  ya estaban: '.$resumen[ImportadorDePlanificacion::OMITIR]
            .'  ·  por saltar: '.$resumen[ImportadorDePlanificacion::SALTAR]
            .'  ·  ignoradas: '.$resumen[ImportadorDePlanificacion::IGNORAR]);

        $this->line('  cubierto sin huecos hasta: '.($lectura['cubierto_hasta'] ? Helper::date($lectura['cubierto_hasta']) : '—')
            .'  ·  ya en la base hasta: '.($lectura['en_bd_hasta'] ? Helper::date($lectura['en_bd_hasta']) : '—')
            .'  ·  días ausentes en la base: '.count($lectura['huecos']));

        if ($lectura['sin_hoja'] !== []) {
            $this->warn('  El libro no trae '.Helper::pluralize(count($lectura['sin_hoja']), 'día pasado', 'días pasados')
                .' del período: '.Helper::listToText(array_map(fn (string $fecha) => Helper::date($fecha), $lectura['sin_hoja'])).'.');
        }

        foreach ($lectura['hojas'] as $hoja) {
            foreach ($hoja['avisos'] as $aviso) {
                $this->warn('  aviso · '.($hoja['fecha'] ? Helper::date($hoja['fecha']).': ' : $hoja['hoja'].': ').$aviso);
            }
        }

        $this->newLine();
    }

    /** Cómo se llama cada acción en pantalla. */
    private function etiqueta(string $accion): string
    {
        return match ($accion) {
            ImportadorDePlanificacion::CREAR => 'crear',
            ImportadorDePlanificacion::OMITIR => 'ya está',
            ImportadorDePlanificacion::SALTAR => 'saltar',
            ImportadorDePlanificacion::IGNORAR => 'ignorar',
            default => $accion,
        };
    }

    /* ======================================================================
     |  Procesar el libro
     ====================================================================== */

    /**
     * Crea los días que faltan y deja el registro del libro.
     *
     * @return array{creados: int, errores: int}
     */
    private function procesar(array $libro, array $lectura): array
    {
        $creados = 0;
        $errores = 0;
        $hojas = [];

        foreach ($lectura['hojas'] as $hoja) {
            $accion = $hoja['accion'];
            $motivo = $hoja['motivo'];

            if ($accion === ImportadorDePlanificacion::CREAR) {
                try {
                    $plan = DailyPlan::createDay($hoja['datos']);

                    $accion = PlanificacionHoja::CREADO;
                    $motivo = 'Diario del '.Helper::longDate($plan->plan_date, withWeekday: true).' creado.';
                    $creados++;
                } catch (Throwable $e) {
                    $accion = PlanificacionHoja::ERROR;
                    $motivo = 'No se pudo crear el día: '.$e->getMessage();
                    $errores++;

                    report($e);
                    RegistroDeErrores::deModelo($e, 'crear el día desde el Excel', [
                        'archivo' => $libro['nombre'],
                        'hoja' => $hoja['hoja'],
                        'plan_date' => $hoja['fecha'],
                    ]);
                }
            } else {
                $accion = match ($accion) {
                    ImportadorDePlanificacion::OMITIR => PlanificacionHoja::OMITIDO,
                    ImportadorDePlanificacion::IGNORAR => PlanificacionHoja::IGNORADO,
                    default => PlanificacionHoja::SALTADO,
                };
            }

            $hojas[] = [
                'hoja' => $hoja['hoja'],
                'fecha' => $hoja['fecha'],
                'accion' => $accion,
                'motivo' => $motivo,
                'avisos' => count($hoja['avisos']),
                'detalle' => $hoja['avisos'] === [] ? null : implode("\n", $hoja['avisos']),
                'procesado_en' => Helper::now(),
            ];
        }

        // Qué fechas tiene el libro y cuáles siguen sin estar en la base.
        $fechas = array_values(array_filter(array_column($hojas, 'fecha')));
        $faltan = $this->faltantes($fechas);
        $enBdHasta = $this->enBdHasta($fechas);
        $estado = $this->estado($lectura, $hojas, $faltan);

        $registro = $this->registrar($libro, $lectura, $hojas, $faltan, $enBdHasta, $estado);

        $this->mover($libro, $estado);

        $this->line('  → '.$registro->resumenEnPalabras());

        if ($faltan !== []) {
            $this->warn('  → quedan por cargar: '.Helper::listToText(array_map(fn (string $fecha) => Helper::date($fecha), $faltan)).'.');
        }

        $this->newLine();

        return ['creados' => $creados, 'errores' => $errores];
    }

    /** Guarda (o reemplaza) el registro del libro y el detalle de sus hojas. */
    private function registrar(
        array $libro,
        array $lectura,
        array $hojas,
        array $faltan,
        ?string $enBdHasta,
        string $estado
    ): PlanificacionImportacion {
        $registro = PlanificacionImportacion::firstOrNew(['archivo' => $libro['nombre']]);

        $registro->fill([
            'periodo_desde' => $lectura['desde'],
            'periodo_hasta' => $lectura['hasta'],
            'estado' => $estado,
            'hojas_total' => count($hojas),
            'hojas_creadas' => $this->contar($hojas, PlanificacionHoja::CREADO),
            'hojas_omitidas' => $this->contar($hojas, PlanificacionHoja::OMITIDO),
            'hojas_saltadas' => $this->contar($hojas, PlanificacionHoja::SALTADO),
            'hojas_ignoradas' => $this->contar($hojas, PlanificacionHoja::IGNORADO),
            'hojas_error' => $this->contar($hojas, PlanificacionHoja::ERROR),
            'cubierto_hasta' => $faltan === [] ? $lectura['hasta'] : $lectura['cubierto_hasta'],
            'en_bd_hasta' => $enBdHasta,
            'dias_sin_hoja' => count($lectura['sin_hoja']),
            'procesado_en' => Helper::now(),
            'descartado_en' => null,
            'mensaje' => $this->mensaje($estado, $faltan),
        ]);

        // La fecha de la subida se respeta: sólo se pone la primera vez.
        if (! $registro->exists) {
            $registro->subido_en = Helper::now();
        }

        $registro->save();

        $registro->hojas()->delete();

        foreach ($hojas as $hoja) {
            $registro->hojas()->create($hoja);
        }

        return $registro;
    }

    /** Cuántas hojas terminaron con esa acción. */
    private function contar(array $hojas, string $accion): int
    {
        return count(array_filter($hojas, fn (array $hoja) => $hoja['accion'] === $accion));
    }

    /**
     * Las fechas del libro que siguen sin estar en la base después de la
     * corrida: los huecos que quedaron.
     *
     * @param  list<string>  $fechas
     * @return list<string>
     */
    private function faltantes(array $fechas): array
    {
        if ($fechas === []) {
            return [];
        }

        return array_values(array_diff($fechas, $this->fechasEnBd($fechas)));
    }

    /** La fecha más grande del libro que ya está en la base. */
    private function enBdHasta(array $fechas): ?string
    {
        $enBd = $this->fechasEnBd($fechas);

        return $enBd === [] ? null : max($enBd);
    }

    /**
     * De esas fechas, cuáles ya están guardadas.
     *
     * @param  list<string>  $fechas
     * @return list<string>
     */
    private function fechasEnBd(array $fechas): array
    {
        if ($fechas === []) {
            return [];
        }

        return DailyPlan::query()
            ->whereIn(DB::raw('date(plan_date)'), $fechas)
            ->pluck('plan_date')
            ->map(fn (mixed $fecha) => Helper::date($fecha, 'Y-m-d'))
            ->all();
    }

    /** El estado del libro después de la corrida. */
    private function estado(array $lectura, array $hojas, array $faltan): string
    {
        if ($this->contar($hojas, PlanificacionHoja::ERROR) > 0) {
            return PlanificacionImportacion::ERROR;
        }

        // Un libro sin ninguna hoja con fecha no sirve como planificación.
        if (array_filter($hojas, fn (array $hoja) => $hoja['fecha'] !== null) === []) {
            return PlanificacionImportacion::ERROR;
        }

        return $faltan === [] ? PlanificacionImportacion::PROCESADO : PlanificacionImportacion::PARCIAL;
    }

    /** La frase que acompaña al estado. */
    private function mensaje(string $estado, array $faltan): ?string
    {
        return match ($estado) {
            PlanificacionImportacion::PROCESADO => 'Todas las fechas del libro están cargadas.',
            PlanificacionImportacion::PARCIAL => 'Quedan '.Helper::pluralize(count($faltan), 'día', 'días').' por cargar: revisa los motivos de las hojas saltadas.',
            PlanificacionImportacion::ERROR => 'El libro no se pudo procesar por completo.',
            default => null,
        };
    }

    /** Mueve el libro según cómo quedó: procesado, parcial (se queda) o error. */
    private function mover(array $libro, string $estado): void
    {
        if (! $libro['en_cola']) {
            return;
        }

        if ($estado === PlanificacionImportacion::PROCESADO) {
            ArchivosDePlanificacion::moverDeLaCola($libro['nombre'], ArchivosDePlanificacion::CARPETA_PROCESADOS);
            $this->line('  → el libro pasó a '.ArchivosDePlanificacion::CARPETA_PROCESADOS.'/.');

            return;
        }

        if ($estado === PlanificacionImportacion::ERROR) {
            ArchivosDePlanificacion::moverDeLaCola($libro['nombre'], ArchivosDePlanificacion::CARPETA_ERRORES);
            $this->line('  → el libro pasó a '.ArchivosDePlanificacion::CARPETA_ERRORES.'/.');
        }
    }

    /* ======================================================================
     |  Fallos
     ====================================================================== */

    /** Un libro que no se pudo ni abrir. */
    private function reportarFallo(string $nombre, Throwable $e, bool $dryRun): void
    {
        report($e);

        RegistroDeErrores::registrar($e, 500, 'comando', [
            'operacion' => 'leer un libro de planificación periódica',
            'comando' => 'planificacion:importar',
            'archivo' => $nombre,
            'dry_run' => $dryRun,
        ]);

        $this->error('Libro: '.$nombre.' · no se pudo leer: '.$e->getMessage());

        if ($dryRun) {
            return;
        }

        PlanificacionImportacion::updateOrCreate(
            ['archivo' => $nombre],
            [
                'estado' => PlanificacionImportacion::ERROR,
                'procesado_en' => Helper::now(),
                'mensaje' => 'No se pudo leer el libro: '.$e->getMessage(),
            ]
        );

        ArchivosDePlanificacion::moverDeLaCola($nombre, ArchivosDePlanificacion::CARPETA_ERRORES);
    }
}
