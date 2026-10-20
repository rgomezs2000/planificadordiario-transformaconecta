<?php

namespace App\Notificaciones\Contratos;

/**
 * Lo que un buzón de notificaciones sabe hacer, sin importar el motor.
 *
 * BuzonMysql y BuzonFirestore implementan este contrato con la misma forma de
 * respuesta; BuzonDual lo usa para escribir en los dos y leer desde MySQL.
 */
interface Buzon
{
    /** "mysql" o "firestore". */
    public function nombre(): string;

    /**
     * @return array<int, array<string, mixed>> Notificaciones ya presentadas.
     */
    public function listar(array $filtros = []): array;

    /**
     * @return array{total: int, no_leidas: int, leidas: int}
     */
    public function contar(): array;

    /** @return array<string, mixed>|null */
    public function buscar(string $uuid): ?array;

    public function marcarLeida(string $uuid): bool;

    public function marcarVarias(array $uuids): int;

    public function descartar(string $uuid): bool;

    public function descartarVarias(array $uuids): int;

    /**
     * @return array<string, mixed> Diagnóstico de la fuente.
     */
    public function estado(): array;
}
