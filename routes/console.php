<?php

use App\Console\Commands\GenerarNotificaciones;
use App\Console\Commands\ImportarPlanificacion;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Planificación periódica
|--------------------------------------------------------------------------
|
| A las 00:00 se leen los libros que estén en la cola del módulo
| (storage/app/private/excel/jobs) y se crean en el diario los días que
| falten. Un día que ya existe no se vuelve a cargar nunca: la corrida sólo
| llena huecos.
|
| Ojo con cómo se dispara, según dónde viva el sistema:
|
|   · Windows (XAMPP): el Programador de tareas ejecuta UNA VEZ AL DÍA, a las
|     00:00, el comando directo (tarea "PlanificadorDiario-PlanificacionPeriodica"):
|
|         C:\xampp\php\php.exe artisan planificacion:importar
|
|     No pasa por este archivo: si se cambia la hora, hay que cambiarla en los
|     dos lados (la tarea de Windows y este horario).
|
|   · Linux o un hosting con cron: manda este horario, y basta con dejar el
|     planificador corriendo cada minuto:
|
|         * * * * * cd /ruta/del/proyecto && php artisan schedule:run >> /dev/null 2>&1
|
| El nombre del comando sale de ImportarPlanificacion::COMANDO, el mismo que usa
| el botón "Ejecutar corrida" del módulo: hay un solo comando y una sola lógica.
|
| withoutOverlapping() evita que dos corridas se pisen si una se demora.
|
*/

Schedule::command(ImportarPlanificacion::COMANDO)
    ->dailyAt(ImportarPlanificacion::HORA)
    ->withoutOverlapping()
    ->description('Carga en el diario los días que falten del Excel de planificación periódica');

/*
|--------------------------------------------------------------------------
| Notificaciones del día
|--------------------------------------------------------------------------
|
| Cada minuto se revisan los objetivos y las franjas del diario de hoy: lo que
| siga sin marcar se convierte en un recordatorio en MySQL y en Firestore.
|
| La campanita también evalúa las reglas cuando alguien tiene la pantalla
| abierta (como máximo una vez por minuto), así que el aviso llega incluso si
| este horario no está corriendo. En Windows, para que el módulo funcione sin
| nadie en pantalla, se puede crear una tarea que ejecute cada minuto:
|
|     C:\xampp\php\php.exe artisan notificaciones:generar
|
| withoutOverlapping evita que dos corridas se pisen si Firestore demora.
|
*/
Schedule::command(GenerarNotificaciones::COMANDO)
    ->everyMinute()
    ->withoutOverlapping()
    ->description('Genera los recordatorios del diario en MySQL y Firestore');
