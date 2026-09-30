<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla eje del planificador: un registro por día planificado.
 * Incluye la cabecera (fecha, energía) y el cierre del día (relación 1:1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_plans', function (Blueprint $table) {
            $table->id();

            // Cabecera: FECHA + MI ENERGÍA HOY
            // El día de la semana (L M M J V S D) se deriva de plan_date, no se guarda.
            $table->date('plan_date')->unique();
            $table->foreignId('energy_level_id')
                ->nullable()
                ->constrained('energy_levels')
                ->nullOnDelete();

            // CIERRE DEL DÍA
            $table->text('achievements')->nullable();   // Lo que logré hoy
            $table->text('pending')->nullable();        // Lo que quedó pendiente
            $table->text('pending_when')->nullable();   // ¿Cuándo lo haré?
            $table->text('proud_of')->nullable();       // Hoy estoy orgulloso/a de mí porque

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_plans');
    }
};
