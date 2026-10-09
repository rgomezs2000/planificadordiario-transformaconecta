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
         1. LAS PLANILLAS
         ================================================================== --}}
    <section class="tc-tarjeta tc-tarjeta--turquesa mb-3">
        <span class="tc-etiqueta tc-etiqueta--turquesa">Paso 1</span>
        <h2 class="tc-tarjeta__titulo mt-2">Descarga la planilla</h2>

        <p class="tc-tarjeta__texto">
            La <strong>genérica</strong> trae la hoja <strong>DIA</strong> para duplicar una vez por cada día
            del período y, al final, la hoja de <strong>INSTRUCCIONES</strong> (que el sistema ignora
            siempre, la dejes o la borres). La <strong>de un período</strong> ya viene con una hoja por día,
            nombrada con su fecha, con la fecha puesta y con su lista desplegable: no hay que duplicar ni
            renombrar nada.
        </p>

        @if ($plantillas === [])
            <span class="tf-insignia tf-insignia--rojo">No hay ninguna planilla creada</span>
            <p class="tf-ayuda mt-2 mb-0">
                Corre <code>php artisan planificacion:plantilla</code> y, si quieres una por período,
                <code>php artisan planificacion:plantilla --desde=2026-10-01 --hasta=2026-10-31</code>.
            </p>
        @else
            <div class="table-responsive">
                <table class="table tf-tabla mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Planilla</th>
                            <th scope="col">Qué trae</th>
                            <th scope="col">Peso</th>
                            <th scope="col">Actualizada</th>
                            <th scope="col" class="text-center">Descargar</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($plantillas as $una)
                            <tr>
                                <td>
                                    <strong>{{ $una['nombre'] }}</strong>

                                    @if ($una['generica'])
                                        <br><span class="tf-insignia tf-insignia--turquesa">genérica</span>
                                    @endif
                                </td>

                                <td>
                                    @if ($una['generica'])
                                        Hoja DIA para duplicar, una por cada día
                                    @else
                                        Una hoja por día, ya nombrada
                                        @if ($una['periodo'])
                                            · <span class="tf-planificacion__motivo">{{ $una['periodo'] }}</span>
                                        @endif
                                    @endif
                                </td>

                                <td>{{ $una['peso'] }}</td>
                                <td>{{ $una['modificado'] }}</td>

                                <td class="text-center">
                                    <a class="tc-boton tc-boton--turquesa"
                                       href="{{ route('planificacion.plantilla', ['archivo' => $una['nombre']]) }}">
                                        <i class="bi bi-download" aria-hidden="true"></i>
                                        Descargar
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p class="tf-ayuda mt-2 mb-0">
                ¿Otro período? <code>php artisan planificacion:plantilla --desde=2026-11-01 --hasta=2026-11-30</code>
            </p>
        @endif
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

                {{-- Limpia el campo del archivo sin enviar el formulario --}}
                <button type="button" class="tc-boton tc-boton--contorno" id="archivo-limpiar" disabled>
                    <i class="bi bi-eraser" aria-hidden="true"></i>
                    Limpiar
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

         Va en un contenedor propio porque el script lo refresca solo por AJAX
         (y también al montar o eliminar un libro) sin recargar la página. La
         huella que trae el contenedor es la del HTML que se acaba de renderizar:
         el refresco sólo cambia la tabla cuando la huella es otra.
         ================================================================== --}}
    <div id="cola-contenedor" data-huella="{{ $huellaCola }}">
        @include('planificacion._cola')
    </div>

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

