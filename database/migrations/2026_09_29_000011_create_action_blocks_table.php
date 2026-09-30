<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "BLOQUE DE ACCIÓN": varias ejecuciones por día (uno a muchos),
 * con duración y resultado tomados de sus catálogos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('action_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_plan_id')
                ->constrained('daily_plans')
                ->cascadeOnDelete();
            $table->foreignId('action_block_duration_id')
                ->nullable()
                ->constrained('action_block_durations')
                ->nullOnDelete();
            $table->foreignId('action_block_outcome_id')
                ->nullable()
                ->constrained('action_block_outcomes')
                ->nullOnDelete();

            $table->string('task')->nullable();          // En qué se trabajó durante el bloque
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('action_blocks');
    }
};
