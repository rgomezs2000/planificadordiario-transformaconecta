<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Solo catálogos: la app es de un único usuario y no usa autenticación,
     * por eso no se crea ningún usuario de prueba.
     */
    public function run(): void
    {
        $this->call([
            EnergyLevelSeeder::class,
            GoalTypeSeeder::class,
            PreparationItemSeeder::class,
            ScheduleSlotSeeder::class,
            ActionBlockDurationSeeder::class,
            ActionBlockOutcomeSeeder::class,
            ReflectionQuestionSeeder::class,
        ]);
    }
}
