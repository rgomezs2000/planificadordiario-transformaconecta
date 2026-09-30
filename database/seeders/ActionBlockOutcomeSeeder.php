<?php

namespace Database\Seeders;

use App\Models\ActionBlockOutcome;
use Illuminate\Database\Seeder;

/**
 * "BLOQUE DE ACCIÓN - Cuando termine este bloque..."
 */
class ActionBlockOutcomeSeeder extends Seeder
{
    public function run(): void
    {
        $outcomes = [
            ['slug' => 'termine', 'name' => 'Lo terminé'],
            ['slug' => 'avance', 'name' => 'Avancé'],
            ['slug' => 'necesito_otro_bloque', 'name' => 'Necesito otro bloque'],
            ['slug' => 'necesito_orientacion', 'name' => 'Necesito pedir orientación'],
        ];

        foreach ($outcomes as $index => $outcome) {
            ActionBlockOutcome::updateOrCreate(
                ['slug' => $outcome['slug']],
                [
                    'name' => $outcome['name'],
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );
        }
    }
}
