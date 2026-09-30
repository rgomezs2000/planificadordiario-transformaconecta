<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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
    })->create();
