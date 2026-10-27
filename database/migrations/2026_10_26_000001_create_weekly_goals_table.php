<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Meta semanal del planificador.
 *
 * Una fila por semana: la meta que la persona eligió para esa semana y por qué
 * es importante. Se guarda una sola vez y todos los diarios de la misma semana
 * la comparten, que es lo que permite que al crear el diario del martes la meta
 * del lunes ya venga cargada.
 *
 * La semana se identifica por su lunes (week_start), no por un número: así no
 * depende de cómo numere cada motor las semanas del año.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_goals', function (Blueprint $table) {
            $table->id();

            // Lunes de la semana a la que pertenece la meta.
            $table->date('week_start')->unique();

            // Los dos campos de la sección "Mi meta semanal".
            $table->text('goal');                       // Escribe una meta semanal...
            $table->text('why_important')->nullable();  // ¿Por qué es importante para mí?

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_goals');
    }
};
