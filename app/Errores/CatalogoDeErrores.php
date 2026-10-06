<?php

namespace App\Errores;

/**
 * Catálogo de los errores HTTP que muestra el sistema.
 *
 * Reúne, para cada código, la familia a la que pertenece (300, 400 o 500), su
 * título, su explicación en lenguaje llano y las salidas que se le ofrecen a la
 * persona. Lo usan la página /error/{codigo} y el manejador de excepciones de
 * bootstrap/app.php, de modo que el aviso que se ve al navegar sea el mismo que
 * aparece cuando algo falla de verdad.
 *
 * Los códigos que no están en el catálogo no quedan sin texto: heredan la
 * explicación de su familia (así 418 o 507 ya salen con un mensaje decente) y
 * un código fuera del rango 300-599 se muestra como 500, avisando del ajuste.
 */
class CatalogoDeErrores
{
    /** Primer y último código que se atienden tal cual. */
    public const MINIMO = 300;

    public const MAXIMO = 599;

    /** Las familias y su nombre. */
    public const FAMILIAS = [
        300 => 'Redirección',
        400 => 'Error del cliente',
        500 => 'Error del servidor',
    ];

    /** Qué significa cada familia, en una línea. */
    private const EXPLICACIONES = [
        300 => 'Una redirección no es una falla: el servidor te lleva a otra dirección para terminar la operación.',
        400 => 'Algo de la solicitud no es válido, no está permitido o ya no existe.',
        500 => 'El servidor recibió la solicitud, pero no pudo completarla.',
    ];

    /** Mensaje general de cada familia, para los códigos sin texto propio. */
    private const MENSAJES = [
        300 => 'El servidor te está enviando a otra dirección para completar lo que pediste.',
        400 => 'La solicitud no pudo completarse porque algo de lo que se pidió no es válido o no está disponible.',
        500 => 'El servidor no pudo completar la operación. No es culpa de lo que escribiste: es algo del sistema.',
    ];

    /** Salidas sugeridas de cada familia, para los códigos sin texto propio. */
    private const SUGERENCIAS = [
        300 => [
            'Sigue el enlace desde el que llegaste',
            'Si escribiste la dirección a mano, revísala',
            'Puedes volver al inicio del planificador',
        ],
        400 => [
            'Revisa la dirección: puede tener un error de escritura',
            'Vuelve al inicio y prueba de nuevo',
            'Si el problema sigue, avisa a quien administra el sistema',
        ],
        500 => [
            'Espera unos segundos y vuelve a intentar',
            'Vuelve al inicio del planificador',
            'Si el error se repite, avisa a quien administra el sistema',
        ],
    ];

    /** Icono institucional de cada familia (Bootstrap Icons). */
    private const ICONOS = [
        300 => 'bi-signpost-split',
        400 => 'bi-exclamation-octagon',
        500 => 'bi-cone-striped',
    ];

    /** Color de la tarjeta según la familia (paleta institucional). */
    private const COLORES = [
        300 => 'azul',
        400 => 'naranja',
        500 => 'rojo',
    ];

    /** Icono propio de algunos códigos. */
    private const ICONOS_CODIGO = [
        301 => 'bi-arrow-repeat',
        302 => 'bi-arrow-repeat',
        304 => 'bi-check2-circle',
        307 => 'bi-arrow-repeat',
        308 => 'bi-arrow-repeat',
        401 => 'bi-person-lock',
        403 => 'bi-shield-lock',
        404 => 'bi-compass',
        405 => 'bi-slash-circle',
        408 => 'bi-hourglass-split',
        410 => 'bi-trash3',
        419 => 'bi-hourglass-bottom',
        429 => 'bi-speedometer2',
        500 => 'bi-cone-striped',
        502 => 'bi-hdd-network',
        503 => 'bi-tools',
        504 => 'bi-clock-history',
    ];

