<?php

namespace Database\Seeders;

use App\Models\Notificacion;
use App\Notificaciones\BuzonDual;
use App\Notificaciones\Candidata;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Tres notificaciones de ejemplo en MySQL (y su espejo en Firestore).
 *
 * No se ejecuta con `php artisan db:seed`: los catálogos se siembran solos,
 * pero los avisos de muestra son datos de demostración. Se piden aparte:
 *
 *     php artisan db:seed --class=NotificacionSeeder
 *
 * Es idempotente: las claves empiezan con "demo:" y se actualizan, no se
 * duplican. Las mismas tres sirven para el seeder de Firestore, que las copia
 * sin pasar por MySQL.
 */
class NotificacionSeeder extends Seeder
{
    /** Las tres notificaciones de muestra, con uuid fijo para los dos motores. */
    public static function ejemplos(?CarbonImmutable $ahora = null): array
    {
        $ahora = $ahora?->setTimezone(config('app.timezone'))
            ?? CarbonImmutable::now(config('app.timezone'));

        $hoy = $ahora->startOfDay();
        $fecha = $hoy->toDateString();

        return [
            [
                'uuid' => '3f1a7c2e-5b4d-4c9a-8e21-7a6f5d4c3b21',
                'clave' => 'demo:objetivo:'.$fecha,
                'tipo' => Candidata::TIPO_OBJETIVO,
                'titulo' => 'Cumple tu objetivo: Debo hacer',
                'mensaje' => 'Tu objetivo «Entregar el informe mensual» todavía no está marcado. '
                    .'Márcalo en tu diario cuando lo hayas cumplido.',
                'prioridad' => 'alta',
                'fecha_referencia' => $hoy,
                'fecha_hora' => $ahora->subMinutes(35),
                'leida_en' => null,
                'descartada_en' => null,
                'daily_plan_id' => null,
                'plan_goal_id' => null,
                'schedule_entry_id' => null,
                'meta' => [
                    'ranura' => 1,
                    'objetivo_tipo' => 'Debo hacer',
                    'descripcion' => 'Entregar el informe mensual',
                    'fecha' => $fecha,
                ],
            ],
            [
                'uuid' => '7b2c9d41-6e3f-4a58-9c72-2d1e0f3a4b65',
                'clave' => 'demo:franja:'.$fecha,
                'tipo' => Candidata::TIPO_FRANJA,
                'titulo' => 'Franja pendiente de las 9:00',
                'mensaje' => 'A las 9:00 tenías planificado «Revisar el correo y responder lo urgente» '
                    .'y ya pasó la hora sin marcarla. Si la hiciste, márcala en tu diario.',
                'prioridad' => 'media',
                'fecha_referencia' => $hoy,
                'fecha_hora' => $ahora->subMinutes(20),
                'leida_en' => null,
                'descartada_en' => null,
                'daily_plan_id' => null,
                'plan_goal_id' => null,
                'schedule_entry_id' => null,
                'meta' => [
                    'hora' => '9:00',
                    'actividad' => 'Revisar el correo y responder lo urgente',
                    'minutos_de_retraso' => 20,
                    'margen_minutos' => 10,
                    'fecha' => $fecha,
                ],
            ],
            [
                'uuid' => 'c4d8e2f6-1a3b-4c5d-8e9f-0a1b2c3d4e5f',
                'clave' => 'demo:sistema:'.$fecha,
                'tipo' => Candidata::TIPO_SISTEMA,
                'titulo' => 'Tu buzón está listo',
                'mensaje' => 'Desde ahora, cuando un objetivo principal o una franja del horario '
                    .'sigan sin marcarse, recibirás un recordatorio en esta bandeja. Cada aviso se '
                    .'guarda en MySQL y se replica en Firestore.',
                'prioridad' => 'baja',
                'fecha_referencia' => $hoy,
                'fecha_hora' => $ahora->subMinutes(5),
                'leida_en' => null,
                'descartada_en' => null,
                'daily_plan_id' => null,
                'plan_goal_id' => null,
                'schedule_entry_id' => null,
                'meta' => [
                    'fecha' => $fecha,
                    'fuentes' => ['MySQL', 'Firestore'],
                ],
            ],
        ];
    }

    public function run(): void
    {
        $buzon = app(BuzonDual::class);
        $sincronizadas = 0;

        foreach (self::ejemplos() as $datos) {
            $notificacion = Notificacion::query()->updateOrCreate(
                ['clave' => $datos['clave']],
                $datos
            );

            if ($buzon->espejar($notificacion)) {
                $sincronizadas++;
            }
        }

        $this->command?->info('Notificaciones de ejemplo: '.count(self::ejemplos())
            .' en MySQL · '.$sincronizadas.' replicadas en Firestore.');

        if ($sincronizadas === 0) {
            $this->command?->warn(
                'Firestore no replicó: revisa firebase-credentials.json o ejecuta '
                .'php artisan db:seed --class=NotificacionFirestoreSeeder cuando esté disponible.'
            );
        }
    }
}
