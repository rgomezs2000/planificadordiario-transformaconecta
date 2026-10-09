@use('App\Helpers\Helper')
{{--
    ==========================================================================
    LA COLA DE LIBROS
    --------------------------------------------------------------------------
    Este bloque se puede volver a pedir solo por AJAX (GET /planificacion/cola)
    y el módulo lo cambia sin recargar la página: al montar un libro, al
    eliminarlo de la cola y en el refresco automático (que sólo mira: nunca
    dispara la corrida, que sigue siendo a las 00:00).

    La tabla la maneja DataTables (paginado, orden y búsqueda) y la segunda fila
    del encabezado trae un filtro por columna: nombre del libro, período, estado,
    cubierto sin huecos, última corrida y qué pasó.

    Necesita: $libros, $enCola y $carpetas.
    ==========================================================================
--}}
@php $estados = \App\Models\PlanificacionImportacion::ESTADOS; @endphp

<section class="tc-tarjeta tf-seccion mb-3">
    <h3 class="tf-titulo">
        <i class="bi bi-inboxes" aria-hidden="true"></i>
        Libros de la cola
        @if ($enCola > 0)
            <span class="tf-obligatorio-tag">{{ $enCola }} en espera</span>
        @endif
    </h3>

    @if ($libros === [])
        <p class="tf-ayuda">
            Todavía no hay ningún libro montado. Descarga la planilla, llena una hoja por día y
            móntala acá.
        </p>
    @else
        {{-- Barra de acciones: se eligen los libros con las casillas y se
             eliminan de una sola vez. El botón arranca apagado y el script lo
             enciende cuando hay algo seleccionado. --}}
        <div class="tf-cola__acciones">
            <button type="button" class="tc-boton tc-boton--rojo" id="cola-eliminar"
                    data-url="{{ route('planificacion.descartar.varios') }}" disabled>
                <i class="bi bi-trash3" aria-hidden="true"></i>
                Eliminar de la cola
            </button>

            <span class="tf-planificacion__motivo" id="cola-cuenta">Ningún libro seleccionado</span>

            <span class="tf-cola__latido" id="cola-latido" title="La cola se actualiza sola">
                <i class="bi bi-arrow-repeat" aria-hidden="true"></i>
                En vivo
            </span>
        </div>

        <table class="table tf-tabla mb-0" id="tabla-cola">
            <thead>
                <tr>
                    <th scope="col" class="tf-cola__col-check">
                        <input class="form-check-input" type="checkbox" id="cola-todos"
                               title="Seleccionar todos los libros en cola"
                               aria-label="Seleccionar todos los libros en cola">
                    </th>
                    <th scope="col">Libro</th>
                    <th scope="col">Período</th>
                    <th scope="col">Estado</th>
                    <th scope="col">Cubierto sin huecos</th>
                    <th scope="col">Última corrida</th>
                    <th scope="col">Qué pasó</th>
                    <th scope="col" class="text-center">Acción</th>
                </tr>

                {{-- Filtros de la tabla: uno por columna. La búsqueda general de
                     DataTables (arriba) busca por palabra clave en todo. --}}
                <tr class="tf-cola__filtros">
                    <th></th>
                    <th>
                        <input type="search" class="form-control form-control-sm" data-cola-filtro="1"
                               placeholder="Nombre del libro" aria-label="Buscar por nombre del libro">
                    </th>
                    <th>
                        <input type="search" class="form-control form-control-sm" data-cola-filtro="2"
                               placeholder="Período" aria-label="Buscar por período">
                    </th>
                    <th>
                        <select class="form-select form-select-sm" data-cola-filtro="3"
                                aria-label="Filtrar por estado">
                            <option value="">Todos los estados</option>
                            @foreach ($estados as $estado)
                                <option value="{{ $estado['etiqueta'] }}">{{ $estado['etiqueta'] }}</option>
                            @endforeach
                        </select>
                    </th>
                    <th>
                        <input type="search" class="form-control form-control-sm" data-cola-filtro="4"
                               placeholder="Cubierto hasta" aria-label="Buscar por cubierto sin huecos">
                    </th>
                    <th>
                        <input type="search" class="form-control form-control-sm" data-cola-filtro="5"
                               placeholder="Última corrida" aria-label="Buscar por última corrida">
                    </th>
                    <th>
                        <input type="search" class="form-control form-control-sm" data-cola-filtro="6"
                               placeholder="Qué pasó" aria-label="Buscar por lo que pasó">
                    </th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($libros as $libro)
                    @php $registro = $libro['registro']; @endphp

                    <tr>
                        <td class="tf-cola__col-check">
                            @if ($libro['en_cola'])
                                <input class="form-check-input tf-cola__check" type="checkbox"
                                       value="{{ $libro['archivo'] }}"
                                       aria-label="Seleccionar {{ $libro['archivo'] }}">
                            @endif
                        </td>

                        <td>
                            <strong>{{ $libro['archivo'] }}</strong>

                            @if ($libro['en_cola'])
                                <br><span class="tf-planificacion__motivo">en la cola · {{ $libro['peso'] }}</span>
                            @else
                                <br><span class="tf-planificacion__motivo">{{ $carpetas['procesados'] }}/</span>
                            @endif

                            @if ($registro?->nombre_original && $registro->nombre_original !== $libro['archivo'])
                                <br><span class="tf-planificacion__motivo">subido como {{ $registro->nombre_original }}</span>
                            @endif
                        </td>

                        <td>{{ $registro?->periodoEnPalabras() ?? 'Sin procesar todavía' }}</td>

                        <td>
                            <span class="tf-insignia tf-insignia--{{ $registro?->colorDeEstado() ?? 'naranja' }}">
                                {{ $registro?->etiquetaDeEstado() ?? 'En espera de la corrida' }}
                            </span>
                        </td>

                        <td>{{ $registro?->cubiertoEnPalabras() ?? '—' }}</td>

                        <td>
                            @if ($registro?->procesado_en)
                                {{ Helper::dateTime($registro->procesado_en) }}
                            @elseif ($registro?->descartado_en)
                                {{ Helper::dateTime($registro->descartado_en) }}
                            @else
                                —
                            @endif
                        </td>

                        <td>
                            {{ $registro?->resumenEnPalabras() ?? 'Montado y en espera.' }}

                            @if ($registro?->dias_sin_hoja > 0)
                                <br><span class="tf-planificacion__motivo">
                                    El libro no trae {{ Helper::pluralize($registro->dias_sin_hoja, 'día pasado', 'días pasados') }}
                                    del período.
                                </span>
                            @endif
                        </td>

                        <td class="text-center">
                            @if ($libro['en_cola'])
                                {{-- Botón de AJAX: la dirección va en data-url y el
                                     nombre del libro, en data-archivo. --}}
                                <button type="button" class="tc-boton tc-boton--contorno tf-cola__quitar"
                                        data-url="{{ route('planificacion.descartar', ['archivo' => $libro['archivo']]) }}"
                                        data-archivo="{{ $libro['archivo'] }}">
                                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                                    Quitar de la cola
                                </button>
                            @else
                                <span class="tf-tabla__sin-dato">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</section>
