<?php

namespace App\Notificaciones;

use App\Models\Notificacion;
use App\Notificaciones\Contratos\Buzon;

/**
 * Buzón sobre MySQL (paradigma SQL).
 *
 * Es una capa fina: las consultas viven en el modelo Notificacion. Existe para
 * que el controlador no sepa si la notificación salió de la tabla o del espejo
 * de Firestore.
 */
class BuzonMysql implements Buzon
{
    public function nombre(): string
    {
        return 'mysql';
    }

    public function listar(array $filtros = []): array
    {
        return Notificacion::listado($filtros)
            ->map(fn (Notificacion $notificacion) => $notificacion->aArreglo())
            ->all();
    }

    public function contar(): array
    {
        return Notificacion::contar();
    }

    public function buscar(string $uuid): ?array
    {
        return Notificacion::buscarPorUuid($uuid)?->aArreglo();
    }

    public function marcarLeida(string $uuid): bool
    {
        return Notificacion::buscarPorUuid($uuid)?->marcarLeida() ?? false;
    }

    public function marcarVarias(array $uuids): int
    {
        return Notificacion::marcarVarias($uuids);
    }

    public function descartar(string $uuid): bool
    {
        return Notificacion::buscarPorUuid($uuid)?->descartar() ?? false;
    }

    public function descartarVarias(array $uuids): int
    {
        return Notificacion::descartarVarias($uuids);
    }

    public function estado(): array
    {
        $conteo = $this->contar();

        return [
            'fuente' => 'mysql',
            'etiqueta' => 'MySQL',
            'motor' => 'SQL',
            'conectado' => true,
            'habilitado' => true,
            'total' => $conteo['total'],
            'no_leidas' => $conteo['no_leidas'],
            'error' => null,
        ];
    }
}
