<?php

namespace Database\Seeders;

use App\Models\ActionBlockDuration;
use Illuminate\Database\Seeder;

/**
 * "BLOQUE DE ACCIÓN - Voy a trabajar durante..."
 */
class ActionBlockDurationSeeder extends Seeder
{
    public function run(): void
    {
        $durations = [5, 10, 15, 20];

        foreach ($durations as $index => $minutes) {
            ActionBlockDuration::updateOrCreate(
                ['minutes' => $minutes],
                [
                    'label' => $minutes.' minutos',
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );
        }
    }
}
