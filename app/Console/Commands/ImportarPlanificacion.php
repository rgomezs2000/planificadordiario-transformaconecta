<?php

namespace App\Console\Commands;

use App\Errores\RegistroDeErrores;
use App\Excel\PlantillaDelPlanificador;
use App\Helpers\Helper;
use App\Models\DailyPlan;
use App\Models\PlanificacionHoja;
use App\Models\PlanificacionImportacion;
use App\Planificacion\ArchivosDePlanificacion;
use App\Planificacion\ImportadorDePlanificacion;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;
use ZipArchive;

/**
 * Carga en el planificador los días que falten de los libros montados.
 *
 *     php artisan planificacion:importar
 *     php artisan planificacion:importar --dry-run
 *     php artisan planificacion:importar --archivo=planilla-octubre.xlsx
 *
 * Es el comando que corre el cron todas las noches a las 00:00 y **el mismo** que
 * dispara el botón "Ejecutar corrida" del módulo: no hay una versión para cada
 * uno ni opciones de más (el nombre sale de `COMANDO`, que es también el que usa
 * el horario del cron). Su regla es una sola: **sólo crea los días que no están
 * en la base**. Un día que ya existe no se vuelve a cargar nunca —sea pasado, de
 * hoy o futuro— y el Excel no pisa lo que hay guardado. Si un día se elimina
 * desde el listado, la corrida siguiente lo vuelve a crear con lo que traiga el
 * libro.
 *
 * Una hoja a la que le falte un campo obligatorio se salta completa y queda
 * anotada en el reporte; se reintenta en la corrida siguiente, cuando el libro
 * vuelva a montarse corregido.
 *
 * Mientras trabaja deja dos rastros, además de lo que se ve en pantalla:
 *
 *   - storage/app/private/excel/corrida.json   cómo va (lo lee la barra de progreso)
 *   - storage/logs/planificacion-corrida.log   el paso a paso y el resumen final
 *
 * Con --dry-run no se escribe nada: ni en la base de datos, ni en las carpetas,
 * ni en esos dos archivos. Sirve para revisar un libro antes de dejarlo en la cola.
 */
class ImportarPlanificacion extends Command
{
    /**
     * El nombre del comando: el mismo que programa el cron (routes/console.php) y
     * el mismo que arranca el botón del módulo. Está acá una sola vez, para que no
     * puedan separarse.
     */
    public const COMANDO = 'planificacion:importar';

    protected $signature = self::COMANDO.'
                            {--archivo= : un solo libro (el nombre dentro de excel/jobs o una ruta completa)}
                            {--dry-run : muestra qué haría, sin escribir en la base ni mover archivos}';

    protected $description = 'Crea en el diario los días que falten de los libros de Planificación periódica';

    /** La hora de la corrida diaria: la medianoche. La usan el cron y el módulo. */
    public const HORA = '00:00';

    /** Cómo puede terminar una corrida. */
    public const COMPLETADA = 'completada';

    public const CON_OBSERVACIONES = 'con_observaciones';

    public const CON_ERRORES = 'con_errores';

    public const SIN_LIBROS = 'sin_libros';

    /** Cómo va la corrida: es lo que se guarda en excel/corrida.json. */
    private array $corrida = [];

    /** Los pasos previstos y los hechos: con eso se calcula el porcentaje. */
    private int $pasos = 0;

    private int $hechos = 0;

    /** Todo lo que quedó anotado para revisar (hojas saltadas, avisos, errores). */
    private array $observaciones = [];

