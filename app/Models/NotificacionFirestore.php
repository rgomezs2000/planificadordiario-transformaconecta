<?php

namespace App\Models;

use App\Notificaciones\PresentadorDeNotificacion;
use Carbon\CarbonImmutable;

/**
 * Modelo de la notificación en Firestore (paradigma NoSQL).
 *
 * No es un modelo Eloquent: envuelve el documento que devuelve el cliente REST
 * y ofrece la misma forma de respuesta que Notificacion (MySQL), para que el
 * buzón y el JavaScript traten igual a las dos fuentes.
 *
 * Los documentos se guardan en la colección indicada en config/firebase.php y
 * usan el uuid de la fila SQL como id del documento.
 */
final class NotificacionFirestore
{
    /** @param array<string, mixed> $datos Campos ya decodificados del documento. */
    public function __construct(
        public readonly string $id,
        private readonly array $datos = [],
    ) {}

    /**
     * @param  array{id?: string, datos?: array<string, mixed>}  $documento
     */
    public static function desdeDocumento(array $documento): self
    {
        return new self(
            (string) ($documento['id'] ?? ''),
            is_array($documento['datos'] ?? null) ? $documento['datos'] : [],
        );
    }

    public function uuid(): string
    {
        return (string) ($this->datos['uuid'] ?? $this->id);
    }

    public function clave(): string
    {
        return (string) ($this->datos['clave'] ?? '');
    }

    public function tipo(): string
    {
        return (string) ($this->datos['tipo'] ?? 'sistema');
    }

    public function estaLeida(): bool
    {
        return ($this->datos['leida_en'] ?? null) !== null;
    }

    public function estaDescartada(): bool
    {
        return ($this->datos['descartada_en'] ?? null) !== null;
    }

    /** Los campos tal como se guardan, con las fechas en Carbon. */
    public function aDatos(): array
    {
        return $this->datos + [
            'uuid' => $this->uuid(),
            'clave' => $this->clave(),
            'tipo' => $this->tipo(),
            'titulo' => '',
            'mensaje' => '',
            'prioridad' => 'media',
            'fecha_referencia' => null,
            'fecha_hora' => null,
            'leida_en' => null,
            'descartada_en' => null,
            'daily_plan_id' => null,
            'plan_goal_id' => null,
            'schedule_entry_id' => null,
            'meta' => [],
            'creado_en' => null,
            'actualizado_en' => null,
        ];
    }

    public function aArreglo(): array
    {
        return PresentadorDeNotificacion::formatear($this->aDatos());
    }

    public function aResumen(): array
    {
        return PresentadorDeNotificacion::resumen($this->aDatos());
    }

    /**
     * Los campos que hay que actualizar al marcarla como leída. Incluye
     * "actualizado_en" para que el documento conserve su rastro de cambios.
     *
     * @return array<string, mixed>
     */
    public function camposParaLeer(): array
    {
        return [
            'leida_en' => CarbonImmutable::now(config('app.timezone')),
            'actualizado_en' => CarbonImmutable::now(config('app.timezone')),
        ];
    }

    /**
     * Los campos que hay que actualizar al archivarla.
     *
     * @return array<string, mixed>
     */
    public function camposParaDescartar(): array
    {
        $ahora = CarbonImmutable::now(config('app.timezone'));

        return [
            'descartada_en' => $ahora,
            'leida_en' => $this->estaLeida() ? ($this->datos['leida_en'] ?? $ahora) : $ahora,
            'actualizado_en' => $ahora,
        ];
    }
}
