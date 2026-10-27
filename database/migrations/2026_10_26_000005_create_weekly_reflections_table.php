<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reflexión de la semana (solo domingo).
 *
 * Las seis preguntas del cierre semanal, una columna por pregunta. Se guarda
 * una fila por semana y el domingo la retoma: si el diario del domingo se
 * corrige, se corrige la misma reflexión.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_reflections', function (Blueprint $table) {
            $table->id();

            // Lunes de la semana que se está cerrando.
            $table->date('week_start')->unique();

            // Las seis preguntas de "Reflexión de la semana (domingo)".
            $table->text('how_was_week')->nullable();    // ¿Cómo me fue esta semana?
            $table->text('what_achieved')->nullable();   // ¿Qué logré?
            $table->text('what_was_hard')->nullable();   // ¿Qué me costó o fue difícil?
            $table->text('what_learned')->nullable();    // ¿Qué aprendí sobre mí?
            $table->text('how_improved')->nullable();    // ¿Cómo mejoré?
            $table->text('what_different')->nullable();  // ¿Qué haré diferente la próxima semana?

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_reflections');
    }
};
