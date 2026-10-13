<?php

namespace App\Planificacion;

use App\Console\Commands\ImportarPlanificacion;
use Symfony\Component\Process\PhpExecutableFinder;
use Throwable;

/**
 * Arranca la corrida a mano, en segundo plano.
 *
 * El botón "Ejecutar corrida" del módulo no puede esperar a que termine el
 * comando (el navegador se quedaría colgado y no habría forma de mostrar el
 * progreso), así que acá se lanza **el mismo comando que corre el cron**, sin
 * ninguna opción de más y despegado de la petición: la corrida sigue aunque se
 * cierre el navegador. La lógica es una sola, la del comando.
 *
 * Mientras corre, el comando va dejando cómo va en `excel/corrida.json` y su
 * paso a paso en `storage/logs/planificacion-corrida.log`; el módulo lee el
 * primero para llenar la barra de progreso.
 */
class LanzadorDeCorridas
{
    /**
     * El comando que se lanza: el mismo del cron, tal como queda en el log.
     *
     * El binario de PHP se busca con el mismo criterio que usa Laravel para
     * `php artisan`: el de la instalación que está corriendo la aplicación. El
     * nombre del comando sale del propio comando (`ImportarPlanificacion::COMANDO`),
     * que es también el que programa el cron.
     */
    public function comando(): string
    {
        return '"'.$this->binarioDePhp().'" "'.base_path('artisan').'" '.ImportarPlanificacion::COMANDO;
    }

    /**
     * Lanza la corrida y vuelve enseguida, sin esperarla.
     *
     * En Windows se usa `start /B`, que abre el proceso sin ventana y sin
     * dejarlo colgado de la petición; en Linux, el `&` de siempre. La salida va
     * a la nada porque el comando ya escribe su propio log.
     */
    public function lanzar(): void
    {
        $comando = $this->comando();

        $linea = windows_os()
            ? 'cmd /c start /B "" '.$comando.' > NUL 2>&1'
            : $comando.' > /dev/null 2>&1 &';

        try {
            $manejador = popen($linea, 'r');

            if (is_resource($manejador)) {
                pclose($manejador);
            }
        } catch (Throwable $e) {
            // Si el sistema no deja lanzar procesos, la corrida no arranca: se
            // anota en el log de la corrida y el módulo lo muestra como error.
            ArchivosDePlanificacion::anotarEnLaCorrida('No se pudo lanzar la corrida: '.$e->getMessage());

            throw $e;
        }
    }

    /** El intérprete de PHP que corre los comandos de artisan. */
    private function binarioDePhp(): string
    {
        $encontrado = (new PhpExecutableFinder)->find(false);

        if (is_string($encontrado) && $encontrado !== '') {
            return $encontrado;
        }

        $actual = PHP_BINARY;

        return str_contains(mb_strtolower(basename($actual)), 'php') ? $actual : 'php';
    }
}
