<?php

/*
|--------------------------------------------------------------------------
| Punto de entrada del sistema
|--------------------------------------------------------------------------
|
| Publica el planificador en la raíz del proyecto:
|
|     http://localhost:8088/planificadordiario-transformaconecta/
|
| en lugar de la carpeta public/:
|
|     http://localhost:8088/planificadordiario-transformaconecta/public/
|
| El arranque de Laravel sigue viviendo en public/index.php; aquí sólo se
| reenvía la petición para no duplicar el autoload, el bootstrap ni el
| manejo de la petición. Así los dos caminos siguen funcionando.
|
*/

require __DIR__.'/public/index.php';
