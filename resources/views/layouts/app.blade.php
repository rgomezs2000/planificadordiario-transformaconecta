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
    <link rel="stylesheet" href="{{ asset('css/styles.css') }}?v={{ filemtime(public_path('css/styles.css')) }}">

    {{-- Buzón de notificaciones: campanita, panel y avisos --}}
    <link rel="stylesheet" href="{{ asset('css/notificaciones.css') }}">

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

            {{-- Logotipo institucional, al lado del botón hamburguesa --}}
            <a class="tc-marca" href="{{ route('home') }}" aria-label="Transforma-Conecta · Inicio">
                <img src="{{ asset('img/logo-transformaconecta.png') }}?v={{ filemtime(public_path('img/logo-transformaconecta.png')) }}"
                     class="tc-marca__logo" alt="Transforma-Conecta">
            </a>

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

            {{-- Campanita del buzón: va después de la fecha y la hora. La
                 llena public/js/notificaciones.js; el panel se abre de derecha
                 a izquierda con el offcanvas de Bootstrap. --}}
            @include('notificaciones._campana')

        </div>

        <p class="tc-cabecera__banda">
            <span class="tc-cabecera__banda-titulo">MI RUTINA ME FORTALECE</span>
            <span class="tc-cabecera__banda-lema">PEQUEÑAS ACCIONES, GRANDES CAMBIOS</span>
        </p>
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
            <li>
                <a class="tc-menu__enlace" href="{{ route('notificaciones.index') }}">
                    <span class="tc-menu__icono tc-menu__icono--naranja">
                        <i class="bi bi-bell" aria-hidden="true"></i>
                    </span>
                    <span class="tc-menu__texto">
                        Notificaciones
                        <span class="tc-menu__descripcion">Tus recordatorios del día</span>
                    </span>
                </a>
            </li>
        </ul>

        <p class="tc-menu__seccion">Planificación</p>

        <ul class="tc-menu__lista">
            <li>
                <a class="tc-menu__enlace" href="{{ route('planificacion.index') }}">
                    <span class="tc-menu__icono tc-menu__icono--turquesa">
                        <i class="bi bi-file-earmark-excel" aria-hidden="true"></i>
                    </span>
                    <span class="tc-menu__texto">
                        Planificación periódica
                        <span class="tc-menu__descripcion">Monta el libro del período</span>
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
    <div class="tc-contenedor tc-pie__cuerpo">
        <strong class="tc-pie__titulo">MI PLANIFICADOR DIARIO</strong>
        <span class="tc-pie__lema">LO IMPORTANTE NO ES EL PENSAMIENTO, LO IMPORTANTE ES LA ACCIÓN</span>
        <span class="tc-pie__futuro">¡TRANSFORMANDO NUESTRO FUTURO!</span>
        <span class="tc-pie__programa">
            Programa de Desarrollo Personal "Transforma-Conecta" · ©{{ now()->year }}
        </span>
    </div>
</footer>

{{-- ======================================================================
     BUZÓN DE NOTIFICACIONES
     El panel, el modal y la bandeja de avisos viven fuera de la cabecera; la
     campanita (incluida arriba) sólo aporta la configuración y el botón.
     ====================================================================== --}}
@if (config('notificaciones.habilitadas', true))
    @include('notificaciones._panel')
    @include('notificaciones._modal')
    <div class="tc-avisos" id="tc-avisos-notificacion" aria-live="polite" aria-atomic="false"></div>
@endif

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
<script src="{{ asset('js/notificaciones.js') }}?v={{ filemtime(public_path('js/notificaciones.js')) }}"></script>

@stack('scripts')
</body>
</html>
