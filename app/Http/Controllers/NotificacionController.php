<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespuestasJson;
use App\Models\Notificacion;
use App\Notificaciones\BuzonDual;
use App\Notificaciones\Candidata;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Buzón de notificaciones · MySQL.
 *
 * La campanita y la bandeja principal leen de MySQL (la fuente de verdad) y
 * escriben en los dos motores a través de BuzonDual. Las acciones del AJAX
 * devuelven el mismo sobre del resto del planificador: { ok, message, data }.
 *
 * El endpoint "contador" es el que sostiene el tiempo real: además de devolver
 * los números, evalúa las reglas del día como máximo una vez por minuto y deja
 * las notificaciones nuevas en las dos fuentes. El comando programado
 * notificaciones:generar hace lo mismo aunque no haya nadie en pantalla.
 */
class NotificacionController extends Controller
{
    use RespuestasJson;

    public function __construct(protected BuzonDual $buzon) {}

    /* ======================================================================
     |  Pantallas
     ====================================================================== */

    /** La bandeja completa (MySQL), con sus filtros y su tabla. */
    public function index()
    {
        return view('notificaciones.index', [
            'tipos' => Candidata::TIPOS,
            'estados' => Notificacion::ESTADOS,
        ]);
    }

    /* ======================================================================
     |  Consultas del AJAX
     ====================================================================== */

    /**
     * Los números de la campanita y las últimas no leídas.
     *
     * Es la consulta periódica del navegador. Aprovecha para evaluar las
     * reglas (con la caché de un minuto) y devuelve el diagnóstico de Firestore
     * que ya esté cacheado: la comprobación en vivo la hace el panel al abrirse.
     */
    public function contador(): JsonResponse
    {
        try {
            $sincronizacion = $this->buzon->sincronizarSiToca();
            $conteo = $this->buzon->contar();

            return $this->jsonSuccess([
                'no_leidas' => $conteo['no_leidas'],
                'total' => $conteo['total'],
                'leidas' => $conteo['leidas'],
                'ultimas' => Notificacion::ultimasNoLeidas(5)
                    ->map(fn (Notificacion $n) => $n->aResumen())
                    ->all(),
                'sincronizacion' => [
                    'creadas' => (int) ($sincronizacion['creadas'] ?? 0),
                    'replicadas' => (int) ($sincronizacion['replicadas'] ?? 0),
                    'resueltas' => (int) ($sincronizacion['resueltas'] ?? 0),
                    'omitida' => (bool) ($sincronizacion['omitida'] ?? false),
                ],
            ]);
        } catch (Throwable $e) {
            $this->registrarFallo($e, 'consultar el contador de notificaciones');

            return $this->jsonError('No se pudo consultar el buzón de notificaciones.', [], 500);
        }
    }

    /** Los datos de la tabla de la bandeja MySQL. */
    public function tabla(Request $request): JsonResponse
    {
        $filtros = $this->filtros($request);

        try {
            $notificaciones = $this->buzon->listar($filtros);
            $conteo = $this->buzon->contar();

            return $this->jsonSuccess([
                'notificaciones' => $notificaciones,
                'total' => count($notificaciones),
                'conteo' => $conteo,
                'filtros' => array_filter($filtros, fn (mixed $valor) => trim((string) $valor) !== ''),
            ]);
        } catch (Throwable $e) {
            $this->registrarFallo($e, 'listar las notificaciones', ['filtros' => $filtros]);

            return $this->jsonError('No se pudo obtener el listado de notificaciones.', [], 500);
        }
    }

    /** Una notificación con todo su detalle, para el modal. */
    public function mostrar(string $notificacion): JsonResponse
    {
        try {
            $datos = $this->buzon->buscar($notificacion);

            if (! $datos) {
                return $this->jsonError('La notificación que buscas no existe o ya fue archivada.', [], 404);
            }

            return $this->jsonSuccess([
                'notificacion' => $datos,
                'plan_url' => $datos['daily_plan_id']
                    ? route('diario.show', ['dailyPlan' => $datos['daily_plan_id']])
                    : null,
            ]);
        } catch (Throwable $e) {
            $this->registrarFallo($e, 'consultar una notificación', ['uuid' => $notificacion]);

            return $this->jsonError('No se pudo consultar la notificación.', [], 500);
        }
    }

    /** Estado de las dos fuentes (para el diagnóstico del panel). */
    public function estado(): JsonResponse
    {
        try {
            $estado = $this->buzon->estado();

            return $this->jsonSuccess([
                'mysql' => $estado['mysql'],
                'firestore' => $this->buzon->estadoFirestore(true),
                'ultima_sincronizacion' => $estado['ultima_sincronizacion'],
            ]);
        } catch (Throwable $e) {
            $this->registrarFallo($e, 'consultar el estado del buzón');

            return $this->jsonError('No se pudo consultar el estado del buzón.', [], 500);
        }
    }

    /* ======================================================================
     |  Acciones
     ====================================================================== */

    /** Evalúa las reglas ahora, sin esperar a que pase el minuto de la caché. */
    public function sincronizar(): JsonResponse
    {
        try {
            $resultado = $this->buzon->sincronizar();
            $conteo = $this->buzon->contar();

            return $this->jsonSuccess([
                'sincronizacion' => $resultado,
                'conteo' => $conteo,
                'firestore' => $resultado['firestore'],
            ], $this->mensajeDeSincronizacion($resultado));
        } catch (Throwable $e) {
            $this->registrarFallo($e, 'sincronizar las notificaciones');

            return $this->jsonError('No se pudieron evaluar las reglas de notificación.', [], 500);
        }
    }

