<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Firebase / Firestore
    |--------------------------------------------------------------------------
    |
    | Las credenciales de servicio viven en un archivo JSON local en la raíz
    | del proyecto (firebase-credentials.json). No se instala el SDK de
    | Firestore: la aplicación habla con la API REST de Firestore usando la
    | librería google/auth que ya viene con el proyecto. Así no hace falta
    | ext-grpc ni cambiar las versiones de Composer.
    |
    */

    // Ruta del archivo de credenciales, relativa a la raíz del proyecto.
    'credenciales' => env('FIREBASE_CREDENTIALS', 'firebase-credentials.json'),

    // Proyecto de Firestore. Si se deja vacío se toma del JSON de credenciales.
    'proyecto' => env('FIREBASE_PROJECT_ID'),

    // Colección donde se replica cada notificación (el id del documento es el
    // mismo uuid que usa MySQL).
    'coleccion_notificaciones' => env('FIREBASE_COLECCION_NOTIFICACIONES', 'notificaciones'),

    // Interruptor general del espejo NoSQL. Si Firestore no está disponible,
    // el buzón sigue funcionando con MySQL y deja constancia del fallo.
    'habilitado' => (bool) env('FIREBASE_HABILITADO', true),

    // Segundos de espera de cada llamada a la API.
    'tiempo_espera' => (int) env('FIREBASE_TIEMPO_ESPERA', 15),

    // Caché del diagnóstico de conexión que muestra el buzón.
    'segundos_diagnostico' => (int) env('FIREBASE_SEGUNDOS_DIAGNOSTICO', 300),

];
