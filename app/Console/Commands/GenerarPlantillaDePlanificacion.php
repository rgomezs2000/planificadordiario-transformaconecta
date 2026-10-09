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
                            {--desde= : primer día del período (con --hasta genera un libro con una hoja por día)}
                            {--hasta= : último día del período}
                            {--ejemplo : genera además el libro de ejemplo que usa la prueba --dry-run}';

    protected $description = 'Crea la planilla del planificador diario: la genérica, la de un período o el libro de ejemplo';

    public function handle(): int
    {
        $this->line('catálogos: '.count(PlantillaDelPlanificador::nombresDeEnergia()).' energías · '
            .PlantillaDelPlanificador::tiposDeObjetivo()->count().' objetivos · '
            .PlantillaDelPlanificador::itemsDePreparacion()->count().' ítems de preparación · '
            .PlantillaDelPlanificador::preguntasDeProcrastinacion()->count().' preguntas · '
            .count(PlantillaDelPlanificador::duracionesDeBloque()).' duraciones · '
            .count(PlantillaDelPlanificador::resultadosDeBloque()).' resultados');

        // Con --desde y --hasta se genera el libro de un período: una hoja por
        // día, ya nombrada, con la fecha puesta y su lista desplegable.
        if ($this->option('desde') || $this->option('hasta')) {
            return $this->periodo();
        }

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

    /**
     * El libro de un período: una hoja por día, ya nombrada con la fecha, con la
     * fecha puesta y con la lista de días del período para elegirla.
     */
    private function periodo(): int
    {
        $desde = (string) $this->option('desde');
        $hasta = (string) $this->option('hasta');

        if ($desde === '' || $hasta === '') {
            $this->error('Hay que indicar las dos fechas: --desde=2026-10-01 --hasta=2026-10-31.');

            return self::FAILURE;
        }

        $dias = PlantillaDelPlanificador::diasDelPeriodo($desde, $hasta);

        if ($dias === []) {
            $this->error('Las fechas no se entienden, están al revés o el período pasa de '
                .PlantillaDelPlanificador::DIAS_MAXIMOS.' días. Usa el formato aaaa-mm-dd.');

            return self::FAILURE;
        }

        try {
            $ruta = PlantillaDelPlanificador::conservarPeriodo($desde, $hasta);
        } catch (Throwable $e) {
            report($e);
            RegistroDeErrores::registrar($e, 500, 'comando', [
                'operacion' => 'crear la planilla de un período',
                'comando' => 'planificacion:plantilla --desde --hasta',
                'desde' => $desde,
                'hasta' => $hasta,
            ]);

            $this->error('No se pudo crear la planilla del período: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($ruta === '') {
            $this->error('No se pudo crear la planilla del período.');

            return self::FAILURE;
        }

        $this->info('Planilla del período creada: '.$ruta);
        $this->line('  archivo: '.ArchivosDePlanificacion::rutaAbsoluta($ruta));
        $this->line('  peso:    '.Helper::fileSize((int) filesize(ArchivosDePlanificacion::rutaAbsoluta($ruta))));
        $this->line('  hojas:   '.count($dias).' días ('
            .Helper::shortDate($dias[0]).' a '.Helper::shortDate(end($dias))
            .'), la hoja oculta '.PlantillaDelPlanificador::HOJA_LISTAS
            .' y '.PlantillaDelPlanificador::HOJA_INSTRUCCIONES.' al final');
        $this->line('  cada hoja ya está nombrada con su fecha y trae la fecha puesta con su lista desplegable.');

        if ($this->option('ejemplo')) {
            $ejemplo = PlantillaDelPlanificador::conservarEjemplo();

            $this->info('Libro de ejemplo creado: '.$ejemplo);
        }

        return self::SUCCESS;
    }
}
