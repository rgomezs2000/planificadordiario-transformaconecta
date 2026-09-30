<?php

namespace Database\Seeders;

use App\Models\PreparationItem;
use Illuminate\Database\Seeder;

/**
 * "ANTES DE EMPEZAR - ¿Qué necesito tener listo?"
 */
class PreparationItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            'Materiales',
            'Ropa adecuada',
            'Alimentación',
            'Cargar dispositivos',
            'Espacio organizado',
            'Todo lo necesario para mis actividades',
        ];

        foreach ($items as $index => $name) {
            PreparationItem::updateOrCreate(
                ['name' => $name],
                [
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );
        }
    }
}
