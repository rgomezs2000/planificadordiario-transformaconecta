<?php

namespace App\Notificaciones\Firestore;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Fallo al hablar con Firestore.
 *
 * Guarda el código HTTP cuando la API contestó algo (401, 404, 409, 500...) y
 * un mensaje corto y seguro para mostrar en el buzón. Nunca incluye el token
 * ni las credenciales.
 */
class ErrorFirestore extends RuntimeException
{
    public static function deRespuesta(string $metodo, string $url, Response $respuesta): self
    {
        $cuerpo = $respuesta->json();
        $detalle = is_array($cuerpo)
            ? ($cuerpo['error']['message'] ?? $cuerpo['error']['status'] ?? null)
            : null;

        $mensaje = 'Firestore respondió '.$respuesta->status().' al '.$metodo.'.';

        if (is_string($detalle) && trim($detalle) !== '') {
            $mensaje .= ' '.Str::limit(trim($detalle), 280);
        }

        $error = new self($mensaje, $respuesta->status());
        $error->url = $url;

        return $error;
    }

    /** La dirección que falló, sin credenciales. */
    public ?string $url = null;

    public function esNoEncontrado(): bool
    {
        return $this->getCode() === 404;
    }

    public function esConflicto(): bool
    {
        return $this->getCode() === 409;
    }
}
