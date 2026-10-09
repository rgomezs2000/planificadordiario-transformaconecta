<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "MI HORARIO DE HOY" ahora se arma fila por fila con una hora libre, así que la
 * franja deja de depender obligatoriamente del catálogo schedule_slots:
 *
 * - se añade start_time a la propia franja del día;
 * - schedule_slot_id pasa a ser opcional y queda sólo como referencia al
 *   catálogo cuando la franja se generó a partir de él.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_entries', function (Blueprint $table) {
            $table->time('start_time')->nullable()->after('schedule_slot_id');
        });

        Schema::table('schedule_entries', function (Blueprint $table) {
            $table->foreignId('schedule_slot_id')->nullable()->change();
        });

        // Las franjas que ya existían toman la hora del catálogo. Se escribe con
        // una subconsulta correlacionada —y no con un update ... join— para que
        // la migración corra igual en MySQL y en el sqlite de las pruebas.
        DB::statement(
            'update schedule_entries
             set start_time = (
                 select ss.start_time from schedule_slots ss where ss.id = schedule_entries.schedule_slot_id
             )
             where start_time is null
               and schedule_slot_id is not null'
        );
    }

    public function down(): void
    {
        Schema::table('schedule_entries', function (Blueprint $table) {
            $table->dropColumn('start_time');
        });

        Schema::table('schedule_entries', function (Blueprint $table) {
            $table->foreignId('schedule_slot_id')->nullable(false)->change();
        });
    }
};
