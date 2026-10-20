@extends('layouts.app')

@section('titulo', 'Notificaciones · Mi Planificador Diario')

@section('contenido')

    {{-- Cabecera del módulo: qué es el buzón y las acciones generales --}}
    <section class="tc-tarjeta tc-notificaciones__cabecera">
        <div class="tc-notificaciones__identidad">
            <span class="tc-notificaciones__icono" aria-hidden="true">
                <i class="bi bi-bell-fill"></i>
            </span>

            <div>
                <h1 class="tf-titulo tc-notificaciones__titulo">Bandeja de notificaciones</h1>
                <p class="tc-notificaciones__descripcion">
                    Recordatorios de tus objetivos principales y de las franjas del horario que ya
                    pasaron sin marcarse. Todo llega a una sola bandeja; el sistema guarda el
                    historial y lo mantiene listo para el push.
                </p>
                <p class="tc-notificaciones__resumen" data-buzon-resumen>
                    Consultando el buzón…
                </p>
            </div>
        </div>

        <div class="tc-notificaciones__acciones">
            <button type="button" class="tc-boton tc-boton--turquesa" data-buzon-sincronizar>
                <i class="bi bi-arrow-repeat" aria-hidden="true"></i>
                Revisar ahora
            </button>
        </div>
    </section>

    <p class="tc-notificaciones__mensaje" data-buzon-mensaje role="status" hidden></p>

    {{-- Filtros del listado. Se aplican solos al cambiar. --}}
    <section class="tf-filtros" data-buzon-filtros>
        <h4 class="tf-filtros__titulo">
            <i class="bi bi-funnel-fill" aria-hidden="true"></i>
            Filtrar notificaciones
        </h4>

        <div class="tf-filtros__cuerpo">
            <div class="tf-filtros__fila">
                <div class="tf-filtros__grupo">
                    <label class="tf-etiqueta" for="notificacion-estado">Estado</label>
                    <select class="form-select form-select-sm" id="notificacion-estado"
                            data-buzon-filtro="estado">
                        @foreach ($estados as $valor => $etiqueta)
                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="tf-filtros__grupo">
                    <label class="tf-etiqueta" for="notificacion-tipo">Tipo</label>
                    <select class="form-select form-select-sm" id="notificacion-tipo"
                            data-buzon-filtro="tipo">
                        <option value="">Todos los tipos</option>
                        @foreach ($tipos as $valor => $etiqueta)
                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="tf-filtros__grupo tf-filtros__grupo--ancho">
                    <label class="tf-etiqueta" for="notificacion-q">Buscar</label>
                    <input type="search" class="form-control form-control-sm" id="notificacion-q"
                           placeholder="Título o mensaje" autocomplete="off" maxlength="80"
                           data-buzon-filtro="q">
                    <p class="tf-filtros__ayuda">Filtra mientras escribes.</p>
                </div>
            </div>
        </div>

        <div class="tf-filtros__acciones">
            <button type="button" class="tc-boton tc-boton--contorno" data-buzon-limpiar-filtros>
                <i class="bi bi-eraser" aria-hidden="true"></i>
                Limpiar
            </button>
        </div>
    </section>

    {{-- La única tabla del buzón --}}
    <section class="tc-tarjeta tc-notificaciones__panel">
        <div class="tc-notificaciones__panel-cabecera">
            <div>
                <h3 class="tf-titulo mb-1">
                    <i class="bi bi-bell" aria-hidden="true"></i>
                    Notificaciones
                </h3>
                <p class="tc-notificaciones__nota mb-0">
                    Una sola bandeja: los avisos se guardan para el historial y se envían al push.
                </p>
            </div>
        </div>

        <div data-buzon-caja="buzon">
            <div class="tc-buzon__acciones tc-buzon__acciones--pagina">
                <span class="tc-buzon__seleccion" data-buzon-seleccion>0 seleccionadas</span>

                <button type="button" class="tc-buzon__accion" data-buzon-marcar
                        data-para="tabla-notificaciones-pagina" disabled>
                    <i class="bi bi-check2-all" aria-hidden="true"></i>
                    Marcar leídas
                </button>

                <button type="button" class="tc-buzon__accion" data-buzon-archivar
                        data-para="tabla-notificaciones-pagina" disabled>
                    <i class="bi bi-archive" aria-hidden="true"></i>
                    Archivar
                </button>
            </div>

            @include('notificaciones._tabla', [
                'id' => 'tabla-notificaciones-pagina',
                'url' => route('notificaciones.tabla'),
                'compacta' => false,
            ])
        </div>
    </section>

    <p class="tc-notificaciones__pie">
        <i class="bi bi-info-circle" aria-hidden="true"></i>
        Se avisa cuando un objetivo principal sigue sin marcarse o cuando una franja del horario
        ya pasó su hora. Lo que está marcado no se notifica y los avisos de tareas cumplidas se
        resuelven solos.
    </p>

@endsection
