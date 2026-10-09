<?php

namespace App\Http\Controllers;

use App\Console\Commands\ImportarPlanificacion;
use App\Errores\RegistroDeErrores;
use App\Helpers\Helper;
use App\Models\PlanificacionImportacion;
use App\Planificacion\ArchivosDePlanificacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Módulo "Planificación periódica".
 *
 * Sirve la planilla estática del planificador y recibe el libro del período.
 * El módulo **sólo guarda**: no procesa nada. El libro queda en la cola hasta
 * que corre el cron de las 00:00 (`planificacion:importar`), que crea en el
 * diario los días que falten y deja acá el reporte de lo que hizo.
 *
 * Los archivos viven en el disco privado, así que la planilla se descarga con
 * una dirección fija del módulo y no queda expuesta en public/.
 */
class PlanificacionController extends Controller
{
    /** La pantalla del módulo: la planilla, la cola y el último reporte. */
    public function index(): View
    {
        try {
            $ultima = PlanificacionImportacion::ultimaCorrida();
            $cola = $this->htmlDeLaCola();

            return view('planificacion.index', array_merge($cola['datos'], [
                'huellaCola' => $cola['huella'],
                'planilla' => ArchivosDePlanificacion::datosDeLaPlantilla(),
                'plantillas' => ArchivosDePlanificacion::plantillas(),
                'ultima' => $ultima,
                'hojasDeLaUltima' => $ultima?->hojas ?? collect(),
                'horaDeLaCorrida' => ImportarPlanificacion::HORA,
                'pesoMaximo' => Helper::fileSize(ArchivosDePlanificacion::PESO_MAXIMO * 1024),
            ]));
        } catch (Throwable $e) {
            $this->registrarFallo($e, 'abrir el módulo de planificación periódica');

            return view('planificacion.index', array_merge($this->datosDeLaCola(), [
                'huellaCola' => null,
                'planilla' => ['existe' => false, 'nombre' => ArchivosDePlanificacion::PLANTILLA, 'peso' => null, 'modificado' => null],
                'plantillas' => [],
                'ultima' => null,
                'hojasDeLaUltima' => collect(),
                'horaDeLaCorrida' => ImportarPlanificacion::HORA,
                'pesoMaximo' => Helper::fileSize(ArchivosDePlanificacion::PESO_MAXIMO * 1024),
            ]))->with('error', 'No se pudo leer el estado del módulo.');
        }
    }

    /**
     * El bloque de la cola, ya armado: los datos, su HTML y la huella.
     *
     * La huella es lo que le permite al módulo no tocar la pantalla cuando nada
     * cambió: el refresco automático sólo cambia la tabla si la huella es otra.
     *
     * @return array{datos: array, html: string, huella: string}
     */
    private function htmlDeLaCola(): array
    {
        $datos = $this->datosDeLaCola();
        $html = view('planificacion._cola', $datos)->render();

        return ['datos' => $datos, 'html' => $html, 'huella' => md5($html)];
    }

    /**
     * Los datos del bloque de la cola.
     *
     * Los usan la pantalla del módulo y el endpoint que la refresca por AJAX,
     * así los dos arman exactamente lo mismo.
     *
     * @return array{libros: list<array>, enCola: int, carpetas: array<string, string>}
     */
    private function datosDeLaCola(): array
    {
        $libros = $this->libros();

        return [
            'libros' => $libros,
            'enCola' => count(array_filter(
                $libros,
                fn (array $libro) => $libro['estado'] === PlanificacionImportacion::EN_ESPERA
            )),
            'carpetas' => [
                'jobs' => ArchivosDePlanificacion::CARPETA_JOBS,
                'procesados' => ArchivosDePlanificacion::CARPETA_PROCESADOS,
                'descartados' => ArchivosDePlanificacion::CARPETA_DESCARTADOS,
                'errores' => ArchivosDePlanificacion::CARPETA_ERRORES,
            ],
        ];
    }