    /**
     * Texto propio de los códigos más habituales.
     *
     * Cada entrada puede traer titulo, mensaje y sugerencias; lo que falte se
     * toma de su familia.
     */
    private const CODIGOS = [
        /* ---------- 3xx: redirecciones ---------- */
        300 => [
            'titulo' => 'Múltiples opciones',
            'mensaje' => 'Lo que pediste tiene más de una dirección posible y el servidor no elige por ti.',
            'sugerencias' => ['Vuelve al enlace desde el que llegaste y elige una opción', 'Entra al inicio del planificador y navega desde ahí'],
        ],
        301 => [
            'titulo' => 'Movido permanentemente',
            'mensaje' => 'Esta dirección cambió de lugar de forma definitiva. El navegador debería llevarte solo a la nueva.',
            'sugerencias' => ['Usa el enlace actualizado o el menú lateral', 'Actualiza tus marcadores si tenías esta dirección guardada'],
        ],
        302 => [
            'titulo' => 'Redirección temporal',
            'mensaje' => 'En este momento la página se atiende desde otra dirección, pero solo por un rato.',
            'sugerencias' => ['Continúa desde el enlace que te trajo aquí', 'Si no avanza solo, vuelve al inicio'],
        ],
        303 => [
            'titulo' => 'Ver otro recurso',
            'mensaje' => 'El servidor te envía a otra dirección para que veas el resultado de la operación.',
            'sugerencias' => ['Revisa el resultado en la dirección de destino', 'Vuelve al inicio si no ves lo que esperabas'],
        ],
        304 => [
            'titulo' => 'No modificado',
            'mensaje' => 'La página no cambió desde la última vez que la abriste: el navegador reutiliza lo que ya tenía.',
            'sugerencias' => ['Recarga la página para verla de nuevo', 'No hay nada que corregir: es el comportamiento normal de la caché'],
        ],
        307 => [
            'titulo' => 'Redirección temporal',
            'mensaje' => 'La operación debe repetirse en otra dirección, conservando lo que enviaste.',
            'sugerencias' => ['Repite la acción desde el enlace que te trajo aquí', 'Vuelve al listado y prueba otra vez'],
        ],
        308 => [
            'titulo' => 'Redirección permanente',
            'mensaje' => 'Esta dirección se movió de forma definitiva y la operación debe repetirse en la nueva.',
            'sugerencias' => ['Usa la dirección nueva', 'Actualiza tus marcadores'],
        ],

        /* ---------- 4xx: errores del cliente ---------- */
        400 => [
            'titulo' => 'Solicitud incorrecta',
            'mensaje' => 'El servidor no entendió lo que se le pidió: puede que falte un dato o que venga mal formado.',
            'sugerencias' => ['Revisa los campos del formulario y vuelve a enviarlo', 'Vuelve al inicio y empieza de nuevo'],
        ],
        401 => [
            'titulo' => 'No autorizado',
            'mensaje' => 'Para ver esto hace falta identificarse primero.',
            'sugerencias' => ['Inicia sesión de nuevo si tenías una sesión abierta', 'Vuelve al inicio del planificador'],
        ],
        402 => [
            'titulo' => 'Pago requerido',
            'mensaje' => 'El acceso a este contenido está condicionado a un pago.',
            'sugerencias' => ['Consulta con quien administra el sistema', 'Vuelve al inicio'],
        ],
        403 => [
            'titulo' => 'Acceso no permitido',
            'mensaje' => 'Llegaste hasta aquí, pero este contenido no está habilitado para tu acceso.',
            'sugerencias' => ['Vuelve al inicio o al listado de diarios', 'Si crees que es un error, avisa a quien administra el sistema'],
        ],
        404 => [
            'titulo' => 'Página no encontrada',
            'mensaje' => 'La dirección que buscas no existe, cambió de lugar o el diario que intentabas abrir ya no está.',
            'sugerencias' => ['Revisa la dirección: puede tener un error de escritura', 'Consulta el listado de diarios registrados', 'Vuelve al inicio del planificador'],
        ],
        405 => [
            'titulo' => 'Método no permitido',
            'mensaje' => 'La dirección existe, pero no admite la forma en que se intentó acceder.',
            'sugerencias' => ['Entra por el menú en lugar de escribir la dirección a mano', 'Vuelve al inicio y navega desde ahí'],
        ],
        406 => [
            'titulo' => 'Respuesta no aceptable',
            'mensaje' => 'El servidor no puede devolver el contenido en el formato que pidió el navegador.',
            'sugerencias' => ['Prueba con otro navegador', 'Vuelve al inicio'],
        ],
        408 => [
            'titulo' => 'Tiempo de espera agotado',
            'mensaje' => 'La petición tardó demasiado y el servidor dejó de esperarla.',
            'sugerencias' => ['Vuelve a intentarlo', 'Revisa tu conexión a internet'],
        ],
        409 => [
            'titulo' => 'Conflicto',
            'mensaje' => 'La operación choca con el estado actual del registro: por ejemplo, ese día ya tiene su diario.',
            'sugerencias' => ['Abre el diario de esa fecha y modifícalo en lugar de crear otro', 'Vuelve al listado para ver qué está registrado'],
        ],
        410 => [
            'titulo' => 'Este contenido ya no existe',
            'mensaje' => 'El recurso existió, pero fue eliminado y no volverá.',
            'sugerencias' => ['Consulta el listado de diarios registrados', 'Vuelve al inicio'],
        ],
        413 => [
            'titulo' => 'Contenido demasiado grande',
            'mensaje' => 'Lo que se envió supera el tamaño que el servidor admite.',
            'sugerencias' => ['Acorta los textos y vuelve a guardar', 'Reparte el contenido en varios días'],
        ],
        414 => [
            'titulo' => 'Dirección demasiado larga',
            'mensaje' => 'La dirección tiene más caracteres de los que el servidor puede atender.',
            'sugerencias' => ['Entra por el menú en lugar de pegar una dirección larga', 'Vuelve al inicio'],
        ],
        415 => [
            'titulo' => 'Tipo de contenido no admitido',
            'mensaje' => 'El formato de lo que se envió no es de los que el sistema acepta.',
            'sugerencias' => ['Vuelve al formulario y guarda desde ahí', 'Vuelve al inicio'],
        ],
        419 => [
            'titulo' => 'La sesión expiró',
            'mensaje' => 'Pasó demasiado tiempo desde que abriste la página, así que el sistema ya no reconoce el formulario.',
            'sugerencias' => ['Vuelve a abrir el formulario y guarda de nuevo', 'Recarga la página para renovar la sesión'],
        ],
        422 => [
            'titulo' => 'Datos no válidos',
            'mensaje' => 'La información enviada no cumple las reglas del formulario.',
            'sugerencias' => ['Revisa los campos marcados en rojo', 'Completa lo obligatorio y vuelve a guardar'],
        ],
        429 => [
            'titulo' => 'Demasiadas solicitudes',
            'mensaje' => 'Se hicieron muchas peticiones seguidas y el servidor pidió una pausa.',
            'sugerencias' => ['Espera unos segundos y vuelve a intentar', 'Evita recargar la página muchas veces seguidas'],
        ],
        451 => [
            'titulo' => 'No disponible por razones legales',
            'mensaje' => 'El contenido no puede mostrarse por una restricción legal.',
            'sugerencias' => ['Consulta con quien administra el sistema', 'Vuelve al inicio'],
        ],

        /* ---------- 5xx: errores del servidor ---------- */
        500 => [
            'titulo' => 'Error interno del servidor',
            'mensaje' => 'Algo falló del lado del sistema mientras se atendía la solicitud. Lo ocurrido quedó registrado.',
            'sugerencias' => ['Espera unos segundos y vuelve a intentar', 'Vuelve al inicio del planificador', 'Si se repite, avisa a quien administra el sistema'],
        ],
        501 => [
            'titulo' => 'Función no implementada',
            'mensaje' => 'El servidor no tiene forma de atender esta operación todavía.',
            'sugerencias' => ['Vuelve al inicio y usa las opciones disponibles', 'Avisa a quien administra el sistema'],
        ],
        502 => [
            'titulo' => 'Respuesta inválida del servidor',
            'mensaje' => 'Un servicio intermedio devolvió algo que el sistema no pudo interpretar.',
            'sugerencias' => ['Espera unos segundos y vuelve a intentar', 'Si se repite, avisa a quien administra el sistema'],
        ],
        503 => [
            'titulo' => 'Servicio no disponible',
            'mensaje' => 'El sistema está en mantenimiento o sobrecargado en este momento.',
            'sugerencias' => ['Vuelve a intentarlo en unos minutos', 'Avisa a quien administra el sistema si la espera se alarga'],
        ],
        504 => [
            'titulo' => 'El servidor tardó demasiado',
            'mensaje' => 'La operación se demoró más de lo permitido y se interrumpió.',
            'sugerencias' => ['Vuelve a intentarlo', 'Si el reporte es muy grande, genera un período más corto'],
        ],
        505 => [
            'titulo' => 'Versión del protocolo no admitida',
            'mensaje' => 'El navegador usó una versión del protocolo que el servidor no acepta.',
            'sugerencias' => ['Actualiza el navegador', 'Prueba con otro navegador'],
        ],
        507 => [
            'titulo' => 'Almacenamiento insuficiente',
            'mensaje' => 'El servidor se quedó sin espacio para completar la operación.',
            'sugerencias' => ['Avisa a quien administra el sistema', 'Vuelve al inicio mientras tanto'],
        ],
    ];

