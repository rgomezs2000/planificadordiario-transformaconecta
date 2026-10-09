@extends('layouts.app')

@section('titulo', 'Menú principal · Mi Planificador Diario')

@section('contenido')

    <div class="row g-3 g-lg-4">

        {{-- ============================================================
             El día de hoy
             ============================================================ --}}
        <div class="col-12">
            <section class="tc-tarjeta tc-tarjeta--turquesa">
                <span class="tc-etiqueta tc-etiqueta--turquesa">Hoy</span>
                <h2 class="tc-tarjeta__titulo mt-2">{{ $todayLabel }}</h2>

                @if ($today)
                    @php $avance = $today->progressSummary(); @endphp

                    <p class="tc-tarjeta__texto">
                        @if ($today->energyLevel)
                            Tu diario de hoy ya está registrado, con energía
                            <strong>{{ $today->energyLevel->name }}</strong>.
                        @else
                            Tu diario de hoy ya está registrado.
                        @endif
                    </p>

                    <div class="tc-resumen">
                        <div class="tc-resumen__dato">
                            <span class="tc-resumen__valor">{{ $avance['goals_done'] }}/{{ $avance['goals_total'] }}</span>
                            <span class="tc-resumen__etiqueta">Objetivos</span>
                        </div>
                        <div class="tc-resumen__dato">
                            <span class="tc-resumen__valor">{{ $avance['schedule_percent'] }}</span>
                            <span class="tc-resumen__etiqueta">Horario</span>
                        </div>
                        <div class="tc-resumen__dato">
                            <span class="tc-resumen__valor">{{ $avance['focus_label'] }}</span>
                            <span class="tc-resumen__etiqueta">Foco</span>
                        </div>
                        <div class="tc-resumen__dato">
                            <span class="tc-resumen__valor">{{ $avance['is_closed'] ? 'Sí' : 'No' }}</span>
                            <span class="tc-resumen__etiqueta">Cierre</span>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <a class="tc-boton tc-boton--azul" href="{{ route('diario.show', $today) }}">
                            <i class="bi bi-eye" aria-hidden="true"></i> Abrir el diario de hoy
                        </a>
                        <a class="tc-boton tc-boton--contorno" href="{{ route('diario.edit', $today) }}">
                            <i class="bi bi-pencil-square" aria-hidden="true"></i> Modificar
                        </a>
                        <a class="tc-boton tc-boton--contorno" href="{{ route('diario.print', $today) }}"
                           target="_blank" rel="noopener">
                            <i class="bi bi-printer" aria-hidden="true"></i> Imprimir
                        </a>
                    </div>
                @else
                    <p class="tc-tarjeta__texto">
                        Todavía no has planificado el día de hoy. Empieza definiendo tus
                        3 objetivos principales: lo que <strong>debes</strong> hacer, lo que
                        <strong>quieres</strong> hacer y algo <strong>para ti</strong>.
                    </p>

                    <a class="tc-boton tc-boton--naranja" href="{{ route('diario.today') }}">
                        <i class="bi bi-calendar-plus" aria-hidden="true"></i> Crear el diario de hoy
                    </a>
                @endif
            </section>
        </div>

        {{-- ============================================================
             Accesos directos (los mismos del menú lateral)
             ============================================================ --}}
        <div class="col-12 col-md-6 col-xl-4">
            <section class="tc-tarjeta tc-tarjeta--completa tc-tarjeta--naranja">
                <span class="tc-tarjeta__icono tc-tarjeta__icono--naranja">
                    <i class="bi bi-calendar-plus" aria-hidden="true"></i>
                </span>
                <h2 class="tc-tarjeta__titulo">Crear diario</h2>
                <p class="tc-tarjeta__texto">
                    Define tus 3 objetivos, arma tu horario, marca el checklist de lo que
                    necesitas tener listo y cierra el día con tus logros.
                </p>
                <a class="tc-boton tc-boton--naranja" href="{{ route('diario.index') }}">
                    Empezar ahora
                </a>
            </section>
        </div>

        <div class="col-12 col-md-6 col-xl-4">
            <section class="tc-tarjeta tc-tarjeta--completa">
                <span class="tc-tarjeta__icono">
                    <i class="bi bi-journal-text" aria-hidden="true"></i>
                </span>
                <h2 class="tc-tarjeta__titulo">Consultar diario</h2>
                <p class="tc-tarjeta__texto">
                    Revisa los días que ya registraste, filtra por rango de fechas o nivel
                    de energía y consulta cómo va tu avance.
                </p>
                <a class="tc-boton tc-boton--azul" href="{{ route('diario.listado') }}">
                    Ver mis días
                </a>
            </section>
        </div>

        {{-- El mismo acceso que el menú lateral, para no tener que abrirlo. --}}
        <div class="col-12 col-md-6 col-xl-4">
            <section class="tc-tarjeta tc-tarjeta--completa tc-tarjeta--turquesa">
                <span class="tc-tarjeta__icono tc-tarjeta__icono--turquesa">
                    <i class="bi bi-file-earmark-excel" aria-hidden="true"></i>
                </span>
                <h2 class="tc-tarjeta__titulo">Planificación periódica</h2>
                <p class="tc-tarjeta__texto">
                    Descarga la planilla, llena una hoja por día y móntala en la cola: el
                    sistema crea esos días solo, en la corrida de las 00:00.
                </p>
                <a class="tc-boton tc-boton--turquesa" href="{{ route('planificacion.index') }}">
                    Montar el libro
                </a>
            </section>
        </div>

    </div>

@endsection
