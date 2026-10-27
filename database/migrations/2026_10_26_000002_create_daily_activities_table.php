<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de actividades diarias (los hábitos de la columna izquierda de la
 * hoja impresa).
 *
 * Cada actividad dice si pide minutos, si solo aplica a un día de la semana
 * (el sábado tiene el cambio de sábanas y toallas) y a qué hora conviene
 * recordarla. El catálogo manda: el formulario y las notificaciones leen de
 * aquí, así una actividad nueva no obliga a tocar código.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_activities', function (Blueprint $table) {
            $table->id();

            // Identidad estable para el código y las notificaciones.
            $table->string('clave', 60)->unique();

            $table->string('name', 120);
            $table->string('icon', 60)->default('bi-check2');

            // Minutos: caminata, respiración y meditación los piden.
            $table->boolean('requires_minutes')->default(false);
            $table->string('minutes_label', 60)->nullable();

            // Día de la semana en que aplica (0 domingo … 6 sábado).
            // NULL = todos los días.
            $table->unsignedTinyInteger('weekday')->nullable()->index();

            // Hora sugerida para el recordatorio, en formato HH:MM.
            $table->string('reminder_time', 5)->nullable();

            // Pregunta del recordatorio ("¿Has realizado la meditación?").
            $table->string('reminder_question', 180)->nullable();

            // Familia de la actividad, para el análisis del resumen.
            $table->string('category', 40)->default('cuidado');

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_activities');
    }
};