    public function marcarLeida(string $notificacion): JsonResponse
    {
        try {
            if (! $this->buzon->marcarLeida($notificacion)) {
                return $this->jsonError('La notificación ya no está disponible.', [], 404);
            }

            return $this->jsonSuccess(
                $this->respuestaDeEstado(),
                'Notificación marcada como leída.'
            );
        } catch (Throwable $e) {
            $this->registrarFallo($e, 'marcar una notificación como leída', ['uuid' => $notificacion]);

            return $this->jsonError('No se pudo marcar la notificación como leída.', [], 500);
        }
    }

    /** Marca como leídas las notificaciones seleccionadas con los checkbox. */
    public function marcarLote(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'uuids' => ['required', 'array', 'min:1', 'max:'.max(1, (int) config('notificaciones.maximo_lote', 100))],
            'uuids.*' => ['uuid'],
        ], [
            'uuids.required' => 'Selecciona al menos una notificación.',
            'uuids.array' => 'La selección de notificaciones no es válida.',
            'uuids.min' => 'Selecciona al menos una notificación.',
            'uuids.max' => 'Puedes marcar como máximo :max notificaciones de una vez.',
            'uuids.*.uuid' => 'Una de las notificaciones seleccionadas no es válida.',
        ]);

        try {
            $cantidad = $this->buzon->marcarVarias($datos['uuids']);

            return $this->jsonSuccess(
                $this->respuestaDeEstado() + ['actualizadas' => $cantidad],
                $cantidad > 0
                    ? $this->plural($cantidad, 'notificación marcada como leída', 'notificaciones marcadas como leídas')
                    : 'No había notificaciones pendientes por marcar.'
            );
        } catch (Throwable $e) {
            $this->registrarFallo($e, 'marcar varias notificaciones como leídas', ['cantidad' => count($datos['uuids'])]);

            return $this->jsonError('No se pudieron marcar las notificaciones seleccionadas.', [], 500);
        }
    }

    public function descartar(string $notificacion): JsonResponse
    {
        try {
            if (! $this->buzon->descartar($notificacion)) {
                return $this->jsonError('La notificación ya no está disponible.', [], 404);
            }

            return $this->jsonSuccess($this->respuestaDeEstado(), 'Notificación archivada.');
        } catch (Throwable $e) {
            $this->registrarFallo($e, 'archivar una notificación', ['uuid' => $notificacion]);

            return $this->jsonError('No se pudo archivar la notificación.', [], 500);
        }
    }

    public function descartarLote(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'uuids' => ['required', 'array', 'min:1', 'max:'.max(1, (int) config('notificaciones.maximo_lote', 100))],
            'uuids.*' => ['uuid'],
        ], [
            'uuids.required' => 'Selecciona al menos una notificación.',
            'uuids.min' => 'Selecciona al menos una notificación.',
            'uuids.max' => 'Puedes archivar como máximo :max notificaciones de una vez.',
            'uuids.*.uuid' => 'Una de las notificaciones seleccionadas no es válida.',
        ]);

        try {
            $cantidad = $this->buzon->descartarVarias($datos['uuids']);

            return $this->jsonSuccess(
                $this->respuestaDeEstado() + ['actualizadas' => $cantidad],
                $cantidad > 0
                    ? $this->plural($cantidad, 'notificación archivada', 'notificaciones archivadas')
                    : 'Las notificaciones seleccionadas ya no estaban en la bandeja.'
            );
        } catch (Throwable $e) {
            $this->registrarFallo($e, 'archivar varias notificaciones', ['cantidad' => count($datos['uuids'])]);

            return $this->jsonError('No se pudieron archivar las notificaciones seleccionadas.', [], 500);
        }
    }

    /* ======================================================================
     |  Auxiliares
     ====================================================================== */

    /** @return array{estado: string, tipo: string, q: string, limite: int} */
    private function filtros(Request $request): array
    {
        $estado = (string) $request->query('estado', 'todas');

        if (! array_key_exists($estado, Notificacion::ESTADOS)) {
            $estado = 'todas';
        }

        return [
            'estado' => $estado,
            'tipo' => (string) $request->query('tipo', ''),
            'q' => (string) $request->query('q', ''),
            'limite' => (int) config('notificaciones.limite_listado', 300),
        ];
    }

    /** El estado que necesita el JavaScript después de cada acción. */
    private function respuestaDeEstado(): array
    {
        $conteo = $this->buzon->contar();

        return [
            'conteo' => $conteo,
        ];
    }

    private function mensajeDeSincronizacion(array $resultado): string
    {
        $creadas = (int) ($resultado['creadas'] ?? 0);
        $replicadas = (int) ($resultado['replicadas'] ?? 0);
        $resueltas = (int) ($resultado['resueltas'] ?? 0);

        $partes = [
            $creadas > 0
                ? $this->plural($creadas, 'notificación nueva', 'notificaciones nuevas')
                : 'Sin notificaciones nuevas',
        ];

        if ($resueltas > 0) {
            $partes[] = $this->plural($resueltas, 'resuelta', 'resueltas');
        }

        if ($replicadas > 0) {
            $partes[] = $this->plural($replicadas, 'replicada en Firestore', 'replicadas en Firestore');
        }

        return implode(' · ', $partes).'.';
    }

    private function plural(int $cantidad, string $singular, string $plural): string
    {
        return $cantidad.' '.($cantidad === 1 ? $singular : $plural);
    }
}
