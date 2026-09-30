<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivote: "ANTES DE EMPEZAR". Guarda qué ítems del catálogo se marcaron cada día.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_plan_preparation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_plan_id')
                ->constrained('daily_plans')
                ->cascadeOnDelete();
            $table->foreignId('preparation_item_id')
                ->constrained('preparation_items')
                ->cascadeOnDelete();

            $table->boolean('is_checked')->default(false);
            $table->timestamps();

            $table->unique(['daily_plan_id', 'preparation_item_id'], 'daily_plan_preparation_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_plan_preparation');
    }
};
