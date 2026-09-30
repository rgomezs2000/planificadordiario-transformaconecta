<?php

namespace Database\Seeders;

use App\Models\ScheduleSlot;
use Illuminate\Database\Seeder;

/**
 * "MI HORARIO DE HOY": las franjas horarias de 7:00 a 21:00.
 * Al crear un día se generan sus 15 filas de schedule_entries a partir de aquí.
 */
class ScheduleSlotSeeder extends Seeder
{
    /** Primera hora del horario diario. */
    public const FIRST_HOUR = 7;

    /** Última hora del horario diario. */
    public const LAST_HOUR = 21;

    public function run(): void
    {
        $order = 1;

        for ($hour = self::FIRST_HOUR; $hour <= self::LAST_HOUR; $hour++) {
            ScheduleSlot::updateOrCreate(
                ['start_time' => sprintf('%02d:00:00', $hour)],
                [
                    'sort_order' => $order,
                    'is_active' => true,
                ]
            );

            $order++;
        }
    }
}
