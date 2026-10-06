<?php

use App\Errores\RegistroDeErrores;
use App\Http\Controllers\ErrorController;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Los errores de validación de las peticiones AJAX usan el mismo sobre
        // que devuelve el controlador: { ok, message, errors }.
        // Las peticiones normales conservan el comportamiento de Laravel
        // (redirigir atrás con los errores en la sesión).
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], 422);
            }

            return null;
        });

        // Errores HTTP que se navegan (403 acceso no permitido, 404 página que
        // no existe, 405 método no permitido, 419 sesión expirada, 429
        // demasiadas solicitudes…): quedan anotados con detalle en el log de
        // errores y se muestran con la página del sistema, con su familia y su
        // código real. En las peticiones que esperan JSON no se toca la
        // respuesta, para no romper el AJAX del listado y de los reportes.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            $codigo = $e->getStatusCode();
            $incidente = RegistroDeErrores::registrar($e, $codigo, 'manejador');

            if ($request->expectsJson()) {
                return null;
            }

            return app(ErrorController::class)->index($codigo, $incidente);
        });

        // Cualquier otra excepción es un error 500. Siempre queda registrada con
        // detalle (el log general, además, la anota por su cuenta). Con
        // APP_DEBUG=true se deja la pantalla de depuración de Laravel (dice qué
        // pasó y dónde, que es lo que hace falta mientras se desarrolla); en
        // producción se muestra la página del sistema con su código de
        // incidente. La validación y los errores HTTP ya se atendieron arriba.
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($e instanceof ValidationException || $e instanceof HttpExceptionInterface) {
                return null;
            }

            $incidente = RegistroDeErrores::registrar($e, 500, 'manejador');

            if ($request->expectsJson() || app()->hasDebugModeEnabled()) {
                return null;
            }

            return app(ErrorController::class)->index(500, $incidente);
        });
    })->create();
