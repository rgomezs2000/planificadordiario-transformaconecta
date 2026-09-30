<?php

/** TEMPORAL: revisa por dentro los dos PDF generados. */

$archivos = ['pdf-muestra.pdf' => true, 'pdf-limpio.pdf' => false];

foreach ($archivos as $archivo => $debeTenerMarca) {
    $ruta = __DIR__.'/'.$archivo;

    if (! file_exists($ruta)) {
        echo "  {$archivo}: no existe\n";

        continue;
    }

    $datos = file_get_contents($ruta);
    $texto = '';

    // Flujos comprimidos (FlateDecode)
    if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $datos, $coincidencias)) {
        foreach ($coincidencias[1] as $flujo) {
            $inflado = @gzuncompress($flujo);

            if ($inflado !== false) {
                $texto .= $inflado;
            } else {
                $texto .= $flujo;   // por si el flujo va sin comprimir
            }
        }
    }

    printf("%s (%d bytes)\n", $archivo, strlen($datos));
    printf("  contiene SPECIMEN .......... %s\n", str_contains($texto, 'SPECIMEN') ? 'SÍ' : 'no');
    printf("  contiene DOCUMENTO DE MUESTRA %s\n", str_contains($texto, 'DOCUMENTO DE MUESTRA') ? 'SÍ' : 'no');
    printf("  flujos descomprimidos ...... %d\n", substr_count($texto, 'BT'));
    printf("  esperado con marca ......... %s\n", $debeTenerMarca ? 'sí' : 'no');
    echo "\n";
}