    /**
     * Descarga una planilla: la genérica (sin argumento) o la de un período.
     *
     * La dirección de la genérica es siempre la misma: si algún día se reemplaza
     * el archivo (porque cambió un catálogo), el link del módulo sigue sirviendo.
     * El nombre que llega se comprueba contra las planillas que existen de
     * verdad, así que no se puede pedir cualquier archivo del disco.
     */
    public function plantilla(?string $archivo = null): BinaryFileResponse|RedirectResponse
    {
        try {
            if ($archivo === null) {
                if (! ArchivosDePlanificacion::existePlantilla()) {
                    return redirect()
                        ->route('planificacion.index')
                        ->with('error', 'La planilla no está creada. Corre «php artisan planificacion:plantilla» y vuelve a intentarlo.');
                }

                $relativa = ArchivosDePlanificacion::rutaPlantilla();
                $nombre = ArchivosDePlanificacion::PLANTILLA;
            } else {
                $nombre = basename($archivo);

                if (! ArchivosDePlanificacion::esPlantilla($nombre)) {
                    return redirect()
                        ->route('planificacion.index')
                        ->with('error', 'Esa planilla no está en la carpeta de formatos.');
                }

                $relativa = ArchivosDePlanificacion::CARPETA_FORMATO.'/'.$nombre;
            }

            return response()->download(
                ArchivosDePlanificacion::rutaAbsoluta($relativa),
                $nombre,
                [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                    'Pragma' => 'no-cache',
                ]
            );
        } catch (Throwable $e) {
            $this->registrarFallo($e, 'descargar una planilla del planificador', ['archivo' => $archivo]);

            return redirect()
                ->route('planificacion.index')
                ->with('error', 'No se pudo descargar la planilla.');
        }
    }

    /**
     * Monta un libro en la cola.
     *
     * Sólo se guarda: el procesamiento es de la corrida de las 00:00. Si ya
     * había un libro con el mismo nombre, se reemplaza y su estado vuelve a
     * "en espera".
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $datos = $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx', 'max:'.ArchivosDePlanificacion::PESO_MAXIMO],
        ], [
            'archivo.required' => 'Elige el libro de Excel que vas a montar.',
            'archivo.file' => 'El archivo no llegó bien: vuelve a intentarlo.',
            'archivo.mimes' => 'El libro tiene que ser .xlsx (Excel).',
            'archivo.max' => 'El libro no puede pesar más de '.Helper::fileSize(ArchivosDePlanificacion::PESO_MAXIMO * 1024).'.',
        ]);

        try {
            $subido = $request->file('archivo');
            $nombre = ArchivosDePlanificacion::guardarLibro($subido);

            // Al reemplazar un libro, su detalle anterior ya no dice nada.
            $registro = PlanificacionImportacion::updateOrCreate(
                ['archivo' => $nombre],
                [
                    'nombre_original' => Helper::limit($subido->getClientOriginalName(), 120, ''),
                    'estado' => PlanificacionImportacion::EN_ESPERA,
                    'periodo_desde' => null,
                    'periodo_hasta' => null,
                    'cubierto_hasta' => null,
                    'en_bd_hasta' => null,
                    'dias_sin_hoja' => 0,
                    'procesado_en' => null,
                    'descartado_en' => null,
                    'subido_en' => Helper::now(),
                    'mensaje' => 'Montado y en espera de la corrida de las '.ImportarPlanificacion::HORA.'.',
                ]
            );

            $registro->hojas()->delete();

            $mensaje = 'El libro «'.$nombre.'» quedó en la cola. Se procesa en la corrida de las '
                .ImportarPlanificacion::HORA.'; hasta entonces no se toca la base de datos.';

            // El módulo lo manda por AJAX: contesta con el sobre de siempre y el
            // navegador refresca sólo la cola. Sin JavaScript, el formulario
            // sigue funcionando: vuelve al módulo con el aviso en la sesión.
            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => true,
                    'message' => $mensaje,
                    'data' => ['archivo' => $nombre],
                ], 201);
            }

            return redirect()
                ->route('planificacion.index')
                ->with('exito', $mensaje);
        } catch (Throwable $e) {
            $this->registrarFallo($e, 'montar un libro de planificación periódica', [
                'archivo' => $request->file('archivo')?->getClientOriginalName(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'No se pudo guardar el libro. Intenta de nuevo.',
                    'errors' => [],
                ], 500);
            }

            return redirect()
                ->route('planificacion.index')
                ->with('error', 'No se pudo guardar el libro. Intenta de nuevo.');
        }
    }

    /**
     * Saca un libro de la cola sin procesarlo.
     *
     * Es la forma de que un libro equivocado deje de participar en las
     * corridas: mientras esté en la cola, cualquier día que se borre desde el
     * listado volvería a cargarse desde ese Excel.
     */
    public function descartar(string $archivo): JsonResponse|RedirectResponse
    {
        try {
            $nombre = Helper::safeFileName($archivo);

            if (! ArchivosDePlanificacion::estaEnCola($nombre)) {
                if (request()->expectsJson()) {
                    return response()->json([
                        'ok' => false,
                        'message' => 'Ese libro ya no está en la cola.',
                        'errors' => [],
                    ], 404);
                }

                return redirect()
                    ->route('planificacion.index')
                    ->with('error', 'Ese libro ya no está en la cola.');
            }

            ArchivosDePlanificacion::descartar($nombre);

            PlanificacionImportacion::updateOrCreate(
                ['archivo' => $nombre],
                [
                    'estado' => PlanificacionImportacion::DESCARTADO,
                    'descartado_en' => Helper::now(),
                    'mensaje' => 'Se sacó de la cola a mano: no se vuelve a procesar.',
                ]
            );

            $mensaje = 'El libro «'.$nombre.'» salió de la cola y quedó en '
                .ArchivosDePlanificacion::CARPETA_DESCARTADOS.'/. La base de datos no se tocó.';

            if (request()->expectsJson()) {
                return response()->json([
                    'ok' => true,
                    'message' => $mensaje,
                    'data' => ['archivo' => $nombre],
                ]);
            }

            return redirect()
                ->route('planificacion.index')
                ->with('exito', $mensaje);
        } catch (Throwable $e) {
            $this->registrarFallo($e, 'sacar un libro de la cola', ['archivo' => $archivo]);

            if (request()->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'No se pudo sacar el libro de la cola.',
                    'errors' => [],
                ], 500);
            }

