<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Conversor de PDF a imagen
    |--------------------------------------------------------------------------
    |
    | Los JPG de los diarios se sacan del PDF con el binario `mutool` de MuPDF:
    | un ejecutable suelto, sin instalador ni extensión de PHP, que funciona
    | igual en Windows y en Linux. No se usa Imagick ni Ghostscript a propósito.
    |
    | Si se deja vacío, se busca primero en storage/app/private/binarios/mupdf y
    | después en el PATH del sistema, así sirve tanto el binario guardado dentro
    | del proyecto como uno instalado con winget, apt o brew.
    |
    |     MUPDF_BIN=C:\ruta\a\mutool.exe
    |
    */

    'bin' => env('MUPDF_BIN'),

    /*
    |--------------------------------------------------------------------------
    | Resolución de la conversión
    |--------------------------------------------------------------------------
    |
    | Puntos por pulgada con los que se rasteriza el PDF. 150 deja una imagen
    | legible en pantalla sin que el ZIP de un diario largo pese de más.
    |
    */

    'resolucion' => (int) env('MUPDF_RESOLUCION', 150),

    /*
    |--------------------------------------------------------------------------
    | Calidad del JPG
    |--------------------------------------------------------------------------
    |
    | Calidad con la que GD comprime el JPG (0-100). Más calidad se ve mejor y
    | pesa más; no cambia el tamaño de la imagen. 92 es un buen equilibrio para
    | documentos con texto.
    |
    */

    'calidad' => (int) env('MUPDF_CALIDAD', 92),

    /*
    |--------------------------------------------------------------------------
    | Enfoque suave
    |--------------------------------------------------------------------------
    |
    | El texto rasterizado a 150 ppp queda un poco difuso; una máscara de
    | nitidez muy leve lo afina sin cambiar el tamaño y sin estropear la marca de
    | agua (que es un gris muy tenue, casi sin bordes que realzar). Se puede
    | apagar con MUPDF_ENFOQUE=false.
    |
    */

    'enfoque' => filter_var(env('MUPDF_ENFOQUE', true), FILTER_VALIDATE_BOOLEAN),

];
