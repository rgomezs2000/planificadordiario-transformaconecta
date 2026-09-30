<?php

namespace Database\Seeders;

use App\Models\GoalType;
use Illuminate\Database\Seeder;

/**
 * "MIS 3 OBJETIVOS PRINCIPALES DE HOY": las tres ranuras con su intención.
 */
class GoalTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'slug' => 'debo_hacer',
                'name' => 'Debo hacer',
                'subtitle' => 'Responsabilidad',
            ],
            [
                'slug' => 'quiero_hacer',
                'name' => 'Quiero hacer',
                'subtitle' => 'Algo que me motiva',
            ],
            [
                'slug' => 'algo_para_mi',
                'name' => 'Algo para mí',
                'subtitle' => 'Autocuidado/Bienestar',
            ],
        ];

        foreach ($types as $index => $type) {
            GoalType::updateOrCreate(
                ['slug' => $type['slug']],
                [
                    'name' => $type['name'],
                    'subtitle' => $type['subtitle'],
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );
        }
    }
}
