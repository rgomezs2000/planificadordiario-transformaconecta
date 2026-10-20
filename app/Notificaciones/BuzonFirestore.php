<?php

namespace App\Notificaciones;

use App\Models\NotificacionFirestore;
use App\Notificaciones\Contratos\Buzon;
use App\Notificaciones\Firestore\ClienteFirestore;

/**
 * Buzón sobre Firestore (paradigma NoSQL).
 *
 * Lee y escribe la colección de config/firebase.php. Cada documento usa el
 * mismo uuid que la fila de MySQL y los mismos nombres de campo, así la
 * bandeja muestra la misma información desde los dos motores.
 *
 * Si Firestore no responde, los errores suben al controlador: el buzón
 * principal sigue funcionando con MySQL y el panel NoSQL muestra el aviso.
 */
class BuzonFirestore implements Buzon
{
    public function __construct(protected ClienteFirestore $cliente) {}

    public function nombre(): string
    {
        return 'firestore';
    }

    public function disponible(): bool
    {
        return $this->cliente->habilitado() && $this->cliente->configurado();
    }

    public function listar(array $filtros = []): array
    {
        $limite = max(1, (int) ($filtros['limite'] ?? config('notificaciones.limite_listado', 300)));

        $notificaciones = array_map(
            fn (array $documento) => NotificacionFirestore::desdeDocumento($documento)->aArreglo(),
            $this->cliente->listar(max($limite, 1))
        );

        $estado = trim((string) ($filtros['estado'] ?? 'todas'));
        $tipo = trim((string) ($filtros['tipo'] ?? ''));
        $busqueda = mb_strtolower(trim((string) ($filtros['q'] ?? '')));

        $filtradas = array_values(array_filter(
            $notificaciones,
            function (array $notificacion) use ($estado, $tipo, $busqueda) {
                if ($notificacion['descartada']) {
                    return false;
                }

                if ($estado === 'no_leidas' && $notificacion['leida']) {
                    return false;
                }

                if ($estado === 'leidas' && ! $notificacion['leida']) {
                    return false;
                }

                if ($tipo !== '' && $notificacion['tipo'] !== $tipo) {
                    return false;
                }

                if ($busqueda !== '') {
                    $texto = mb_strtolower($notificacion['titulo'].' '.$notificacion['mensaje']);

                    if (! str_contains($texto, $busqueda)) {
                        return false;
                    }
                }

                return true;
            }
        ));

        usort(
            $filtradas,
            fn (array $a, array $b) => strcmp(
                (string) ($b['fecha_hora_iso'] ?? ''),
                (string) ($a['fecha_hora_iso'] ?? '')
            )
        );

        return array_slice($filtradas, 0, $limite);
    }

    public function contar(): array
    {
        $notificaciones = $this->listar([
            'limite' => max(300, (int) config('notificaciones.limite_listado', 300)),
        ]);

        $noLeidas = count(array_filter($notificaciones, fn (array $n) => ! $n['leida']));

        return [
            'total' => count($notificaciones),
            'no_leidas' => $noLeidas,
            'leidas' => count($notificaciones) - $noLeidas,
        ];
    }

    public function buscar(string $uuid): ?array
    {
        $documento = $this->cliente->obtener($uuid);

        if (! $documento) {
            return null;
        }

        $notificacion = NotificacionFirestore::desdeDocumento($documento);

        return $notificacion->estaDescartada() ? null : $notificacion->aArreglo();
    }

    public function marcarLeida(string $uuid): bool
    {
        $documento = $this->cliente->obtener($uuid);

        if (! $documento) {
            return false;
        }

        $notificacion = NotificacionFirestore::desdeDocumento($documento);

        if ($notificacion->estaDescartada()) {
            return false;
        }

        if ($notificacion->estaLeida()) {
            return true;
        }

        $this->cliente->actualizar($uuid, $notificacion->camposParaLeer());

        return true;
    }

    public function marcarVarias(array $uuids): int
    {
        $marcadas = 0;

        foreach (array_slice(array_values(array_unique($uuids)), 0, $this->maximoLote()) as $uuid) {
            if (is_string($uuid) && $this->marcarLeida($uuid)) {
                $marcadas++;
            }
        }

        return $marcadas;
    }

    public function descartar(string $uuid): bool
    {
        $documento = $this->cliente->obtener($uuid);

        if (! $documento) {
            return false;
        }

        $notificacion = NotificacionFirestore::desdeDocumento($documento);

        if ($notificacion->estaDescartada()) {
            return true;
        }

        $this->cliente->actualizar($uuid, $notificacion->camposParaDescartar());

        return true;
    }

    public function descartarVarias(array $uuids): int
    {
        $descartadas = 0;

        foreach (array_slice(array_values(array_unique($uuids)), 0, $this->maximoLote()) as $uuid) {
            if (is_string($uuid) && $this->descartar($uuid)) {
                $descartadas++;
            }
        }

        return $descartadas;
    }

    public function estado(): array
    {
        return array_merge($this->cliente->probar(), [
            'fuente' => 'firestore',
            'etiqueta' => 'Firestore',
            'motor' => 'NoSQL',
        ]);
    }

    /** Protección para que un lote enorme no se convierta en cientos de llamadas. */
    protected function maximoLote(): int
    {
        return max(1, (int) config('notificaciones.maximo_lote', 100));
    }
}
