{{--
    ==========================================================================
    PLANTILLA BASE · Mi Planificador Diario
    Programa de Desarrollo Personal "Transforma-Conecta"
    --------------------------------------------------------------------------
    Reúne lo que comparten todas las pantallas: las librerías por CDN
    (Bootstrap, Bootstrap Icons, jQuery, DataTables y bootbox), la cabecera con
    la fecha y la hora dinámicas, el botón hamburguesa con su menú lateral y el
    pie.

    Las vistas hijas sólo rellenan @section('contenido') y, si lo necesitan,
    añaden algo con @push('estilos') o @push('scripts').

    NOTA: las librerías se cargan por CDN a propósito; este proyecto no usa
    NPM, ni Tailwind, ni Vue, ni React, ni Angular.
    ==========================================================================
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#002060">
    <meta name="description"
          content="Sistema de planificación personal del Programa de Desarrollo Personal Transforma-Conecta.">

    <title>@yield('titulo', 'Mi Planificador Diario')</title>

    {{-- Bootstrap 5 (rejilla y componentes) --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">

    {{-- Iconos --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">

    {{-- DataTables (tablas) con su integración para Bootstrap 5 --}}
    <link rel="stylesheet" href="https://cdn.datatables.net/2.3.8/css/dataTables.bootstrap5.min.css">

    {{-- Estilos propios: la paleta institucional vive aquí --}}
    <link rel="stylesheet" href="{{ asset('css/styles.css') }}">

    @stack('estilos')
</head>
<body>

{{-- ======================================================================
     CABECERA
     ====================================================================== --}}
<header class="tc-cabecera">
    <div class="tc-contenedor">
        <div class="tc-cabecera__fila">

            {{-- Botón hamburguesa: abre el menú lateral --}}
            <button class="tc-hamburguesa" type="button"
                    data-bs-toggle="offcanvas" data-bs-target="#menuLateral"
                    aria-controls="menuLateral" aria-label="Abrir el menú">
                <i class="bi bi-list" aria-hidden="true"></i>
            </button>

            {{-- Marca. Para usar el logotipo real, basta con sustituir este
                 bloque por: <img src="{{ asset('img/logo.png') }}" class="tc-marca__logo" alt="Transforma-Conecta"> --}}
            <div class="tc-marca">
                <span class="tc-marca__transforma">TRANSFORMA</span>
                <span class="tc-marca__conecta">Conecta</span>
            </div>

            {{-- Títulos: H3, H1 y H2, en el mismo orden de la hoja impresa --}}
            <div class="tc-cabecera__titulos">
                <h3 class="tc-cabecera__programa">PROGRAMA DE DESARROLLO PERSONAL "TRANSFORMA-CONECTA"</h3>
                <h1 class="tc-cabecera__titulo">MI PLANIFICADOR DIARIO</h1>
                <h2 class="tc-cabecera__subtitulo">SISTEMA DE PLANIFICACIÓN PERSONAL</h2>
            </div>

            {{-- Fecha y hora dinámicas (las pinta public/js/reloj.js) --}}
            <div class="tc-reloj">
                <span class="tc-reloj__fecha" id="reloj-fecha">Cargando la fecha…</span>
                <span class="tc-reloj__hora" id="reloj-hora">--:--:--</span>
            </div>

        </div>

        <p class="tc-cabecera__banda">ORGÁNIZATE · ACTÚA · AVANZA</p>
    </div>
</header>

{{-- ======================================================================
     MENÚ LATERAL
     ====================================================================== --}}
<div class="offcanvas offcanvas-start tc-menu" tabindex="-1" id="menuLateral"
     aria-labelledby="menuLateralTitulo">
    <div class="tc-menu__cabecera">
        <h2 class="tc-menu__titulo" id="menuLateralTitulo">Menú</h2>
        <button type="button" class="tc-menu__cerrar" data-bs-dismiss="offcanvas"
                aria-label="Cerrar el menú">
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
    </div>

    <div class="tc-menu__cuerpo">
        <p class="tc-menu__seccion">Mi diario</p>

        <ul class="tc-menu__lista">
            <li>
                <a class="tc-menu__enlace" href="{{ route('home') }}">
                    <span class="tc-menu__icono tc-menu__icono--turquesa">
                        <i class="bi bi-house-door" aria-hidden="true"></i>
                    </span>
                    <span class="tc-menu__texto">
                        Inicio
                        <span class="tc-menu__descripcion">Menú principal</span>
                    </span>
                </a>
            </li>
            <li>
                <a class="tc-menu__enlace" href="{{ route('diario.index') }}">
                    <span class="tc-menu__icono tc-menu__icono--naranja">
                        <i class="bi bi-calendar-plus" aria-hidden="true"></i>
                    </span>
                    <span class="tc-menu__texto">
                        Crear diario
                        <span class="tc-menu__descripcion">Planifica un día nuevo</span>
                    </span>
                </a>
            </li>
            <li>
                <a class="tc-menu__enlace" href="{{ route('diario.listado') }}">
                    <span class="tc-menu__icono">
                        <i class="bi bi-journal-text" aria-hidden="true"></i>
                    </span>
                    <span class="tc-menu__texto">
                        Consultar diario
                        <span class="tc-menu__descripcion">Revisa y filtra tus días</span>
                    </span>
                </a>
            </li>
        </ul>
    </div>

    <div class="tc-menu__pie">
        Programa de Desarrollo Personal<br>
        <strong>Transforma-Conecta</strong>
    </div>
</div>

{{-- ======================================================================
     CONTENIDO
     ====================================================================== --}}
<main class="tc-principal">
    <div class="tc-contenedor">
        @yield('contenido')
    </div>
</main>

{{-- ======================================================================
     PIE
     ====================================================================== --}}
<footer class="tc-pie">
    <div class="tc-contenedor">
        <strong>MI PLANIFICADOR DIARIO</strong> · ORGÁNIZATE · ACTÚA · AVANZA
        <br>
        Programa de Desarrollo Personal "Transforma-Conecta" · {{ now()->year }}
    </div>
</footer>

{{-- ======================================================================
     LIBRERÍAS Y SCRIPTS
     El orden importa: primero las librerías y al final el arranque propio.
     ====================================================================== --}}
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootbox@6.0.4/dist/bootbox.min.js"></script>
<script src="https://cdn.datatables.net/2.3.8/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.3.8/js/dataTables.bootstrap5.min.js"></script>

<script src="{{ asset('js/reloj.js') }}"></script>
<script src="{{ asset('js/menu.js') }}"></script>
<script src="{{ asset('js/app.js') }}"></script>

@stack('scripts')
</body>
</html>
