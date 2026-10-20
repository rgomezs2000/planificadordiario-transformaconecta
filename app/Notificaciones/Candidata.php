<?php

namespace App\Notificaciones;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Una notificación que las reglas del día piden crear.
 *
 * Es el contrato entre el generador y los buzones: el generador sólo calcula
 * qué avisos corresponden y esta clase lleva los datos; el buzón decide cómo
 * guardarlos (MySQL, Firestore y el espejo entre ambos).
 *
 * La "clave" es la huella de la regla (por ejemplo
 * "franja:42:2026-10-20"). Es única en MySQL y en Firestore, y es lo que impide
 * repetir el mismo recordatorio cada vez que el navegador consulta.
 */
final class Candidata
{
    /** Objetivo principal del día sin marcar. */
    public const TIPO_OBJETIVO = 'objetivo';

    /** Franja del horario cuya hora ya pasó sin marcarse. */
    public const TIPO_FRANJA = 'franja';

    /** El día transcurre y todavía no tiene diario cargado. */
    public const TIPO_DIARIO_AUSENTE = 'diario_ausente';

    /** Aviso propio del sistema (no lo produce una regla del diario). */
    public const TIPO_SISTEMA = 'sistema';

    /** Etiquetas legibles de los tipos, para la pantalla. */
    public const TIPOS = [
        self::TIPO_OBJETIVO => 'Objetivo',
        self::TIPO_FRANJA => 'Franja horaria',
        self::TIPO_DIARIO_AUSENTE => 'Diario del día',
        self::TIPO_SISTEMA => 'Sistema',
    ];

    /** Etiquetas de la prioridad. */
    public const PRIORIDADES = [
        'alta' => 'Alta',
        'media' => 'Media',
        'baja' => 'Baja',
    ];

    /** Identidad compartida con el documento de Firestore. */
    public readonly string $uuid;

    public function __construct(
        public readonly string $clave,
        public readonly string $tipo,
        public readonly string $titulo,
        public readonly string $mensaje,
        public readonly CarbonImmutable $fechaHora,
        public readonly string $prioridad = 'media',
        public readonly ?CarbonImmutable $fechaReferencia = null,
        public readonly ?int $dailyPlanId = null,
        public readonly ?int $planGoalId = null,
        public readonly ?int $scheduleEntryId = null,
        public readonly array $meta = [],
        ?string $uuid = null,
    ) {
        $this->uuid = $uuid ?? (string) Str::uuid();
    }

    public static function etiquetaTipo(string $tipo): string
    {
        return self::TIPOS[$tipo] ?? ucfirst(str_replace('_', ' ', $tipo));
    }

    public static function etiquetaPrioridad(string $prioridad): string
    {
        return self::PRIORIDADES[$prioridad] ?? ucfirst($prioridad);
    }

    /**
     * Los valores que se guardan en la tabla notificaciones.
     *
     * Las fechas se entregan como objetos Carbon y Eloquent las castea; así el
     * mismo arreglo sirve para crear la fila y, después, para armar el espejo
     * de Firestore.
     */
    public function paraGuardar(): array
    {
        return [
            'uuid' => $this->uuid,
            'clave' => $this->clave,
            'tipo' => $this->tipo,
            'titulo' => $this->titulo,
            'mensaje' => $this->mensaje,
            'prioridad' => $this->prioridad,
            'fecha_referencia' => $this->fechaReferencia?->toDateString(),
            'fecha_hora' => $this->fechaHora,
            'leida_en' => null,
            'descartada_en' => null,
            'daily_plan_id' => $this->dailyPlanId,
            'plan_goal_id' => $this->planGoalId,
            'schedule_entry_id' => $this->scheduleEntryId,
            'meta' => $this->meta,
            'sincronizada_en' => null,
            'error_firestore' => null,
        ];
    }

    /** Resumen para el aviso emergente del navegador. */
    public function aResumen(): array
    {
        return [
            'uuid' => $this->uuid,
            'tipo' => $this->tipo,
            'tipo_etiqueta' => self::etiquetaTipo($this->tipo),
            'titulo' => $this->titulo,
            'mensaje' => $this->mensaje,
            'prioridad' => $this->prioridad,
            'fecha_hora_iso' => $this->fechaHora->toIso8601String(),
            'leida' => false,
        ];
    }
}
