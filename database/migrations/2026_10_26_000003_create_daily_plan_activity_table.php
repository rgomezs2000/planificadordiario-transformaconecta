<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El marcado de las actividades de cada día.
 *
 * Una fila por actividad y por diario: si se hizo, cuántos minutos y una nota
 * libre. La pareja diario + actividad es única, así el formulario puede guardar
 * sin miedo a duplicar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_plan_activity', function (Blueprint $table) {
            $table->id();

            $table->foreignId('daily_plan_id')
                ->constrained('daily_plans')
                ->cascadeOnDelete();

            $table->foreignId('daily_activity_id')
                ->constrained('daily_activities')
                ->cascadeOnDelete();

            $table->boolean('is_done')->default(false);

            // Minutos dedicados, cuando la actividad los pide.
            $table->unsignedSmallInteger('minutes')->nullable();

            // Detalle libre ("30 minutos, parque").
            $table->string('note', 500)->nullable();

            $table->timestamps();

            $table->unique(['daily_plan_id', 'daily_activity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_plan_activity');
    }
};
