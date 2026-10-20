<?php

namespace App\Http\Controllers\Concerns;

use App\Errores\RegistroDeErrores;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * El sobre JSON que ya usa el planificador: { ok, message, data } para las
 * respuestas correctas y { ok, message, errors } para los errores.
 *
 * Lo comparten el buzón MySQL y el buzón Firestore, así el JavaScript del
 * módulo de notificaciones recibe siempre la misma forma.
 */
trait RespuestasJson
{
    protected function jsonSuccess(
        mixed $data = null,
        string $message = 'Operación realizada correctamente.',
        int $status = 200
    ): JsonResponse {
        return response()->json([
            'ok' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    protected function jsonError(string $message, array $errors = [], int $status = 422): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }

    /**
     * Deja constancia del fallo en el log general y en el log de errores del
     * sistema antes de responderle al navegador.
     */
    protected function registrarFallo(Throwable $e, string $operacion, array $contexto = []): void
    {
        report($e);

        RegistroDeErrores::deControlador($e, $operacion, $contexto);
    }
}