@push('scripts')
    <script>
        {{-- Montar el libro, eliminar de la cola y mantener la cola al día: todo
             por AJAX y sin recargar la página.

             El refresco automático sólo MIRA. Pide la cola cada 20 segundos y
             cambia la tabla únicamente si el servidor devuelve otra huella; no
             dispara la corrida, que sigue siendo a las 00:00.

             La tabla la maneja DataTables (paginado, orden y búsqueda) y cada
             columna tiene su filtro, como en el buscador de diarios. --}}
        $(function () {
            var P = window.Planificador;

            if (! P) {
                return;
            }

            /** Cada cuánto se vuelve a pedir la cola (milisegundos). */
            var LATIDO = 20000;

            var $cola = $('#cola-contenedor');
            var $archivo = $('#archivo');
            var $limpiar = $('#archivo-limpiar');
            var $formulario = $archivo.closest('form');

            var tabla = null;
            var huella = $cola.data('huella') || null;
            var seleccion = [];          // nombres elegidos, aunque cambien de página
            var filtros = {};            // índice de columna => valor del filtro
            var busqueda = '';           // caja de búsqueda de DataTables
            var largoPagina = P.config.tabla.pageLength;
            var paginaActual = 0;

            /* ==============================================================
               La tabla: DataTables con un filtro por columna
               ============================================================== */

            /** Arma DataTables sobre la tabla de la cola (o la deja en null). */
            function armarTabla() {
                var $tabla = $('#tabla-cola');

                if (! $tabla.length) {
                    tabla = null;

                    return;
                }

                tabla = P.Tabla.crear('#tabla-cola', {
                    orderCellsTop: true,
                    pageLength: largoPagina,
                    displayStart: paginaActual * largoPagina,
                    columnDefs: [
                        // La casilla y los botones no se ordenan ni se buscan.
                        { targets: 0, orderable: false, searchable: false },
                        { targets: 7, orderable: false, searchable: false }
                    ],
                    language: $.extend({}, P.Tabla.idioma, {
                        search: 'Palabra clave:',
                        searchPlaceholder: 'Nombre del libro'
                    })
                });

                // Se devuelven los filtros que el usuario tenía puestos.
                $cola.find('[data-cola-filtro]').each(function () {
                    var indice = $(this).data('cola-filtro');

                    if (filtros[indice] !== undefined) {
                        $(this).val(filtros[indice]);
                    }
                });

                $.each(filtros, function (indice, valor) {
                    tabla.column(parseInt(indice, 10)).search(valor || '');
                });

                tabla.search(busqueda).draw(false);

                // La búsqueda general se recuerda sola.
                $tabla.on('search.dt', function () {
                    busqueda = tabla.search();
                });

                // Al cambiar de página o de filtro, las casillas marcadas siguen
                // marcadas (la selección vive fuera de la tabla).
                $tabla.on('draw.dt', function () {
                    aplicarSeleccion();
                    actualizar();
                });
            }

            /* ==============================================================
               La selección: vale para todas las páginas
               ============================================================== */

            function marcadosEnPantalla() {
                return $cola.find('.tf-cola__check:checked').map(function () {
                    return $(this).val();
                }).get();
            }

            function aplicarSeleccion() {
                $cola.find('.tf-cola__check').each(function () {
                    $(this).prop('checked', seleccion.indexOf($(this).val()) !== -1);
                });
            }

            function actualizar() {
                var $boton = $cola.find('#cola-eliminar');
                var $cuenta = $cola.find('#cola-cuenta');
                var $todos = $cola.find('#cola-todos');
                var enPantalla = $cola.find('.tf-cola__check').length;
                var marcados = marcadosEnPantalla().length;

                $boton.prop('disabled', seleccion.length === 0);
                $cuenta.text(
                    seleccion.length === 0
                        ? 'Ningún libro seleccionado'
                        : (seleccion.length === 1 ? '1 libro seleccionado' : seleccion.length + ' libros seleccionados')
                );

                $todos.prop('checked', enPantalla > 0 && marcados === enPantalla);
                $todos.prop('indeterminate', marcados > 0 && marcados < enPantalla);
            }

            function marcar(nombre, marcado) {
                var indice = seleccion.indexOf(nombre);

                if (marcado && indice === -1) {
                    seleccion.push(nombre);
                }

                if (! marcado && indice !== -1) {
                    seleccion.splice(indice, 1);
                }
            }

            $cola.on('change', '.tf-cola__check', function () {
                marcar($(this).val(), $(this).is(':checked'));
                actualizar();
            });

            // La casilla del encabezado marca y desmarca la página que se ve.
            $cola.on('change', '#cola-todos', function () {
                var marcado = $(this).is(':checked');

                $cola.find('.tf-cola__check').each(function () {
                    $(this).prop('checked', marcado);
                    marcar($(this).val(), marcado);
                });

                actualizar();
            });

            $cola.on('keyup change', '[data-cola-filtro]', function () {
                var indice = parseInt($(this).data('cola-filtro'), 10);

                filtros[indice] = $(this).val();

                if (tabla) {
                    tabla.column(indice).search($(this).val()).draw();
                }
            });

            /* ==============================================================
               El refresco en vivo
               ============================================================== */

            /** Un latido visual en el cartel de "En vivo". */
            function latir() {
                var $aviso = $cola.find('#cola-latido');

                $aviso.addClass('tf-cola__latido--activo');
                window.setTimeout(function () {
                    $aviso.removeClass('tf-cola__latido--activo');
                }, 900);
            }

            /**
             * Vuelve a pedir la cola. Sólo cambia la pantalla si la huella es
             * otra: si nada pasó, no toca el DOM (ni pierde filtros, ni página,
             * ni selección). Nunca dispara la importación.
             */
            function refrescarCola() {
                if (document.hidden) {
                    return;
                }

                $.ajax({
                    url: @json(route('planificacion.cola')),
                    type: 'GET',
                    dataType: 'json',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                }).done(function (respuesta) {
                    if (! respuesta || ! respuesta.ok || ! respuesta.data) {
                        return;
                    }

                    if (respuesta.data.huella === huella) {
                        return;
                    }

                    huella = respuesta.data.huella;

                    // La selección se queda sólo con lo que sigue en la cola.
                    var enCola = respuesta.data.archivos || [];

                    seleccion = seleccion.filter(function (nombre) {
                        return enCola.indexOf(nombre) !== -1;
                    });

                    if (tabla) {
                        tabla.destroy();
                        tabla = null;
                    }

                    $cola.html(respuesta.data.html);
                    armarTabla();
                    aplicarSeleccion();
                    actualizar();
                    latir();
                });
            }

            armarTabla();
            actualizar();

            window.setInterval(refrescarCola, LATIDO);

            // Al volver a la pestaña se mira enseguida.
            document.addEventListener('visibilitychange', function () {
                if (! document.hidden) {
                    refrescarCola();
                }
            });

            /* ==============================================================
               Montar el libro (AJAX, sin recargar)
               ============================================================== */

            $formulario.on('submit', function (evento) {
                evento.preventDefault();

                if (! $archivo[0] || ! $archivo[0].files.length) {
                    P.Alerta.aviso('Elige el libro de Excel que vas a montar.');

                    return;
                }

                var $montar = $formulario.find('button[type="submit"]');

                $montar.prop('disabled', true);

                $.ajax({
                    url: $formulario.attr('action'),
                    type: 'POST',
                    data: new FormData($formulario[0]),
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': P.token
                    }
                }).done(function (respuesta) {
                    if (! respuesta || respuesta.ok === false) {
                        P.Alerta.error(respuesta && respuesta.message
                            ? respuesta.message
                            : 'No se pudo montar el libro.');

                        return;
                    }

                    limpiarArchivo();
                    P.Alerta.exito(respuesta.message, 'Libro montado en la cola');
                    refrescarCola();
                }).fail(function (xhr) {
                    P.Alerta.error(P.Ajax.mensajeDeError(xhr));
                }).always(function () {
                    $montar.prop('disabled', false);
                });
            });

            /* ==============================================================
               Quitar libros de la cola
               ============================================================== */

            $cola.on('click', '#cola-eliminar', function () {
                var $boton = $(this);

                if (! seleccion.length) {
                    return;
                }

                var mensaje = seleccion.length === 1
                    ? '¿Deseas eliminar de la cola el libro «' + seleccion[0] + '»?'
                    : '¿Deseas eliminar de la cola los ' + seleccion.length + ' libros seleccionados?';

                P.Alerta.confirmar({
                    titulo: 'Eliminar libros de la cola',
                    mensaje: mensaje + ' No se van a procesar más y quedan guardados en '
                        + @json($carpetas['descartados']) + '/.',
                    textoAceptar: 'Sí, eliminar',
                    claseAceptar: 'btn btn-danger',
                    alAceptar: function () {
                        P.Ajax.peticion({
                            url: $boton.data('url'),
                            tipo: 'DELETE',
                            datos: { archivos: seleccion },
                            alExito: function (respuesta) {
                                seleccion = [];
                                P.Alerta.exito(respuesta.message, 'Libros eliminados');
                                refrescarCola();
                            }
                        });
                    }
                });
            });

            // Quitar un solo libro (el botón de cada fila).
            $cola.on('click', '.tf-cola__quitar', function () {
                var $boton = $(this);
                var archivo = $boton.data('archivo');

                P.Alerta.confirmar({
                    titulo: 'Quitar el libro de la cola',
                    mensaje: '¿Sacar «' + archivo + '» de la cola? No se procesa más y queda guardado en '
                        + @json($carpetas['descartados']) + '/. La base de datos no se toca.',
                    textoAceptar: 'Sí, quitar',
                    claseAceptar: 'btn btn-danger',
                    alAceptar: function () {
                        P.Ajax.peticion({
                            url: $boton.data('url'),
                            tipo: 'DELETE',
                            alExito: function (respuesta) {
                                marcar(archivo, false);
                                P.Alerta.exito(respuesta.message, 'Libro quitado de la cola');
                                refrescarCola();
                            }
                        });
                    }
                });
            });

            /* ==============================================================
               El campo del archivo: limpiar
               ============================================================== */

            function revisarArchivo() {
                $limpiar.prop('disabled', ! $archivo[0] || ! $archivo[0].files.length);
            }

            function limpiarArchivo() {
                $archivo.val('');
                revisarArchivo();
            }

            $archivo.on('change', revisarArchivo);
            revisarArchivo();

            $limpiar.on('click', limpiarArchivo);
        });
    </script>
@endpush
