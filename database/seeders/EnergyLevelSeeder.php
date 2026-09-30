<?php

namespace Database\Seeders;

use App\Models\EnergyLevel;
use Illuminate\Database\Seeder;

/**
 * "MI ENERGÍA HOY": Baja, Media, Alta.
 */
class EnergyLevelSeeder extends Seeder
{
    public function run(): void
    {
        $levels = [
            ['slug' => 'baja', 'name' => 'Baja', 'emoji' => '😞'],
            ['slug' => 'media', 'name' => 'Media', 'emoji' => '😐'],
            ['slug' => 'alta', 'name' => 'Alta', 'emoji' => '😃'],
        ];

        foreach ($levels as $index => $level) {
            EnergyLevel::updateOrCreate(
                ['slug' => $level['slug']],
                [
                    'name' => $level['name'],
                    'emoji' => $level['emoji'],
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );
        }
    }
}
