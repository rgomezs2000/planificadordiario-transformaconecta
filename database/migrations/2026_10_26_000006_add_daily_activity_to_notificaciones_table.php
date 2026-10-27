<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La actividad diaria que originó una notificación.
 *
 * Los recordatorios de objetivos y de franjas ya se resolvían por su clave
 * foránea; el de una actividad necesita la misma referencia para marcarse como
 * leído en cuanto la actividad se marca como hecha, sin esperar a que la regla
 * vuelva a evaluarse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notificaciones', function (Blueprint $table) {
            $table->foreignId('daily_activity_id')
                ->nullable()
                ->after('schedule_entry_id')
                ->constrained('daily_activities')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('notificaciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('daily_activity_id');
        });
    }
};
