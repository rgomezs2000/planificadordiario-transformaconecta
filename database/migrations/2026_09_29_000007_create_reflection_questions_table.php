<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo: "SI ESTOY PROCRASTINANDO ME PREGUNTO" (y futuras categorías de reflexión).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reflection_questions', function (Blueprint $table) {
            $table->id();
            $table->string('category')->default('procrastinacion')->index();
            $table->text('question');
            $table->enum('input_type', ['checkbox', 'textarea'])->default('checkbox');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reflection_questions');
    }
};
