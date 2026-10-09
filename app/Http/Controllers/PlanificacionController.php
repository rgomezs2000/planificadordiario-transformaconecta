<?php

namespace App\Http\Controllers;

use App\Console\Commands\ImportarPlanificacion;
use App\Errores\RegistroDeErrores;
use App\Helpers\Helper;
use App\Models\PlanificacionImportacion;
use App\Planificacion\ArchivosDePlanificacion;
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
            $libros = $this->libros();
            $ultima = PlanificacionImportacion::ultimaCorrida();

            return view('planificacion.index', [
                'planilla' => ArchivosDePlanificacion::datosDeLaPlantilla(),
                'libros' => $libros,
                'enCola' => count(array_filter($libros, fn (array $libro) => $libro['estado'] === PlanificacionImportacion::EN_ESPERA)),
                'ultima' => $ultima,
                'hojasDeLaUltima' => $ultima?->hojas ?? collect(),
                'horaDeLaCorrida' => ImportarPlanificacion::HORA,
                'pesoMaximo' => Helper::fileSize(ArchivosDePlanificacion::PESO_MAXIMO * 1024),
                'carpetas' => [
                    'jobs' => ArchivosDePlanificacion::CARPETA_JOBS,
                    'procesados' => ArchivosDePlanificacion::CARPETA_PROCESADOS,
                    'errores' => ArchivosDePlanificacion::CARPETA_ERRORES,
                ],
            ]);
        } catch (Throwable $e) {
            $this->registrarFallo($e, 'abrir el módulo de planificación periódica');

            return view('planificacion.index', [
                'planilla' => ['existe' => false, 'nombre' => ArchivosDePlanificacion::PLANTILLA, 'peso' => null, 'modificado' => null],
                'libros' => [],
                'enCola' => 0,
                'ultima' => null,
                'hojasDeLaUltima' => collect(),
                'horaDeLaCorrida' => ImportarPlanificacion::HORA,
                'pesoMaximo' => Helper::fileSize(ArchivosDePlanificacion::PESO_MAXIMO * 1024),
                'carpetas' => [
                    'jobs' => ArchivosDePlanificacion::CARPETA_JOBS,
                    'procesados' => ArchivosDePlanificacion::CARPETA_PROCESADOS,
                    'errores' => ArchivosDePlanificacion::CARPETA_ERRORES,
                ],
            ])->with('error', 'No se pudo leer el estado del módulo.');
        }
    }

    /**
     * Descarga la planilla en blanco.
     *
     * La dirección es siempre la misma: si algún día se reemplaza el archivo
     * (porque cambió un catálogo), el link del módulo sigue sirviendo.
     */
    public function plantilla(): BinaryFileResponse|RedirectResponse
    {
        try {
            if (! ArchivosDePlanificacion::existePlantilla()) {
                return redirect()
                    ->route('planificacion.index')
                    ->with('error', 'La planilla no está creada. Corre «php artisan planificacion:plantilla» y vuelve a intentarlo.');
            }

            return response()->download(
                ArchivosDePlanificacion::rutaAbsoluta(ArchivosDePlanificacion::rutaPlantilla()),
                ArchivosDePlanificacion::PLANTILLA,
                [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                    'Pragma' => 'no-cache',
                ]
            );
        } catch (Throwable $e) {
            $this->registrarFallo($e, 'descargar la planilla del planificador');

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
    public function store(Request $request): RedirectResponse
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

            return redirect()
                ->route('planificacion.index')
                ->with('exito', 'El libro «'.$nombre.'» quedó en la cola. Se procesa en la corrida de las '.ImportarPlanificacion::HORA.'; hasta entonces no se toca la base de datos.');
        } catch (Throwable $e) {
            $this->registrarFallo($e, 'montar un libro de planificación periódica', [
                'archivo' => $request->file('archivo')?->getClientOriginalName(),
            ]);

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
    public function descartar(string $archivo): RedirectResponse
    {
        try {
            $nombre = Helper::safeFileName($archivo);

            if (! ArchivosDePlanificacion::estaEnCola($nombre)) {
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

            return redirect()
                ->route('planificacion.index')
                ->with('exito', 'El libro «'.$nombre.'» salió de la cola y quedó en '.ArchivosDePlanificacion::CARPETA_DESCARTADOS.'/. La base de datos no se tocó.');
        } catch (Throwable $e) {
            $this->registrarFallo($e, 'sacar un libro de la cola', ['archivo' => $archivo]);

            return redirect()
                ->route('planificacion.index')
                ->with('error', 'No se pudo sacar el libro de la cola.');
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
