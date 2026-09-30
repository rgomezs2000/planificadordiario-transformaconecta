<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Detalle: respuestas del día a las preguntas de reflexión
 * ("SI ESTOY PROCRASTINANDO ME PREGUNTO" y futuras categorías).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reflection_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_plan_id')
                ->constrained('daily_plans')
                ->cascadeOnDelete();
            $table->foreignId('reflection_question_id')
                ->constrained('reflection_questions')
                ->cascadeOnDelete();

            $table->boolean('is_checked')->default(false);
            $table->text('answer')->nullable();   // Para preguntas de tipo textarea
            $table->timestamps();

            $table->unique(['daily_plan_id', 'reflection_question_id'], 'reflection_answers_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reflection_answers');
    }
};
