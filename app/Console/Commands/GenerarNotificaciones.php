<?php

namespace App\Console\Commands;

use App\Helpers\Helper;
use App\Notificaciones\BuzonDual;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * Evalúa las reglas del día y deja las notificaciones en MySQL y Firestore.
 *
 *     php artisan notificaciones:generar
 *     php artisan notificaciones:generar --ahora="2026-10-20 10:30:00"
 *
 * Es el mismo trabajo que hace el sondeo de la campanita, pero sin depender de
 * que haya una pestaña abierta: el cron lo corre cada minuto. La opción --ahora
 * existe para probar las reglas en otro momento del día sin esperar.
 */
class GenerarNotificaciones extends Command
{
    /** El nombre que usa el horario de routes/console.php. */
    public const COMANDO = 'notificaciones:generar';

    protected $signature = self::COMANDO.'
                            {--ahora= : momento de la evaluación (Y-m-d H:i:s o dd/mm/aaaa H:i), para pruebas}
                            {--todo : vuelve a copiar todas las notificaciones vigentes al espejo de Firestore}';

    protected $description = 'Genera los recordatorios del diario y mantiene el espejo de Firestore';

    public function handle(BuzonDual $buzon): int
    {
        $ahora = null;
        $texto = $this->option('ahora');

        if (filled($texto)) {
            $fecha = Helper::toCarbon($texto);

            if (! $fecha) {
                $this->error('La fecha de --ahora no se entiende. Usa "2026-10-20 10:30:00".');

                return self::FAILURE;
            }

            $ahora = CarbonImmutable::instance($fecha)->setTimezone(config('app.timezone'));
        }

        $momento = $ahora ?? CarbonImmutable::now(config('app.timezone'));

        $this->line('Notificaciones · '.Helper::longDate($momento, withWeekday: true)
            .' '.$momento->format('H:i').'  ·  '.config('app.timezone'));

        try {
            $resultado = $buzon->sincronizar($ahora);
        } catch (Throwable $e) {
            report($e);

            $this->error('No se pudieron evaluar las reglas: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->line(sprintf(
            'Nuevas: %d  ·  Ya existían: %d  ·  Resueltas: %d  ·  Replicadas: %d',
            (int) ($resultado['creadas'] ?? 0),
            (int) ($resultado['existentes'] ?? 0),
            (int) ($resultado['resueltas'] ?? 0),
            (int) ($resultado['replicadas'] ?? 0),
        ));

        if ($this->option('todo')) {
            $replicadas = $buzon->replicarTodo();

            $this->info('Espejo reconstruido: '.$replicadas.' notificaciones copiadas a Firestore.');
        }

        $firestore = $resultado['firestore'] ?? [];

        if (($firestore['conectado'] ?? false) === true) {
            $this->info('Firestore: conectado · colección "'.($firestore['coleccion'] ?? 'notificaciones').'".');
        } else {
            $this->warn('Firestore: '.($firestore['error'] ?? 'sin conexión.'));
            $this->warn('Las notificaciones quedaron en MySQL y se replicarán en la próxima corrida.');
        }

        foreach ((array) ($resultado['errores'] ?? []) as $error) {
            $this->warn('No se pudo guardar '.($error['clave'] ?? 'una notificación').': '
                .($error['mensaje'] ?? 'error desconocido'));
        }

        return self::SUCCESS;
    }
}
