<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "ANTES DE EMPEZAR": cada ítem marcado puede llevar su propia descripción,
 * por ejemplo marcar "Materiales" y anotar "PC, cuaderno, calculadora".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_plan_preparation', function (Blueprint $table) {
            $table->text('preparation_items_description')
                ->nullable()
                ->after('is_checked');
        });
    }

    public function down(): void
    {
        Schema::table('daily_plan_preparation', function (Blueprint $table) {
            $table->dropColumn('preparation_items_description');
        });
    }
};
