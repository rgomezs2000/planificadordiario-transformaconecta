<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nuevas secciones del diario en la tabla eje.
 *
 *   · weekly_goal_id        la meta de la semana a la que pertenece el día.
 *   · personal_done_today   "¿Qué hice hoy para acercarme a mi meta?"
 *   · personal_helped       "¿Qué me ayudó?"
 *   · personal_hard         "¿Qué me dificultó?"
 *
 * El seguimiento personal se guarda en el día y no en la meta porque cambia
 * cada jornada: la meta es la misma toda la semana, lo que se hace con ella no.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_plans', function (Blueprint $table) {
            $table->foreignId('weekly_goal_id')
                ->nullable()
                ->after('energy_level_id')
                ->constrained('weekly_goals')
                ->nullOnDelete();

            $table->text('personal_done_today')->nullable()->after('proud_of');
            $table->text('personal_helped')->nullable()->after('personal_done_today');
            $table->text('personal_hard')->nullable()->after('personal_helped');
        });
    }

    public function down(): void
    {
        Schema::table('daily_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('weekly_goal_id');
            $table->dropColumn(['personal_done_today', 'personal_helped', 'personal_hard']);
        });
    }
};