    /**
     * Los datos del código, listos para la vista.
     *
     * @return array{codigo: int, codigo_solicitado: int|null, ajustado: bool, familia: int,
     *               familia_titulo: string, familia_explicacion: string, titulo: string,
     *               mensaje: string, sugerencias: array<int, string>, icono: string, color: string,
     *               incidente: string|null}
     */
    public static function datos(int|string|null $codigo): array
    {
        $pedido = is_numeric($codigo) ? (int) $codigo : null;
        $ajustado = ! self::esValido($pedido);
        $codigo = $ajustado ? 500 : $pedido;

        $familia = self::familia($codigo);
        $propios = self::CODIGOS[$codigo] ?? [];

        return [
            'codigo' => $codigo,
            'codigo_solicitado' => $ajustado ? $pedido : null,
            'ajustado' => $ajustado,
            'familia' => $familia,
            'familia_titulo' => self::FAMILIAS[$familia],
            'familia_explicacion' => self::EXPLICACIONES[$familia],
            'titulo' => $propios['titulo'] ?? self::FAMILIAS[$familia],
            'mensaje' => $propios['mensaje'] ?? self::MENSAJES[$familia],
            'sugerencias' => $propios['sugerencias'] ?? self::SUGERENCIAS[$familia],
            'icono' => self::ICONOS_CODIGO[$codigo] ?? self::ICONOS[$familia],
            'color' => self::COLORES[$familia],
            // Lo completa el controlador cuando el error lo detectó el sistema:
            // es el código con el que quedó anotado en el log de errores.
            'incidente' => null,
        ];
    }

    /** La centena del código: 300, 400 o 500. */
    public static function familia(int $codigo): int
    {
        $familia = intdiv($codigo, 100) * 100;

        return isset(self::FAMILIAS[$familia]) ? $familia : 500;
    }

    /** ¿Es un código que el sistema atiende tal cual? */
    public static function esValido(mixed $codigo): bool
    {
        return is_numeric($codigo)
            && (int) $codigo >= self::MINIMO
            && (int) $codigo <= self::MAXIMO;
    }

    /** Nombre corto del código, para los títulos y los registros. */
    public static function etiqueta(int|string|null $codigo): string
    {
        $datos = self::datos($codigo);

        return $datos['codigo'].' · '.$datos['titulo'];
    }
}
