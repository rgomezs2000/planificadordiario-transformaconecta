<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Buzón de notificaciones en MySQL (el paradigma SQL del módulo).
 *
 * El uuid es la llave compartida con Firestore: el documento NoSQL se guarda
 * con ese mismo identificador, así una notificación es reconocible en los dos
 * motores. La columna "clave" es la huella de la regla que la originó y evita
 * duplicados: una misma franja o un mismo objetivo no se notifican dos veces
 * el mismo día.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificaciones', function (Blueprint $table) {
            $table->id();

            // Identidad compartida con Firestore.
            $table->uuid('uuid')->unique();
            $table->string('clave', 191)->unique();

            // Contenido del aviso.
            $table->string('tipo', 40);          // objetivo, franja, diario_ausente, sistema
            $table->string('titulo', 180);
            $table->text('mensaje');
            $table->string('prioridad', 20)->default('media');   // alta, media, baja

            // Cuándo corresponde el aviso y de qué día del diario habla.
            $table->date('fecha_referencia')->nullable()->index();
            $table->dateTime('fecha_hora')->index();

            // Estado del aviso en la bandeja.
            $table->timestamp('leida_en')->nullable()->index();
            $table->timestamp('descartada_en')->nullable()->index();

            // Vínculos con el diario que originó el recordatorio.
            $table->foreignId('daily_plan_id')
                ->nullable()
                ->constrained('daily_plans')
                ->nullOnDelete();
            $table->foreignId('plan_goal_id')
                ->nullable()
                ->constrained('plan_goals')
                ->nullOnDelete();
            $table->foreignId('schedule_entry_id')
                ->nullable()
                ->constrained('schedule_entries')
                ->nullOnDelete();

            // Datos de apoyo para el modal (ranura, actividad, hora, etc.).
            $table->json('meta')->nullable();

            // Estado del espejo NoSQL.
            $table->timestamp('sincronizada_en')->nullable();
            $table->text('error_firestore')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificaciones');
    }
};
