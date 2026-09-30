<?php

/** TEMPORAL: ¿qué es ese "checked" que aparece 11 veces en el formulario vacío? */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\DailyController;

$respuesta = (new DailyController())->index();

if (! $respuesta instanceof Illuminate\View\View) {
    echo "no devolvió una vista\n";
    exit(1);
}

try {
    $html = $respuesta->render();
} catch (Throwable $e) {
    echo 'no se pudo renderizar: ', $e->getMessage(), PHP_EOL;
    exit(1);
}

printf("ocurrencias de 'checked': %d\n\n", substr_count($html, 'checked'));

$posicion = 0;
$n = 0;

while (($posicion = strpos($html, 'checked', $posicion)) !== false) {
    $n++;
    printf("  %2d en %5d: ...%s...\n", $n, $posicion,
        preg_replace('/\s+/', ' ', substr($html, max(0, $posicion - 80), 110)));
    $posicion += 7;
}