    private array $contadores = [
        'creados' => 0,
        'omitidos' => 0,
        'saltados' => 0,
        'ignorados' => 0,
        'errores' => 0,
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $libros = $this->libros();

        // La revisión (--dry-run) no es una corrida: no toca el progreso ni el log.
        if (! $dryRun) {
            $this->empezarLaCorrida($libros);
        }

        if ($libros === []) {
            $this->info('No hay libros en la cola ('.ArchivosDePlanificacion::CARPETA_JOBS.').');

            if (! $dryRun) {
                $this->terminarLaCorrida(self::SIN_LIBROS, 'No hay ningún libro en la cola para procesar.');
            }

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
            $this->paso('Leyendo «'.$libro['nombre'].'»…');

            try {
                $lectura = ImportadorDePlanificacion::leer($libro['ruta']);
            } catch (Throwable $e) {
                $errores++;
                $this->reportarFallo($libro['nombre'], $e, $dryRun);
                $this->anotar('Error · libro '.$libro['nombre'].': no se pudo leer ('.$e->getMessage().').');
                $this->observaciones[] = 'No se pudo leer el libro «'.$libro['nombre'].'»: '.$e->getMessage();
                $this->contadores['errores']++;
                $this->avanzar();

                continue;
            }

            $this->avanzar();
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

        $this->terminarLaCorrida($this->resultado($errores), null, $creados);

        return $errores === 0 ? self::SUCCESS : self::FAILURE;
    }

    /* ======================================================================
     |  La corrida: progreso, log y resultado
     ====================================================================== */

    /**
     * Prepara el estado de la corrida y cuenta los pasos previstos.
     *
     * Los pasos son: uno por libro (leerlo) y uno por hoja (procesarla). Para
     * saber cuántas hojas hay sin abrir cada libro entero se mira el zip: el
     * listado de hojas está en xl/workbook.xml y leerlo es instantáneo.
     *
     * @param  list<array{nombre: string, ruta: string, en_cola: bool}>  $libros
     */
    private function empezarLaCorrida(array $libros): void
    {
        $hojas = 0;

        foreach ($libros as $libro) {
            $hojas += $this->contarHojas($libro['ruta']);
        }

        $this->pasos = max(count($libros) + $hojas, count($libros), 1);
        $this->hechos = 0;

        $this->corrida = [
            'estado' => 'en_curso',
            'iniciada_en' => Helper::now()->toDateTimeString(),
            'terminada_en' => null,
            'libros' => count($libros),
            'hojas' => $hojas,
            'pasos' => $this->pasos,
            'hechos' => 0,
            'porcentaje' => 0,
            'paso' => $libros === [] ? 'No hay libros en la cola.' : 'Preparando la corrida…',
            'resultado' => null,
            'resultado_en_palabras' => null,
            'mensaje' => null,
            'resumen' => $this->contadores,
            'observaciones' => [],
        ];

        $this->guardarLaCorrida();

        $this->anotar(str_repeat('=', 20));
        $this->anotar('Corrida iniciada · '
            .Helper::pluralize(count($libros), 'libro', 'libros').' · '
            .Helper::pluralize($hojas, 'hoja', 'hojas'));

        if ($libros === []) {
            $this->anotar('No hay libros en la cola: no se procesó nada.');
        }
    }

    /** Anota el paso que se está dando. */
    private function paso(string $texto): void
    {
        if ($this->corrida === []) {
            return;
        }

        $this->corrida['paso'] = $texto;

        $this->guardarLaCorrida();
    }

    /** Suma un paso hecho y actualiza el porcentaje. */
    private function avanzar(?string $detalle = null): void
    {
        if ($this->corrida === []) {
            return;
        }

        $this->hechos++;

        $this->corrida['hechos'] = $this->hechos;
        // Hasta que no termina no se muestra el 100%: si el libro traía más
        // hojas de las contadas, el porcentaje no se pasa de 99.
        $this->corrida['porcentaje'] = $this->pasos > 0
            ? min(99, (int) round($this->hechos * 100 / $this->pasos))
            : 0;

        if ($detalle !== null) {
            $this->corrida['paso'] = $detalle;
            $this->anotar($this->corrida['porcentaje'].'% · '.$detalle);
        }

        $this->guardarLaCorrida();
    }

    /** Cierra la corrida: deja el resultado en el estado y el resumen en el log. */
    private function terminarLaCorrida(string $resultado, ?string $mensaje = null, int $creados = 0): void
    {
        if ($this->corrida === []) {
            return;
        }

        $this->corrida['estado'] = 'terminada';
        $this->corrida['terminada_en'] = Helper::now()->toDateTimeString();
        $this->corrida['porcentaje'] = 100;
        $this->corrida['hechos'] = $this->pasos;
        $this->corrida['paso'] = 'Corrida terminada.';
        $this->corrida['resultado'] = $resultado;
        $this->corrida['resultado_en_palabras'] = $this->resultadoEnPalabras($resultado);
        $this->corrida['mensaje'] = $mensaje ?? $this->mensajeDelResultado($resultado);
        $this->corrida['resumen'] = $this->contadores;
        $this->corrida['observaciones'] = $this->observaciones;

        $this->guardarLaCorrida();

        // El resumen final, en el log de la corrida.
        $this->anotar('100% · Corrida terminada');
        $this->anotar('Resumen · creados: '.$this->contadores['creados']
            .' · ya estaban: '.$this->contadores['omitidos']
            .' · saltados: '.$this->contadores['saltados']
            .' · ignorados: '.$this->contadores['ignorados']
            .' · errores: '.$this->contadores['errores']
            .' · días creados en esta corrida: '.$creados);
        $this->anotar('Resultado · '.$this->corrida['resultado_en_palabras']
            .($this->corrida['mensaje'] ? ' · '.$this->corrida['mensaje'] : ''));

        foreach ($this->observaciones as $observacion) {
            $this->anotar('Observación · '.$observacion);
        }

        if ($this->observaciones === []) {
            $this->anotar('Sin observaciones: no quedó nada pendiente.');
        }
    }

    /** Guarda cómo va la corrida, para que el módulo muestre el progreso. */
    private function guardarLaCorrida(): void
    {
        if ($this->corrida === []) {
            return;
        }

        ArchivosDePlanificacion::guardarEstadoDeLaCorrida($this->corrida);
    }

    /** Escribe una línea en el log propio de la corrida. */
    private function anotar(string $linea): void
    {
        ArchivosDePlanificacion::anotarEnLaCorrida($linea);

        if ($this->output->isVerbose()) {
            $this->line('  · '.$linea);
        }
    }

    /**
     * Cuenta las hojas de un libro sin abrirlo del todo (lee el zip).
     *
     * El listado de hojas está en xl/workbook.xml, así que leerlo es instantáneo;
     * se descuentan las hojas que la corrida ignora (INSTRUCCIONES y LISTAS)
     * para que el porcentaje del progreso sea el de verdad.
     */
    private function contarHojas(string $ruta): int
    {
        if (! is_file($ruta) || ! class_exists(ZipArchive::class)) {
            return 0;
        }

        $zip = new ZipArchive;

        if ($zip->open($ruta) !== true) {
            return 0;
        }

        $xml = $zip->getFromName('xl/workbook.xml');

        $zip->close();

        if (! is_string($xml)) {
            return 0;
        }

        if (preg_match_all('/<sheet[^>]*\sname="([^"]*)"/', $xml, $coincidencias) === 0) {
            // Si no se pudieron leer los nombres, al menos se cuentan las hojas.
            return (int) preg_match_all('/<sheet[\s>]/', $xml);
        }

        $cuentan = array_filter(
            $coincidencias[1],
            fn (string $nombre) => ! in_array(mb_strtoupper(trim($nombre)), PlantillaDelPlanificador::HOJAS_IGNORADAS, true)
        );

        return count($cuentan);
    }

    /** Cómo terminó la corrida, para el módulo. */
    private function resultado(int $errores): string
    {
        if ($errores > 0 || $this->contadores['errores'] > 0) {
            return self::CON_ERRORES;
        }

        if ($this->observaciones !== []) {
            return self::CON_OBSERVACIONES;
        }

        return self::COMPLETADA;
    }

    private function resultadoEnPalabras(string $resultado): string
    {
        return match ($resultado) {
            self::COMPLETADA => 'Corrida completada',
            self::CON_OBSERVACIONES => 'Corrida completada con observaciones',
            self::CON_ERRORES => 'Corrida terminada con errores',
            self::SIN_LIBROS => 'No había nada para procesar',
            default => 'Corrida terminada',
        };
    }

    private function mensajeDelResultado(string $resultado): string
    {
        return match ($resultado) {
            self::COMPLETADA => 'Todo lo que traía el libro quedó cargado, sin nada pendiente.',
            self::CON_OBSERVACIONES => 'Se cargó lo que se pudo; quedó algo para revisar (mirá las observaciones).',
            self::CON_ERRORES => 'Hubo errores: revisá el log de la corrida y el detalle del libro.',
            self::SIN_LIBROS => 'No hay ningún libro en la cola para procesar.',
            default => '',
        };
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
     * Va hoja por hoja para poder mostrar el progreso de la corrida y anotar cada
     * observación en el log a medida que aparece.
     *
     * @return array{creados: int, errores: int}
     */
    private function procesar(array $libro, array $lectura): array
    {
        $creados = 0;
        $errores = 0;
        $hojas = [];

        foreach ($lectura['hojas'] as $hoja) {
            $fila = $this->procesarHoja($libro, $hoja);

            $hojas[] = $fila;

            if ($fila['accion'] === PlanificacionHoja::CREADO) {
                $creados++;
            }

            if ($fila['accion'] === PlanificacionHoja::ERROR) {
                $errores++;
            }

            $this->contadores[match ($fila['accion']) {
                PlanificacionHoja::CREADO => 'creados',
                PlanificacionHoja::OMITIDO => 'omitidos',
                PlanificacionHoja::SALTADO => 'saltados',
                PlanificacionHoja::IGNORADO => 'ignorados',
                default => 'errores',
            }]++;

            // Lo que hay que revisar: una hoja saltada, un error, o un día que
            // entró pero con algún aviso.
            if ($fila['accion'] === PlanificacionHoja::SALTADO || $fila['accion'] === PlanificacionHoja::ERROR) {
                $this->observaciones[] = $this->enPalabras($fila).': '.$fila['motivo'];
            }

            if (in_array($fila['accion'], [PlanificacionHoja::CREADO, PlanificacionHoja::OMITIDO], true)) {
                foreach ($hoja['avisos'] as $aviso) {
                    $this->observaciones[] = $this->enPalabras($fila).': '.$aviso;
                }
            }

            $this->avanzar($libro['nombre'].' · '.$this->enPalabras($fila).' · '.$this->etiquetaDeAccion($fila['accion']));
        }

        // Qué fechas tiene el libro y cuáles siguen sin estar en la base.
        $fechas = array_values(array_filter(array_column($hojas, 'fecha')));
        $faltan = $this->faltantes($fechas);
        $enBdHasta = $this->enBdHasta($fechas);
        $estado = $this->estado($lectura, $hojas, $faltan);

        $registro = $this->registrar($libro, $lectura, $hojas, $faltan, $enBdHasta, $estado);

        $this->mover($libro, $estado);

        $this->line('  → '.$registro->resumenEnPalabras());
        $this->anotar('Libro '.$libro['nombre'].' · '.$registro->resumenEnPalabras()
            .' · estado: '.$registro->etiquetaDeEstado().'.');

        if ($faltan !== []) {
            $this->warn('  → quedan por cargar: '.Helper::listToText(array_map(fn (string $fecha) => Helper::date($fecha), $faltan)).'.');
            $this->anotar('Libro '.$libro['nombre'].' · quedan por cargar: '
                .Helper::listToText(array_map(fn (string $fecha) => Helper::date($fecha), $faltan)).'.');
        }

        $this->newLine();

        return ['creados' => $creados, 'errores' => $errores];
    }

    /**
     * Procesa una hoja: crea el día si falta, y si no anota por qué no se hizo.
     *
     * @return array{hoja: string, fecha: ?string, accion: string, motivo: ?string, avisos: int, detalle: ?string, procesado_en: Carbon}
     */
    private function procesarHoja(array $libro, array $hoja): array
    {
        $accion = $hoja['accion'];
        $motivo = $hoja['motivo'];

        if ($accion === ImportadorDePlanificacion::CREAR) {
            try {
                $plan = DailyPlan::createDay($hoja['datos']);

                $accion = PlanificacionHoja::CREADO;
                $motivo = 'Diario del '.Helper::longDate($plan->plan_date, withWeekday: true).' creado.';
            } catch (Throwable $e) {
                $accion = PlanificacionHoja::ERROR;
                $motivo = 'No se pudo crear el día: '.$e->getMessage();

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

        return [
            'hoja' => $hoja['hoja'],
            'fecha' => $hoja['fecha'],
            'accion' => $accion,
            'motivo' => $motivo,
            'avisos' => count($hoja['avisos']),
            'detalle' => $hoja['avisos'] === [] ? null : implode("\n", $hoja['avisos']),
            'procesado_en' => Helper::now(),
        ];
    }

    /** La hoja, con su fecha, para el log y las observaciones. */
    private function enPalabras(array $fila): string
    {
        return 'Hoja '.$fila['hoja'].($fila['fecha'] ? ' ('.Helper::date($fila['fecha']).')' : '');
    }

    /** Cómo se llama cada acción, en el progreso de la corrida. */
    private function etiquetaDeAccion(string $accion): string
    {
        return match ($accion) {
            PlanificacionHoja::CREADO => 'creado',
            PlanificacionHoja::OMITIDO => 'ya estaba',
            PlanificacionHoja::SALTADO => 'saltado',
            PlanificacionHoja::IGNORADO => 'ignorado',
            PlanificacionHoja::ERROR => 'con error',
            default => $accion,
        };
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
