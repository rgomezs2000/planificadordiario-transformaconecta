<?php

namespace App\Console\Commands;

use App\Errores\RegistroDeErrores;
use App\Excel\PlantillaDelPlanificador;
use App\Helpers\Helper;
use App\Planificacion\ArchivosDePlanificacion;
use Illuminate\Console\Command;
use Throwable;

/**
 * Crea la planilla estática del planificador diario.
 *
 *     php artisan planificacion:plantilla
 *     php artisan planificacion:plantilla --ejemplo
 *
 * Deja el libro en blanco en storage/app/private/excel/formato, que es el que
 * descarga el módulo "Planificación periódica" con un link fijo. La plantilla
 * es estática: este comando se corre cuando hay que crearla o cuando cambió
 * algún catálogo (un ítem del checklist, una pregunta de reflexión, las
 * duraciones del bloque de acción), para que la hoja y la base digan lo mismo.
 *
 * Con --ejemplo genera además un libro ya lleno en excel/ejemplos, que es el
 * que se usa para probar la corrida con --dry-run sin escribir en la base.
 */
class GenerarPlantillaDePlanificacion extends Command
{
    protected $signature = 'planificacion:plantilla
                            {--ejemplo : genera además el libro de ejemplo que usa la prueba --dry-run}';

    protected $description = 'Crea la planilla en blanco del planificador diario (y, si se pide, un libro de ejemplo)';

    public function handle(): int
    {
        $this->line('catálogos: '.count(PlantillaDelPlanificador::nombresDeEnergia()).' energías · '
            .PlantillaDelPlanificador::tiposDeObjetivo()->count().' objetivos · '
            .PlantillaDelPlanificador::itemsDePreparacion()->count().' ítems de preparación · '
            .PlantillaDelPlanificador::preguntasDeProcrastinacion()->count().' preguntas · '
            .count(PlantillaDelPlanificador::duracionesDeBloque()).' duraciones · '
            .count(PlantillaDelPlanificador::resultadosDeBloque()).' resultados');

        try {
            $ruta = PlantillaDelPlanificador::conservar();
        } catch (Throwable $e) {
            report($e);
            RegistroDeErrores::registrar($e, 500, 'comando', [
                'operacion' => 'crear la planilla del planificador',
                'comando' => 'planificacion:plantilla',
            ]);

            $this->error('No se pudo crear la planilla: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Planilla creada: '.$ruta);
        $this->line('  archivo: '.ArchivosDePlanificacion::rutaAbsoluta($ruta));
        $this->line('  peso:    '.Helper::fileSize((int) filesize(ArchivosDePlanificacion::rutaAbsoluta($ruta))));
        $this->line('  hojas:   '.PlantillaDelPlanificador::HOJA_DIA.' (para duplicar) y '
            .PlantillaDelPlanificador::HOJA_INSTRUCCIONES.' (al final, la corrida la ignora)');

        if (! $this->option('ejemplo')) {
            return self::SUCCESS;
        }

        try {
            $ejemplo = PlantillaDelPlanificador::conservarEjemplo();
        } catch (Throwable $e) {
            report($e);
            RegistroDeErrores::registrar($e, 500, 'comando', [
                'operacion' => 'crear el libro de ejemplo',
                'comando' => 'planificacion:plantilla --ejemplo',
            ]);

            $this->error('No se pudo crear el libro de ejemplo: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Libro de ejemplo creado: '.$ejemplo);
        $this->line('  archivo: '.ArchivosDePlanificacion::rutaAbsoluta($ejemplo));
        $this->line('  trae días completos, un día sin cierre (para ver cómo se salta) y las hojas DIA e INSTRUCCIONES.');

        return self::SUCCESS;
    }
}
