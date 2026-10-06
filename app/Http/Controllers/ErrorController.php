<?php

namespace App\Http\Controllers;

use App\Errores\CatalogoDeErrores;

/**
 * Página de errores del sistema.
 *
 *   index()  /error/{codigo}   muestra el aviso institucional de ese código.
 *
 * El código llega como número (la ruta lo restringe con whereNumber) y se
 * agrupa por familia: 3xx (redirecciones), 4xx (errores del cliente, como el
 * acceso no permitido o la página que no existe) y 5xx (errores del servidor).
 * El texto de cada familia y de cada código vive en CatalogoDeErrores.
 *
 * Este mismo controlador lo usa el manejador de excepciones de
 * bootstrap/app.php para los errores reales de navegación, así que el aviso de
 * /error/404 y el que sale cuando una página no existe son el mismo.
 */
class ErrorController extends Controller
{
    /**
     * Muestra el aviso del código pedido.
     *
     * La respuesta sale con el mismo estado HTTP que se está explicando:
     * /error/404 contesta 404, /error/500 contesta 500. Así el navegador y
     * cualquier monitorización ven el código real y no un 200 disfrazado.
     * Un código fuera de 300-599 se muestra como 500, avisando del ajuste.
     *
     * Cuando el error lo detectó el sistema, el manejador de excepciones pasa
     * además el código de incidente con el que quedó anotado en el log de
     * errores, para que se pueda cruzar lo que vio la persona con lo registrado.
     */
    public function index(int $codigo, ?string $incidente = null)
    {
        $error = CatalogoDeErrores::datos($codigo);
        $error['incidente'] = $incidente;

        return response()
            ->view('errores.index', ['error' => $error], $error['codigo'])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }
}
