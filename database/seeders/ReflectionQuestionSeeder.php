<?php

namespace Database\Seeders;

use App\Models\ReflectionQuestion;
use Illuminate\Database\Seeder;

/**
 * "SI ESTOY PROCRASTINANDO ME PREGUNTO"
 */
class ReflectionQuestionSeeder extends Seeder
{
    public function run(): void
    {
        $questions = [
            '¿Qué estoy evitando?',
            '¿Por qué lo estoy postergando?',
            '¿Qué me está distrayendo?',
            '¿Necesito desglosarlo en pasos más pequeños?',
            '¿Qué puedo hacer AHORA en 5 minutos?',
        ];

        foreach ($questions as $index => $question) {
            ReflectionQuestion::updateOrCreate(
                [
                    'category' => ReflectionQuestion::CATEGORY_PROCRASTINATION,
                    'question' => $question,
                ],
                [
                    'input_type' => 'checkbox',
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );
        }
    }
}
