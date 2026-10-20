<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Notificaciones de la pantalla
    |--------------------------------------------------------------------------
    |
    | Reglas del recordatorio diario. Todo se calcula sobre el diario del día
    | que está transcurriendo: los objetivos principales sin marcar y las
    | franjas del horario que ya pasaron su hora sin marcarse.
    |
    */

    // Interruptor general. En falso, ni el servidor genera notificaciones ni
    // el navegador consulta: la campanita queda oculta.
    'habilitadas' => (bool) env('NOTIFICACIONES_HABILITADAS', true),

    // Minutos que se esperan después de la hora de una franja antes de
    // recordarla. Evita que el aviso salga justo al iniciar la hora, cuando
    // todavía se está a tiempo de hacerla.
    'margen_minutos' => (int) env('NOTIFICACIONES_MARGEN_MINUTOS', 10),

    // Si el día de hoy todavía no tiene diario cargado, se puede recordar que
    // hay que crearlo. Es lo que hace posible que el sistema avise aunque el
    // diario no exista; se apaga con NOTIFICACIONES_AVISAR_DIARIO_AUSENTE=false.
    'avisar_diario_ausente' => (bool) env('NOTIFICACIONES_AVISAR_DIARIO_AUSENTE', true),

    // A partir de qué hora se recuerda que falta el diario del día. Antes de
    // esa hora se entiende que la persona todavía no empezó su jornada.
    'hora_diario_ausente' => env('NOTIFICACIONES_HORA_DIARIO_AUSENTE', '08:00'),

    // Cada cuántos segundos, como máximo, se vuelven a evaluar las reglas desde
    // el navegador. El comando programado las evalúa cada minuto igual.
    'segundos_entre_generaciones' => (int) env('NOTIFICACIONES_SEGUNDOS_ENTRE_GENERACIONES', 55),

    // Cada cuántos milisegundos la campanita consulta el contador.
    'intervalo_consulta_ms' => (int) env('NOTIFICACIONES_INTERVALO_MS', 15000),

    // Sonido y aviso del escritorio: valores iniciales de los interruptores
    // del buzón. La preferencia elegida se guarda en el navegador.
    'sonido_activo' => (bool) env('NOTIFICACIONES_SONIDO', true),
    'escritorio_activo' => (bool) env('NOTIFICACIONES_ESCRITORIO', false),

    // Límites del listado y de los lotes de acciones.
    'limite_listado' => (int) env('NOTIFICACIONES_LIMITE_LISTADO', 300),
    'maximo_lote' => (int) env('NOTIFICACIONES_MAXIMO_LOTE', 100),

];
