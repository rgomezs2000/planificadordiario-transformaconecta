@use('App\Helpers\Helper')
{{--
    ==========================================================================
    LA ÚLTIMA CORRIDA
    --------------------------------------------------------------------------
    Arriba, el botón para correr ahora sin esperar a las 00:00 (con la
    confirmación de bootbox y la barra de progreso). Abajo, hoja por hoja, qué
    hizo la última corrida (creado, ya estaba, saltado, ignorado o con error) y
    por qué: una DataTable con un filtro por columna y la búsqueda general, que
    filtra desde la primera letra.

    Este bloque se refresca solo por AJAX (dentro del estado del módulo), igual
    que la cola, y **en vivo**: mientras el comando está corriendo, el libro que
    se está procesando es el que sale acá y sus hojas van apareciendo una por
    una. El módulo lo vuelve a pedir cada pocos segundos (ver `index.blade.php`).

    Necesita: $ultima, $hojasDeLaUltima, $enCola y $enCurso.
    ==========================================================================
--}}
@php
    $acciones = \App\Models\PlanificacionHoja::ACCIONES;
    $enCola = $enCola ?? 0;
    $enCurso = $enCurso ?? false;

    // Lo que quedó pendiente: es lo que hace falta para que el botón tenga sentido.
    $pendientes = [];

    if ($ultima && $ultima->hojas_saltadas > 0) {
        $pendientes[] = Helper::pluralize($ultima->hojas_saltadas, 'hoja saltada', 'hojas saltadas');
    }

    if ($ultima && $ultima->hojas_error > 0) {
        $pendientes[] = Helper::pluralize($ultima->hojas_error, 'hoja con error', 'hojas con error');
    }

    if ($enCola > 0) {
        $pendientes[] = Helper::pluralize($enCola, 'libro en la cola', 'libros en la cola');
    }
@endphp

<section class="tc-tarjeta tf-seccion mb-3">
    <h3 class="tf-titulo">
        <i class="bi bi-list-check" aria-hidden="true"></i>
        Detalle de la última corrida

        {{-- El detalle se refresca solo por AJAX, igual que la cola: el cartel
             late cada vez que la tabla cambia (mientras la corrida corre, hoja
             por hoja). --}}
        <span class="tf-envivo" id="corrida-latido" title="El detalle se actualiza solo">
            <i class="bi bi-arrow-repeat" aria-hidden="true"></i>
            En vivo
        </span>
    </h3>

    {{-- Correr a mano, sin esperar a las 00:00. --}}
    <div class="tf-corrida__acciones">
        <button type="button" class="tc-boton tc-boton--naranja" id="corrida-ejecutar"
                data-url="{{ route('planificacion.corrida.ejecutar') }}"
                data-estado-url="{{ route('planificacion.corrida') }}"
                @disabled($enCola === 0)>
            <i class="bi bi-play-circle" aria-hidden="true"></i>
            Ejecutar corrida
        </button>

        <span class="tf-planificacion__motivo" id="corrida-aviso">
            @if ($enCola === 0)
                No hay ningún libro en la cola: no hay nada para procesar.
            @elseif ($pendientes !== [])
                Hay {{ Helper::listToText($pendientes) }}. Corré la corrida para reintentarlo ahora,
                sin esperar a las {{ \App\Console\Commands\ImportarPlanificacion::HORA }}.
            @else
                Hay {{ Helper::pluralize($enCola, 'libro', 'libros') }} en la cola. La corrida de las
                {{ \App\Console\Commands\ImportarPlanificacion::HORA }} los procesa sola; acá podés
                adelantarla.
            @endif
        </span>
    </div>

    @if (! $ultima)
        <p class="tf-ayuda">
            @if ($enCurso)
                La corrida arrancó: todavía no terminó la primera hoja. El detalle va a ir
                apareciendo acá, hoja por hoja, sin recargar la página.
            @else
                Todavía no corrió ninguna vez. El detalle aparece después de la corrida de las 00:00
                (o de una corrida a mano).
            @endif
        </p>
    @else
        <div class="tf-planificacion__cifras mb-2">
            <span><strong>Libro:</strong> {{ $ultima->archivo }}</span>
            <span><strong>Procesado:</strong> {{ Helper::dateTime($ultima->procesado_en) }}</span>
            <span><strong>Período:</strong> {{ $ultima->periodoEnPalabras() }}</span>
            <span><strong>Cubierto sin huecos hasta:</strong> {{ $ultima->cubiertoEnPalabras() }}</span>
            <span><strong>Creados:</strong> {{ $ultima->hojas_creadas }}</span>
            <span><strong>Ya estaban:</strong> {{ $ultima->hojas_omitidas }}</span>
            <span><strong>Saltados:</strong> {{ $ultima->hojas_saltadas }}</span>
            <span><strong>Ignorados:</strong> {{ $ultima->hojas_ignoradas }}</span>
        </div>

        @if ($ultima->mensaje)
            <p class="tf-ayuda">{{ $ultima->mensaje }}</p>
        @endif

        <table class="table tf-tabla mb-0" id="tabla-corrida">
            <thead>
                <tr>
                    <th scope="col">Hoja</th>
                    <th scope="col">Día</th>
                    <th scope="col">Acción</th>
                    <th scope="col">Motivo</th>
                </tr>

                {{-- Filtros de la tabla: hoja, día, acción y motivo. La búsqueda
                     general de DataTables (arriba) busca por palabra clave en
                     todas las columnas, desde la primera letra. --}}
                <tr class="tf-cola__filtros">
                    <th>
                        <input type="search" class="form-control form-control-sm" data-filtro="0"
                               placeholder="Hoja" aria-label="Buscar por hoja">
                    </th>
                    <th>
                        <input type="search" class="form-control form-control-sm" data-filtro="1"
                               placeholder="Día" aria-label="Buscar por día">
                    </th>
                    <th>
                        <select class="form-select form-select-sm" data-filtro="2"
                                aria-label="Filtrar por acción">
                            <option value="">Todas las acciones</option>
                            @foreach ($acciones as $accion)
                                <option value="{{ $accion['etiqueta'] }}">{{ $accion['etiqueta'] }}</option>
                            @endforeach
                        </select>
                    </th>
                    <th>
                        <input type="search" class="form-control form-control-sm" data-filtro="3"
                               placeholder="Motivo" aria-label="Buscar por motivo">
                    </th>
                </tr>
            </thead>
            <tbody>
                @foreach ($hojasDeLaUltima as $hoja)
                    <tr>
                        <td>{{ $hoja->hoja }}</td>
                        <td>{{ $hoja->diaEnPalabras() }}</td>
                        <td>
                            <span class="tf-insignia tf-insignia--{{ $hoja->colorDeAccion() }}">
                                {{ $hoja->etiquetaDeAccion() }}
                            </span>
                        </td>
                        <td>
                            {{ $hoja->motivo }}

                            @if ($hoja->listaDeAvisos() !== [])
                                <ul class="tf-avisos">
                                    @foreach ($hoja->listaDeAvisos() as $aviso)
                                        <li>{{ $aviso }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</section>
