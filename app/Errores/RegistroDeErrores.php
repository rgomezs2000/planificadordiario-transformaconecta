<?php

namespace App\Errores;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Bitácora detallada de los errores.
 *
 * Todo lo que falla —lo captura el manejador de excepciones, un try/catch de un
 * controlador o una transacción del modelo— se escribe acá con el mayor detalle
 * posible: qué error fue, en qué dirección se pidió, desde dónde, con qué datos
 * y con la traza recortada. También devuelve un **código de incidente** corto
 * que se muestra en la página de error y encabeza la entrada del log, para poder
 * cruzar lo que vio la persona con lo que quedó registrado.
 *
 * El canal se llama "errores" y usa el driver "daily": Laravel crea un archivo
 * por día (errores-AAAA-MM-DD.log) y conserva los anteriores según los días
 * configurados, igual que el log general (laravel-AAAA-MM-DD.log).
 *
 * Dos cuidados a propósito:
 *
 *   · El registro **nunca** puede tumbar la respuesta ni llamarse a sí mismo: si
 *     el log no se puede escribir, el fallo se anota con error_log() y se sigue.
 *   · **No se guarda el cuerpo de la petición** (el contenido del formulario son
 *     los datos personales del diario). Sí se guarda la dirección completa, la
 *     ruta, la IP, el navegador y el contexto que pase quien detecta el error.
 */
class RegistroDeErrores
{
    /** Canal del detalle (ver config/logging.php). */
    public const CANAL = 'errores';

    /** Cuántas líneas de la traza se guardan. */
    public const LINEAS_DE_TRAZA = 12;

    /** Largo máximo de cada texto libre del contexto. */
    private const LARGO = 300;

    /** Un código corto y único para identificar el incidente. */
    public static function incidente(): string
    {
        return strtoupper(Str::random(8));
    }

    /**
     * Escribe el error en el log detallado.
     *
     * @param  array<string, mixed>  $contexto  Datos propios de la operación que falló.
     * @return string  El código de incidente, para mostrarlo en la página de error.
     */
    public static function registrar(
        Throwable $e,
        int $codigo,
        string $origen,
        array $contexto = [],
        ?string $incidente = null
    ): string {
        $incidente ??= self::incidente();

        try {
            $datos = self::datos($e, $codigo, $origen, $contexto, $incidente);

            Log::channel(self::CANAL)
                ->log($codigo >= 500 ? 'error' : 'warning', self::titulo($datos), $datos);
        } catch (Throwable $falloDelLog) {
            // El log no puede ser la causa de otro error: se anota y se sigue.
            // Se usa error_log() —y no el logger— para no entrar en un ciclo.
            error_log(
                '['.$incidente.'] No se pudo escribir en el log de errores: '
                .$falloDelLog->getMessage()
            );
        }

        return $incidente;
    }

    /** Atajo para los try/catch de los controladores. */
    public static function deControlador(
        Throwable $e,
        string $operacion,
        array $contexto = [],
        int $codigo = 500
    ): string {
        return self::registrar($e, $codigo, 'controlador', ['operacion' => $operacion] + $contexto);
    }

    /** Atajo para las transacciones y consultas del modelo. */
    public static function deModelo(
        Throwable $e,
        string $operacion,
        array $contexto = [],
        int $codigo = 500
    ): string {
        return self::registrar($e, $codigo, 'modelo', ['operacion' => $operacion] + $contexto);
    }

    /* ================================================================== */

    /** La primera línea del registro: qué pasó, en una frase. */
    private static function titulo(array $datos): string
    {
        return sprintf(
            '[%s] Error %s (%s) · %s · %s',
            $datos['incidente'],
            $datos['codigo'],
            $datos['titulo'],
            $datos['origen'],
            $datos['excepcion']
        );
    }

    /** Arma todo el contexto que se guarda. */
    private static function datos(
        Throwable $e,
        int $codigo,
        string $origen,
        array $contexto,
        string $incidente
    ): array {
        $error = CatalogoDeErrores::datos($codigo);
        $peticion = self::peticion();

        $datos = [
            'incidente' => $incidente,
            'origen' => $origen,
            'codigo' => $error['codigo'],
            'codigo_pedido' => $error['ajustado'] ? $error['codigo_solicitado'] : null,
            'familia' => $error['familia'],
            'familia_titulo' => $error['familia_titulo'],
            'titulo' => $error['titulo'],
            'excepcion' => $e::class,
            'mensaje' => self::recortar($e->getMessage(), 500),
            'archivo' => $e->getFile().':'.$e->getLine(),
            'metodo' => $peticion?->getMethod(),
            'url' => $peticion ? self::recortar($peticion->fullUrl(), 500) : null,
            'ruta' => $peticion?->route()?->getName(),
            'accion' => $peticion?->route()?->getActionName(),
            'ip' => $peticion?->ip(),
            'navegador' => $peticion ? self::recortar((string) $peticion->userAgent(), 200) : null,
            'referencia' => $peticion ? self::recortar((string) $peticion->headers->get('referer'), 300) : null,
            'espera_json' => $peticion?->expectsJson(),
            'traza' => self::traza($e),
        ];

        if ($contexto !== []) {
            $datos['contexto'] = self::limpiar($contexto);
        }

        return array_filter($datos, fn (mixed $valor) => $valor !== null && $valor !== '' && $valor !== []);
    }

    /** La petición actual, o null si esto corre por consola. */
    private static function peticion(): ?Request
    {
        if (app()->runningInConsole()) {
            return null;
        }

        return request();
    }

    /** Las primeras líneas de la traza: alcanza para ubicar el problema. */
    private static function traza(Throwable $e): array
    {
        $lineas = preg_split('/\r\n|\r|\n/', $e->getTraceAsString()) ?: [];

        return array_map(
            fn (string $linea) => self::recortar(trim($linea), self::LARGO),
            array_slice($lineas, 0, self::LINEAS_DE_TRAZA)
        );
    }

    /** Recorta los textos del contexto para que el log no crezca de más. */
    private static function limpiar(array $contexto): array
    {
        $limpio = [];

        foreach ($contexto as $clave => $valor) {
            if ($valor === null || $valor === '') {
                continue;
            }

            $limpio[$clave] = match (true) {
                is_string($valor) => self::recortar($valor, self::LARGO),
                is_scalar($valor) => $valor,
                is_array($valor) => self::limpiar($valor),
                default => get_debug_type($valor),
            };
        }

        return $limpio;
    }

    /** Corta un texto largo, dejando constancia del corte. */
    private static function recortar(?string $texto, int $largo = self::LARGO): ?string
    {
        $texto = trim((string) $texto);

        if ($texto === '') {
            return null;
        }

        return mb_strlen($texto) <= $largo
            ? $texto
            : mb_substr($texto, 0, $largo).'… (recortado)';
    }
}
