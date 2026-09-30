<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo: "BLOQUE DE ACCIÓN - Voy a trabajar durante..." (5, 10, 15, 20 minutos).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('action_block_durations', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('minutes')->unique();
            $table->string('label');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('action_block_durations');
    }
};
