<?php

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
| withoutOverlapping() evita que dos corridas se pisen si una se demora.
|
*/

Schedule::command('planificacion:importar')
    ->dailyAt(ImportarPlanificacion::HORA)
    ->withoutOverlapping()
    ->description('Carga en el diario los días que falten del Excel de planificación periódica');
