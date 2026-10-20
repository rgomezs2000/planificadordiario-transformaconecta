<?php

namespace App\Notificaciones;

use App\Helpers\Helper;

/**
 * Da forma a una notificación para la pantalla, venga de MySQL o de Firestore.
 *
 * Las dos fuentes guardan los mismos campos con los mismos nombres; esta clase
 * los normaliza (fechas, etiquetas y estados) para que el JavaScript del buzón
 * no tenga que saber de dónde salió la fila.
 */
final class PresentadorDeNotificacion
{
    /**
     * @param  array<string, mixed>  $datos  Campos crudos de la notificación
     *                                       (created_at o creado_en para el alta).
     */
    public static function formatear(array $datos): array
    {
        $fechaHora = Helper::toCarbon($datos['fecha_hora'] ?? null);
        $leidaEn = $datos['leida_en'] ?? null;
        $descartadaEn = $datos['descartada_en'] ?? null;
        $creada = Helper::toCarbon(
            self::valor($datos, ['creado_en', 'created_at', 'fecha_hora'])
        );

        $tipo = (string) ($datos['tipo'] ?? Candidata::TIPO_SISTEMA);
        $prioridad = (string) ($datos['prioridad'] ?? 'media');
        $meta = $datos['meta'] ?? [];

        return [
            'uuid' => (string) ($datos['uuid'] ?? ''),
            'clave' => (string) ($datos['clave'] ?? ''),
            'tipo' => $tipo,
            'tipo_etiqueta' => Candidata::etiquetaTipo($tipo),
            'titulo' => (string) ($datos['titulo'] ?? ''),
            'mensaje' => (string) ($datos['mensaje'] ?? ''),
            'prioridad' => $prioridad,
            'prioridad_etiqueta' => Candidata::etiquetaPrioridad($prioridad),

            // Fechas y horas ya listas para pintar y para ordenar.
            'fecha_referencia' => Helper::date($datos['fecha_referencia'] ?? null, 'Y-m-d'),
            'fecha' => Helper::date($fechaHora, 'Y-m-d'),
            'fecha_corta' => Helper::shortDate($fechaHora),
            'fecha_label' => Helper::longDate($fechaHora),
            'hora' => Helper::time($fechaHora, 'H:i'),
            'fecha_hora_iso' => $fechaHora?->toIso8601String(),
            'hora_legible' => Helper::humanDiff($fechaHora),

            // Estado.
            'leida' => $leidaEn !== null,
            'leida_en' => Helper::dateTime($leidaEn),
            'descartada' => $descartadaEn !== null,
            'descartada_en' => Helper::dateTime($descartadaEn),
            'creada' => Helper::dateTime($creada),
            'creada_iso' => $creada?->toIso8601String(),

            // Vínculo con el diario que originó el aviso.
            'daily_plan_id' => self::entero($datos['daily_plan_id'] ?? null),
            'plan_goal_id' => self::entero($datos['plan_goal_id'] ?? null),
            'schedule_entry_id' => self::entero($datos['schedule_entry_id'] ?? null),
            'meta' => is_array($meta) ? $meta : [],
        ];
    }

    /** Lo que necesita el aviso emergente y el globo del contador. */
    public static function resumen(array $datos): array
    {
        $completo = self::formatear($datos);

        return [
            'uuid' => $completo['uuid'],
            'tipo' => $completo['tipo'],
            'tipo_etiqueta' => $completo['tipo_etiqueta'],
            'titulo' => $completo['titulo'],
            'mensaje' => $completo['mensaje'],
            'prioridad' => $completo['prioridad'],
            'prioridad_etiqueta' => $completo['prioridad_etiqueta'],
            'fecha_hora_iso' => $completo['fecha_hora_iso'],
            'fecha_corta' => $completo['fecha_corta'],
            'hora' => $completo['hora'],
            'leida' => $completo['leida'],
        ];
    }

    /** El primer valor no nulo de las claves indicadas. */
    private static function valor(array $datos, array $claves): mixed
    {
        foreach ($claves as $clave) {
            if (array_key_exists($clave, $datos) && $datos[$clave] !== null) {
                return $datos[$clave];
            }
        }

        return null;
    }

    private static function entero(mixed $valor): ?int
    {
        return $valor === null || $valor === '' ? null : (int) $valor;
    }
}
