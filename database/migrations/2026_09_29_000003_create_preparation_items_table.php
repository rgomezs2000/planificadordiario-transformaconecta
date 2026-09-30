<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo: "ANTES DE EMPEZAR" (qué necesito tener listo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preparation_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preparation_items');
    }
};
