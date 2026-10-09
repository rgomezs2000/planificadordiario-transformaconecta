<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Control de los libros de "Planificación periódica".
 *
 * Un registro por libro montado en el módulo. Guarda el estado de la última
 * corrida, los contadores de lo que pasó con sus hojas y hasta dónde quedó
 * cubierto el período. La base de datos de los diarios (daily_plans) manda: esta
 * tabla sólo cuenta lo que hizo el cron.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planificacion_importaciones', function (Blueprint $table) {
            $table->id();

            // El nombre final del archivo en la cola: es su identidad.
            $table->string('archivo')->unique();
            $table->string('nombre_original')->nullable();

            // Qué período cubre el libro, según sus hojas.
            $table->date('periodo_desde')->nullable();
            $table->date('periodo_hasta')->nullable();

            // en_espera | procesado | parcial | error | descartado
            $table->string('estado', 20)->default('en_espera');

            $table->unsignedSmallInteger('hojas_total')->default(0);
            $table->unsignedSmallInteger('hojas_creadas')->default(0);
            $table->unsignedSmallInteger('hojas_omitidas')->default(0);
            $table->unsignedSmallInteger('hojas_saltadas')->default(0);
            $table->unsignedSmallInteger('hojas_ignoradas')->default(0);
            $table->unsignedSmallInteger('hojas_error')->default(0);

            // El checkpoint: hasta dónde está cubierto sin huecos y hasta dónde
            // hay días cargados por adelantado.
            $table->date('cubierto_hasta')->nullable();
            $table->date('en_bd_hasta')->nullable();

            // Días pasados que el libro no trae (aviso, no error).
            $table->unsignedSmallInteger('dias_sin_hoja')->default(0);

            $table->timestamp('subido_en')->nullable();
            $table->timestamp('procesado_en')->nullable();
            $table->timestamp('descartado_en')->nullable();

            $table->text('mensaje')->nullable();

            $table->timestamps();

            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planificacion_importaciones');
    }
};
