<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "MIS 3 OBJETIVOS PRINCIPALES DE HOY": hasta 3 filas por día,
 * cada una tipificada por el catálogo goal_types.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_plan_id')
                ->constrained('daily_plans')
                ->cascadeOnDelete();
            $table->foreignId('goal_type_id')
                ->nullable()
                ->constrained('goal_types')
                ->nullOnDelete();

            $table->unsignedTinyInteger('slot');          // 1, 2 o 3
            $table->string('description');
            $table->boolean('is_done')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['daily_plan_id', 'slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_goals');
    }
};