            return redirect()
                ->route('planificacion.index')
                ->with('error', 'No se pudo sacar el libro de la cola.');
        }
    }

    /**
     * La cola, sólo, para que el módulo la cambie sin recargar la página.
     *
     * Devuelve el mismo bloque que se ve en el módulo (la vista parcial), su
     * huella y los contadores. **Nunca dispara la corrida**: la importación sigue
     * siendo a las 00:00; esto sólo cuenta lo que hay.
     */
    public function cola(): JsonResponse
    {
        try {
            $cola = $this->htmlDeLaCola();

            return response()->json([
                'ok' => true,
                'message' => 'Cola actualizada.',
                'data' => [
                    'html' => $cola['html'],
                    'huella' => $cola['huella'],
                    'en_cola' => count(ArchivosDePlanificacion::librosEnCola()),
                    'enCola' => $cola['datos']['enCola'],
                    // Los nombres que siguen en la cola: el módulo los usa para
                    // quedarse sólo con la selección que sigue existiendo.
                    'archivos' => array_values(array_map(
                        fn (array $libro) => $libro['archivo'],
                        array_filter($cola['datos']['libros'], fn (array $libro) => $libro['en_cola'])
                    )),
                ],
            ]);
        } catch (Throwable $e) {
            $this->registrarFallo($e, 'consultar la cola de planificación periódica');

            return response()->json([
                'ok' => false,
                'message' => 'No se pudo actualizar la cola.',
                'errors' => [],
            ], 500);
        }
    }

    /**
     * Saca varios libros de la cola de una sola vez (AJAX).
     *
     * Es la misma acción que el botón de cada fila: los archivos no se borran
     * del disco, pasan a excel/descartados, así que se pueden recuperar. El
     * navegador los manda como una lista y acá se informa cuáles salieron y
     * cuáles no.
     */
    public function descartarVarios(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'archivos' => ['required', 'array', 'min:1'],
            'archivos.*' => ['required', 'string'],
        ], [
            'archivos.required' => 'Elige al menos un libro de la cola.',
            'archivos.min' => 'Elige al menos un libro de la cola.',
        ]);

        try {
            $eliminados = [];
            $fallos = [];

            foreach ($datos['archivos'] as $archivo) {
                $nombre = Helper::safeFileName($archivo);

                if (! ArchivosDePlanificacion::estaEnCola($nombre)) {
                    $fallos[] = $nombre.' (ya no estaba en la cola)';

                    continue;
                }

                if (! ArchivosDePlanificacion::descartar($nombre)) {
                    $fallos[] = $nombre.' (no se pudo mover)';

                    continue;
                }

                PlanificacionImportacion::updateOrCreate(
                    ['archivo' => $nombre],
                    [
                        'estado' => PlanificacionImportacion::DESCARTADO,
                        'descartado_en' => Helper::now(),
                        'mensaje' => 'Se sacó de la cola a mano: no se vuelve a procesar.',
                    ]
                );

                $eliminados[] = $nombre;
            }

            if ($eliminados === []) {
                return response()->json([
                    'ok' => false,
                    'message' => 'No se pudo eliminar ningún libro de la cola.',
                    'errors' => $fallos,
                ], 422);
            }

            $mensaje = count($eliminados) === 1
                ? 'El libro «'.$eliminados[0].'» fue eliminado de la cola.'
                : count($eliminados).' libros fueron eliminados de la cola.';

            if ($fallos !== []) {
                $mensaje .= ' Quedaron sin sacar: '.Helper::listToText($fallos).'.';
            }

            return response()->json([
                'ok' => true,
                'message' => $mensaje,
                'data' => [
                    'eliminados' => $eliminados,
                    'fallos' => $fallos,
                    'carpeta' => ArchivosDePlanificacion::CARPETA_DESCARTADOS,
                ],
            ]);
        } catch (Throwable $e) {
            $this->registrarFallo($e, 'eliminar varios libros de la cola', [
                'archivos' => $datos['archivos'] ?? [],
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'No se pudieron eliminar los libros de la cola.',
                'errors' => [],
            ], 500);
        }
    }

    /* ======================================================================
     |  Internos
     ====================================================================== */

    /**
     * Los libros que se muestran en el módulo: los que están en la cola y los
     * que ya pasaron por una corrida (o se descartaron).
     *
     * @return list<array{archivo: string, en_cola: bool, peso: ?string, subido: ?string, registro: ?PlanificacionImportacion, estado: string}>
     */
    private function libros(): array
    {
        $registros = PlanificacionImportacion::query()->get()->keyBy('archivo');
        $libros = [];
        $vistos = [];

        foreach (ArchivosDePlanificacion::librosEnCola() as $archivo) {
            $registro = $registros->get($archivo['nombre']);

            $libros[] = [
                'archivo' => $archivo['nombre'],
                'en_cola' => true,
                'peso' => Helper::fileSize($archivo['peso']),
                'modificado' => Helper::dateTime($archivo['modificado']),
                'registro' => $registro,
                'estado' => $registro?->estado ?? PlanificacionImportacion::EN_ESPERA,
            ];

            $vistos[$archivo['nombre']] = true;
        }

        // El historial: los que ya no están en la cola.
        foreach ($registros as $registro) {
            if (isset($vistos[$registro->archivo])) {
                continue;
            }

            $libros[] = [
                'archivo' => $registro->archivo,
                'en_cola' => false,
                'peso' => null,
                'modificado' => Helper::dateTime($registro->procesado_en ?? $registro->descartado_en ?? $registro->subido_en),
                'registro' => $registro,
                'estado' => $registro->estado,
            ];
        }

        return $libros;
    }

    /** Deja constancia del fallo, como el resto de los controladores. */
    private function registrarFallo(Throwable $e, string $operacion, array $contexto = []): void
    {
        report($e);

        RegistroDeErrores::deControlador($e, $operacion, $contexto);
    }
}
