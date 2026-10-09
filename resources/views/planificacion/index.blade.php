@use('App\Helpers\Helper')

@extends('layouts.app')

@section('titulo', 'Planificación periódica · Mi Planificador Diario')

@section('contenido')

    {{-- Mensajes del servidor --}}
    @if (session('exito'))
        <div class="alert alert-success" role="alert">
            <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
            {{ session('exito') }}
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger" role="alert">
            <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
            {{ session('error') }}
        </div>
    @endif

    {{-- ==================================================================
         1. LA PLANILLA ESTÁTICA
         ================================================================== --}}
    <section class="tc-tarjeta tc-tarjeta--turquesa mb-3">
        <span class="tc-etiqueta tc-etiqueta--turquesa">Paso 1</span>
        <h2 class="tc-tarjeta__titulo mt-2">Descarga la planilla</h2>

        <p class="tc-tarjeta__texto">
            Es el libro del formulario en blanco: trae la hoja <strong>DIA</strong> para duplicar
            una vez por cada día del período y, al final, la hoja de <strong>INSTRUCCIONES</strong>
            (que el sistema ignora siempre, la dejes o la borres). Cada hoja del libro es un día:
            se nombra con la fecha, en formato <strong>aaaa-mm-dd</strong>.
        </p>

        <div class="d-flex flex-wrap gap-2 align-items-center">
            <a class="tc-boton tc-boton--turquesa" href="{{ route('planificacion.plantilla') }}">
                <i class="bi bi-file-earmark-excel" aria-hidden="true"></i>
                Descargar la planilla (.xlsx)
            </a>

            @if ($planilla['existe'])
                <span class="tf-planificacion__motivo">
                    {{ $planilla['nombre'] }} · {{ $planilla['peso'] }} · actualizada el {{ $planilla['modificado'] }}
                </span>
            @else
                <span class="tf-insignia tf-insignia--rojo">La planilla no está creada</span>
            @endif
        </div>
    </section>

    {{-- ==================================================================
         2. MONTAR EL LIBRO
         ================================================================== --}}
    <section class="tc-tarjeta tf-seccion mb-3">
        <span class="tc-etiqueta">Paso 2</span>
        <h3 class="tf-titulo mt-2">
            <i class="bi bi-cloud-arrow-up" aria-hidden="true"></i>
            Monta el libro del período
        </h3>

        <form method="POST" action="{{ route('planificacion.store') }}" enctype="multipart/form-data">
            @csrf

            <div class="tf-montar">
                <div class="tf-montar__campo">
                    <label class="tf-etiqueta tf-obligatorio" for="archivo">Libro de Excel (.xlsx)</label>
                    <input class="form-control tf-montar__archivo" type="file" name="archivo" id="archivo"
                           accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>

                    @error('archivo')
                        <p class="tf-campo--error">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="tc-boton tc-boton--naranja">
                    <i class="bi bi-box-arrow-in-down" aria-hidden="true"></i>
                    Montar en la cola
                </button>
            </div>

            <p class="tf-ayuda">
                El módulo <strong>sólo guarda</strong>: el libro queda en la cola y se procesa en la
                corrida de las {{ $horaDeLaCorrida }}. Montarlo no toca la base de datos de los diarios.
                Peso máximo: {{ $pesoMaximo }}.
            </p>
        </form>
    </section>

    {{-- ==================================================================
         3. LA COLA Y EL ESTADO DE CADA LIBRO
         ================================================================== --}}
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
            <div class="table-responsive">
                <table class="table tf-tabla mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Libro</th>
                            <th scope="col">Período</th>
                            <th scope="col">Estado</th>
                            <th scope="col">Cubierto sin huecos</th>
                            <th scope="col">Última corrida</th>
                            <th scope="col">Qué pasó</th>
                            <th scope="col" class="text-center">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($libros as $libro)
                            @php $registro = $libro['registro']; @endphp

                            <tr>
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
                                        <form method="POST"
                                              action="{{ route('planificacion.descartar', ['archivo' => $libro['archivo']]) }}"
                                              onsubmit="return confirm('¿Sacar «{{ $libro['archivo'] }}» de la cola? El libro no se procesa más y queda guardado en descartados. La base de datos no se toca.');">
                                            @csrf
                                            @method('DELETE')

                                            <button type="submit" class="tc-boton tc-boton--contorno">
                                                <i class="bi bi-x-lg" aria-hidden="true"></i>
                                                Quitar de la cola
                                            </button>
                                        </form>
                                    @else
                                        <span class="tf-tabla__sin-dato">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- ==================================================================
         4. EL DETALLE DE LA ÚLTIMA CORRIDA
         ================================================================== --}}
    @if ($ultima)
        <section class="tc-tarjeta tf-seccion mb-3">
            <h3 class="tf-titulo">
                <i class="bi bi-list-check" aria-hidden="true"></i>
                Detalle de la última corrida
            </h3>

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

            <div class="table-responsive">
                <table class="table tf-tabla mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Hoja</th>
                            <th scope="col">Día</th>
                            <th scope="col">Acción</th>
                            <th scope="col">Motivo</th>
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
            </div>
        </section>
    @endif

    {{-- ==================================================================
         5. CÓMO FUNCIONA LA CORRIDA
         ================================================================== --}}
    <section class="tc-tarjeta tf-seccion mb-3">
        <h3 class="tf-titulo">
            <i class="bi bi-info-circle" aria-hidden="true"></i>
            Cómo funciona la corrida de las {{ $horaDeLaCorrida }}
        </h3>

        <ul class="tf-reglas">
            <li>Una hoja del libro es un día, y su <strong>nombre es la fecha</strong> (aaaa-mm-dd).</li>
            <li><strong>Sólo se crean los días que faltan.</strong> Un día que ya está cargado no se vuelve a cargar nunca, aunque el libro lo traiga otra vez, y el Excel no pisa nada de lo guardado.</li>
            <li>Si un día se <strong>elimina</strong> desde el listado, la corrida siguiente lo vuelve a crear con lo que traiga el libro. Para que no vuelva, hay que quitar su hoja del Excel y sacar el libro de la cola.</li>
            <li>Si a una hoja le falta un campo obligatorio —energía, los 3 objetivos, una franja con hora y actividad, o el cierre del día— <strong>se salta completa</strong> y queda anotada con el motivo. Esa hoja se reintenta en la corrida siguiente.</li>
            <li>La hoja <strong>INSTRUCCIONES</strong> se ignora siempre, y también cualquier hoja cuyo nombre no sea una fecha.</li>
            <li>Los días que el libro no traiga y que tampoco estén en la base quedan como <strong>aviso</strong>: no son un error.</li>
            <li>El libro se guarda en <code>{{ $carpetas['jobs'] }}/</code>. Cuando todas sus fechas están cargadas pasa a <code>{{ $carpetas['procesados'] }}/</code>; si quedó alguna saltada, se queda en la cola esperando la corrección.</li>
        </ul>
    </section>

    <div class="tf-acciones">
        <a class="tc-boton tc-boton--contorno" href="{{ route('home') }}">
            <i class="bi bi-arrow-left" aria-hidden="true"></i>
            Volver al inicio
        </a>

        <a class="tc-boton tc-boton--azul" href="{{ route('diario.listado') }}">
            <i class="bi bi-journal-text" aria-hidden="true"></i>
            Consultar los diarios
        </a>
    </div>

@endsection
