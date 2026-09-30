<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "MI HORARIO DE HOY": una fila por franja horaria del catálogo schedule_slots.
 * Al abrir un día se generan las 15 franjas (7:00-21:00) con activity = null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_plan_id')
                ->constrained('daily_plans')
                ->cascadeOnDelete();
            $table->foreignId('schedule_slot_id')
                ->constrained('schedule_slots')
                ->cascadeOnDelete();

            $table->string('activity')->nullable();   // ¿Qué voy a hacer?
            $table->boolean('is_done')->default(false);
            $table->timestamps();

            $table->unique(['daily_plan_id', 'schedule_slot_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_entries');
    }
};
