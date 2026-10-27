<?php

namespace Database\Seeders;

use App\Models\DailyActivity;
use App\Models\DailyPlan;
use Illuminate\Database\Seeder;

/**
 * Actividades diarias de la hoja impresa.
 *
 * Las trece primeras aplican todos los días; las tres últimas solo el sábado
 * (weekday 6). Caminata, respiración y meditación piden minutos. Cada una trae
 * su hora y su pregunta para el recordatorio del día.
 */
class DailyActivitySeeder extends Seeder
{
    public function run(): void
    {
        $actividades = [
            // clave, nombre, icono, minutos, etiqueta minutos, día, hora, pregunta, categoría
            ['tender_cama', 'Tendí mi cama', 'bi-bed', false, null, null, '09:00',
                '¿Has tendido tu cama?', 'hogar'],
            ['caminata', 'Caminata', 'bi-person-walking', true, 'Minutos de caminata', null, '18:00',
                '¿Has realizado tu caminata de hoy?', 'cuerpo'],
            ['respiracion', 'Respiración', 'bi-wind', true, 'Minutos de respiración', null, '20:00',
                '¿Has hecho tu respiración de hoy?', 'mente'],
            ['meditacion', 'Meditación', 'bi-flower1', true, 'Minutos de meditación', null, '21:00',
                '¿Has realizado la meditación?', 'mente'],
            ['audios', 'Audios del día (tono indicado)', 'bi-mic', false, null, null, '10:00',
                '¿Has escuchado los audios del día?', 'mente'],
            ['temas_whatsapp', 'Temas del día en WhatsApp', 'bi-whatsapp', false, null, null, '11:00',
                '¿Has participado en los temas del día en WhatsApp?', 'vinculo'],
            ['alimentacion', 'Alimentación saludable', 'bi-apple', false, null, null, '13:30',
                '¿Has comido de forma saludable?', 'cuidado'],
            ['estudio', 'Estudio / trabajo', 'bi-laptop', false, null, null, '17:00',
                '¿Has ejercido tus estudios o labores?', 'productividad'],
            ['actividad_fisica', 'Actividad física', 'bi-heart-pulse', false, null, null, '18:30',
                '¿Has realizado actividad física?', 'cuerpo'],
            ['tareas_hogar', 'Tareas del hogar', 'bi-house', false, null, null, '19:00',
                '¿Has cumplido tus tareas del hogar?', 'hogar'],
            ['practica_habilidades', 'Práctica de habilidades', 'bi-lightbulb', false, null, null, '19:30',
                '¿Has practicado tus habilidades?', 'productividad'],
            ['tiempo_personal', 'Tiempo personal', 'bi-heart', false, null, null, '20:30',
                '¿Te has dado tu tiempo personal?', 'vinculo'],
            ['meta_semanal', 'Trabajé en mi meta semanal', 'bi-bullseye', false, null, null, '20:00',
                '¿Has avanzado en tu meta semanal?', 'meta'],

            // Solo sábado: el cambio de semana de la hoja impresa.
            ['cambio_sabanas', 'Cambié las sábanas', 'bi-layers', false, null, 6, '12:00',
                '¿Has cambiado las sábanas?', 'hogar'],
            ['cambio_toallas', 'Cambié las toallas', 'bi-droplet', false, null, 6, '12:00',
                '¿Has cambiado las toallas?', 'hogar'],
            ['espacio_limpio', 'Mantuve mi espacio limpio y ordenado', 'bi-stars', false, null, 6, '12:00',
                '¿Mantuviste tu espacio limpio y ordenado?', 'hogar'],
        ];

        foreach ($actividades as $indice => [$clave, $nombre, $icono, $minutos, $etiqueta, $dia, $hora, $pregunta, $categoria]) {
            DailyActivity::updateOrCreate(
                ['clave' => $clave],
                [
                    'name' => $nombre,
                    'icon' => $icono,
                    'requires_minutes' => $minutos,
                    'minutes_label' => $etiqueta,
                    'weekday' => $dia,
                    'reminder_time' => $hora,
                    'reminder_question' => $pregunta,
                    'category' => $categoria,
                    'sort_order' => $indice + 1,
                    'is_active' => true,
                ]
            );
        }

        // Los diarios que ya existían no tenían fila para una actividad nueva:
        // se les crea vacía para que el formulario, el PDF y las notificaciones
        // la vean sin tener que volver a guardar cada día.
        DailyPlan::query()
            ->with('activities')
            ->chunkById(200, function ($planes) {
                foreach ($planes as $plan) {
                    $aplicables = DailyActivity::active()
                        ->forWeekday((int) $plan->plan_date->dayOfWeek)
                        ->pluck('id');

                    $faltantes = $aplicables
                        ->diff($plan->activities->pluck('id'))
                        ->mapWithKeys(fn (int $id) => [$id => ['is_done' => false]]);

                    if ($faltantes->isNotEmpty()) {
                        $plan->activities()->syncWithoutDetaching($faltantes->all());
                    }
                }
            });
    }
}
