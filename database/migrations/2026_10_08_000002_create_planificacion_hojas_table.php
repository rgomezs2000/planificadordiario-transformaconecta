<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El detalle de cada hoja del libro en la última corrida.
 *
 * Es lo que alimenta la tabla del módulo: qué día se creó, cuál se omitió
 * porque ya estaba, cuál se saltó y por qué, y qué avisos dejó.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planificacion_hojas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('importacion_id')
                ->constrained('planificacion_importaciones')
                ->cascadeOnDelete();

            // El nombre de la hoja dentro del libro y la fecha que representa.
            $table->string('hoja');
            $table->date('fecha')->nullable();

            // creado | omitido | saltado | ignorado | error
            $table->string('accion', 20);
            $table->string('motivo')->nullable();

            // Cuántos avisos dejó y el texto de todos ellos.
            $table->unsignedSmallInteger('avisos')->default(0);
            $table->text('detalle')->nullable();

            $table->timestamp('procesado_en')->nullable();

            $table->timestamps();

            $table->unique(['importacion_id', 'hoja']);
            $table->index(['importacion_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planificacion_hojas');
    }
};
