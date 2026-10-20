<?php

namespace Database\Seeders;

use App\Notificaciones\Firestore\ClienteFirestore;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Las mismas tres notificaciones de muestra, pero sólo en Firestore.
 *
 *     php artisan db:seed --class=NotificacionFirestoreSeeder
 *
 * Es el seeder del paradigma NoSQL: escribe directamente en la colección (no
 * pasa por MySQL) para poder probar la bandeja de Firestore por separado. Se
 * puede correr aunque MySQL no tenga ninguna notificación.
 *
 * Si Firestore no está configurado, avisa y no hace nada.
 */
class NotificacionFirestoreSeeder extends Seeder
{
    public function run(): void
    {
        $cliente = app(ClienteFirestore::class);

        if (! $cliente->habilitado() || ! $cliente->configurado()) {
            $this->command?->warn(
                'Firestore no está habilitado o falta firebase-credentials.json. No se sembró nada.'
            );

            return;
        }

        $ahora = CarbonImmutable::now(config('app.timezone'));
        $creadas = 0;

        foreach (NotificacionSeeder::ejemplos($ahora) as $datos) {
            $datos['creado_en'] = $ahora;
            $datos['actualizado_en'] = $ahora;

            try {
                $cliente->crear($datos['uuid'], $datos);
                $creadas++;
            } catch (\Throwable $e) {
                report($e);
                $this->command?->warn('No se pudo guardar '.$datos['uuid'].': '.$e->getMessage());
            }
        }

        $this->command?->info(
            'Notificaciones de ejemplo en Firestore ('.$cliente->coleccion().'): '.$creadas.' de 3.'
        );
    }
}
