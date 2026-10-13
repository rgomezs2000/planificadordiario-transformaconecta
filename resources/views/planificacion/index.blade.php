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

         Igual que la cola: en un contenedor propio, con su huella, para que el
         script lo refresque solo por AJAX sin recargar la página.
         ================================================================== --}}
    <div id="corrida-contenedor" data-huella="{{ $huellaCorrida }}">
        @include('planificacion._corrida')
    </div>

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

    {{-- ==================================================================
         6. EL MODAL DE LA CORRIDA MANUAL

         Va aca afuera (y no dentro del bloque de la corrida) porque el bloque se
         refresca solo: si el modal viviera adentro, un refresco lo cerraria en
         pleno progreso.
         ================================================================== --}}
    <div class="modal fade" id="modalCorrida" tabindex="-1" aria-labelledby="modalCorridaTitulo" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCorridaTitulo">
                        <i class="bi bi-play-circle" aria-hidden="true"></i>
                        Corrida manual
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">
                    <p class="tf-corrida__paso" id="corrida-paso">Arrancando la corrida...</p>

                    <div class="progress tf-corrida__barra" role="progressbar" id="corrida-barra"
                         aria-label="Progreso de la corrida" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar progress-bar-striped progress-bar-animated"
                             id="corrida-barra-relleno" style="width: 0%">0%</div>
                    </div>

                    <div id="corrida-resultado" class="mt-3" aria-live="polite"></div>

                    <p class="tf-planificacion__motivo mt-3 mb-0">
                        La corrida crea en el diario <strong>solo los dias que faltan</strong> (no pisa nada) y deja
                        el paso a paso en <code>{{ \App\Planificacion\ArchivosDePlanificacion::LOG_CORRIDA }}</code>.
                        Podes cerrar esta ventana: la corrida sigue sola.
                    </p>
                </div>

                <div class="modal-footer">
                    <button type="button" class="tc-boton tc-boton--contorno" data-bs-dismiss="modal">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        {{-- Montar el libro, eliminar de la cola y mantener el módulo al día:
             todo por AJAX y sin recargar la página.

             El refresco automático sólo MIRA. Pide el estado cada 20 segundos
             (GET /planificacion/estado) y cambia cada bloque únicamente si el
             servidor devuelve otra huella; no dispara la corrida, que sigue
             siendo a las 00:00.

             Las dos tablas —la cola y el detalle de la última corrida— las
             maneja DataTables: paginado, orden y búsqueda que filtra desde la
             primera letra, más un filtro por columna en cada una. --}}
        $(function () {
            var P = window.Planificador;

            if (! P) {
                return;
            }

            /** Cada cuánto se vuelve a pedir el estado (milisegundos). */
            var LATIDO = 20000;

            var $cola = $('#cola-contenedor');
            var $corrida = $('#corrida-contenedor');
            var $archivo = $('#archivo');
            var $limpiar = $('#archivo-limpiar');
            var $formulario = $archivo.closest('form');

            var seleccion = [];          // libros elegidos, aunque cambien de página

            /* ==============================================================
               Los bloques que se refrescan solos
               ============================================================== */

            /** Un bloque con su tabla: contenedor, selector y estado guardado. */
            function bloque($contenedor, selector, columnDefs, idioma) {
                return {
                    $contenedor: $contenedor,
                    selector: selector,
                    columnDefs: columnDefs,
                    idioma: $.extend({}, P.Tabla.idioma, { search: 'Palabra clave:' }, idioma),
                    tabla: null,
                    huella: $contenedor.data('huella') || null,
                    filtros: {},
                    busqueda: '',
                    largo: P.config.tabla.pageLength,
                    pagina: 0
                };
            }

            /** Guarda filtros, página y búsqueda antes de cambiar la tabla. */
            function recordar(uno) {
                if (! uno.tabla) {
                    return;
                }

                var info = uno.tabla.page.info();

                uno.largo = info.length;
                uno.pagina = info.page;
                uno.busqueda = uno.tabla.search();

                uno.$contenedor.find('[data-filtro]').each(function () {
                    uno.filtros[$(this).data('filtro')] = $(this).val();
                });
            }

            /** Arma (o vuelve a armar) DataTables sobre la tabla del bloque. */
            function armar(uno) {
                var $tabla = uno.$contenedor.find(uno.selector);

                if (! $tabla.length) {
                    uno.tabla = null;

                    return;
                }

                uno.tabla = P.Tabla.crear(uno.selector, {
                    orderCellsTop: true,
                    searchDelay: 0,
                    pageLength: uno.largo,
                    displayStart: uno.pagina * uno.largo,
                    columnDefs: uno.columnDefs,
                    language: uno.idioma
                });

                // Se devuelven los filtros y la búsqueda que estaban puestos.
                uno.$contenedor.find('[data-filtro]').each(function () {
                    var indice = $(this).data('filtro');

                    if (uno.filtros[indice] !== undefined) {
                        $(this).val(uno.filtros[indice]);
                    }
                });

                $.each(uno.filtros, function (indice, valor) {
                    uno.tabla.column(parseInt(indice, 10)).search(valor || '');
                });

                uno.tabla.search(uno.busqueda).draw(false);

                // La búsqueda general se recuerda sola y filtra desde la
                // primera letra.
                $tabla.on('search.dt', function () {
                    uno.busqueda = uno.tabla.search();
                });
            }

            var enCola = bloque($cola, '#tabla-cola', [
                // La casilla y los botones no se ordenan ni se buscan.
                { targets: 0, orderable: false, searchable: false },
                { targets: 7, orderable: false, searchable: false }
            ], { searchPlaceholder: 'Nombre del libro' });

            var enCorrida = bloque($corrida, '#tabla-corrida', [], {
                searchPlaceholder: 'Hoja, día o motivo'
            });

            /* ==============================================================
               La selección de la cola (vale para todas las páginas)
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

            // Los filtros de las dos tablas (delegados: sobreviven al refresco).
            $(document).on('keyup change', '[data-filtro]', function () {
                var $campo = $(this);
                var uno = $campo.closest('#corrida-contenedor').length ? enCorrida : enCola;
                var indice = parseInt($campo.data('filtro'), 10);

                uno.filtros[indice] = $campo.val();

                if (uno.tabla) {
                    uno.tabla.column(indice).search($campo.val()).draw();
                }
            });

            // Al cambiar de página o de filtro, las casillas marcadas siguen
            // marcadas (la selección vive fuera de la tabla).
            $cola.on('draw.dt', '#tabla-cola', function () {
                aplicarSeleccion();
                actualizar();
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

            /** Cambia el bloque sólo si su huella es otra. Devuelve si cambió. */
            function refrescarBloque(uno, datos) {
                if (! datos || datos.huella === uno.huella) {
                    return false;
                }

                uno.huella = datos.huella;

                recordar(uno);

                if (uno.tabla) {
                    uno.tabla.destroy();
                    uno.tabla = null;
                }

                uno.$contenedor.html(datos.html);

                armar(uno);

                return true;
            }

            /**
             * Vuelve a pedir el estado del módulo. Sólo cambia la pantalla si algo
             * cambió: si no, no toca el DOM (ni pierde filtros, ni página, ni
             * selección). Nunca dispara la importación.
             */
            function refrescar() {
                if (document.hidden) {
                    return;
                }

                $.ajax({
                    url: @json(route('planificacion.estado')),
                    type: 'GET',
                    dataType: 'json',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                }).done(function (respuesta) {
                    if (! respuesta || ! respuesta.ok || ! respuesta.data) {
                        return;
                    }

                    // La selección se queda sólo con lo que sigue en la cola.
                    var archivos = (respuesta.data.cola || {}).archivos || [];

                    seleccion = seleccion.filter(function (nombre) {
                        return archivos.indexOf(nombre) !== -1;
                    });

                    var cambioLaCola = refrescarBloque(enCola, respuesta.data.cola);
                    var cambioLaCorrida = refrescarBloque(enCorrida, respuesta.data.corrida);

                    if (cambioLaCola) {
                        aplicarSeleccion();
                        actualizar();
                    }

                    if (cambioLaCola || cambioLaCorrida) {
                        latir();
                    }
                });
            }

            armar(enCola);
            armar(enCorrida);
            actualizar();

            window.setInterval(refrescar, LATIDO);

            // Al volver a la pestaña se mira enseguida.
            document.addEventListener('visibilitychange', function () {
                if (! document.hidden) {
                    refrescar();
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
                    refrescar();
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
                                refrescar();
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
                                refrescar();
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

            /* ==============================================================
               La corrida a mano: confirmación, barra de progreso y resultado
               ============================================================== */

            var $modalCorrida = $('#modalCorrida');
            var modalCorrida = null;
            var relojCorrida = null;

            /** Abre el modal de la corrida con la barra en cero. */
            function abrirCorrida() {
                if (! window.bootstrap || ! $modalCorrida.length) {
                    return;
                }

                if (! modalCorrida) {
                    modalCorrida = window.bootstrap.Modal.getOrCreateInstance($modalCorrida[0], {
                        backdrop: 'static',
                        keyboard: false
                    });
                }

                $('#corrida-resultado').empty();
                $('#corrida-barra-relleno')
                    .removeClass('bg-success bg-warning bg-danger')
                    .addClass('progress-bar-animated progress-bar-striped');

                pintarCorrida({ estado: 'en_curso', porcentaje: 0, paso: 'Arrancando la corrida…' });

                modalCorrida.show();
            }

            /** La barra y el paso que se está dando. */
            function pintarCorrida(datos) {
                var porcentaje = Math.max(0, Math.min(100, parseInt(datos.porcentaje || 0, 10) || 0));

                $('#corrida-barra').attr('aria-valuenow', porcentaje);
                $('#corrida-barra-relleno').css('width', porcentaje + '%').text(porcentaje + '%');
                $('#corrida-paso').text(datos.paso || 'Procesando…');
            }

            /** El resultado final, dentro del modal. */
            function mostrarResultado(datos) {
                var resumen = datos.resumen || {};
                var clase = datos.resultado === 'completada' ? 'success'
                    : (datos.resultado === 'con_observaciones' ? 'warning' : 'danger');
                var icono = clase === 'success' ? 'check-circle'
                    : (clase === 'warning' ? 'exclamation-triangle' : 'x-octagon');

                var $caja = $('<div>').addClass('alert alert-' + clase + ' mb-0');

                $('<strong>').html('<i class="bi bi-' + icono + '"></i> '
                    + (datos.resultado_en_palabras || 'Corrida terminada')).appendTo($caja);

                if (datos.mensaje) {
                    $('<p>').addClass('mb-2 mt-1').text(datos.mensaje).appendTo($caja);
                }

                if (resumen && (resumen.creados || resumen.omitidos || resumen.saltados || resumen.ignorados || resumen.errores)) {
                    $('<p>').addClass('mb-0').html(
                        '<strong>Creados:</strong> ' + (resumen.creados || 0)
                        + ' · <strong>Ya estaban:</strong> ' + (resumen.omitidos || 0)
                        + ' · <strong>Saltados:</strong> ' + (resumen.saltados || 0)
                        + ' · <strong>Ignorados:</strong> ' + (resumen.ignorados || 0)
                        + ' · <strong>Errores:</strong> ' + (resumen.errores || 0)
                    ).appendTo($caja);
                }

                if (datos.observaciones && datos.observaciones.length) {
                    $('<p>').addClass('mb-1 mt-2').html('<strong>Observaciones</strong>').appendTo($caja);

                    var $lista = $('<ul>').addClass('tf-avisos mb-0');

                    $.each(datos.observaciones, function (i, observacion) {
                        $('<li>').text(observacion).appendTo($lista);
                    });

                    $lista.appendTo($caja);
                }

                $('#corrida-resultado').empty().append($caja);
            }

            function detenerReloj() {
                if (relojCorrida) {
                    window.clearInterval(relojCorrida);
                    relojCorrida = null;
                }
            }

            /** Pregunta cómo va la corrida y pinta el modal. */
            function seguirCorrida() {
                $.ajax({
                    url: $corrida.find('#corrida-ejecutar').data('estado-url') || @json(route('planificacion.corrida')),
                    type: 'GET',
                    dataType: 'json',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                }).done(function (respuesta) {
                    if (! respuesta || ! respuesta.ok || ! respuesta.data) {
                        return;
                    }

                    pintarCorrida(respuesta.data);

                    if (['terminada', 'interrumpida', 'sin_corrida'].indexOf(respuesta.data.estado) !== -1) {
                        detenerReloj();

                        if (respuesta.data.estado !== 'sin_corrida') {
                            mostrarResultado(respuesta.data);
                            $('#corrida-barra-relleno').removeClass('progress-bar-animated progress-bar-striped');
                        }

                        refrescar();
                    }
                });
            }

            function empezarARelojear() {
                detenerReloj();
                relojCorrida = window.setInterval(seguirCorrida, 1000);
                seguirCorrida();
            }

            // El botón: confirma con bootbox y arranca la corrida.
            $corrida.on('click', '#corrida-ejecutar', function () {
                var $boton = $(this);

                if ($boton.prop('disabled')) {
                    return;
                }

                P.Alerta.confirmar({
                    titulo: 'Ejecutar corrida',
                    mensaje: '¿Desea ejecutar la corrida manual? Se procesan ahora los libros de la cola, '
                        + 'con las mismas reglas de la corrida de las ' + @json(\App\Console\Commands\ImportarPlanificacion::HORA) + '.',
                    textoAceptar: 'Sí, ejecutar',
                    alAceptar: function () {
                        $.ajax({
                            url: $boton.data('url'),
                            type: 'POST',
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
                                    : 'No se pudo arrancar la corrida.');

                                return;
                            }

                            abrirCorrida();
                            empezarARelojear();
                        }).fail(function (xhr) {
                            P.Alerta.error(P.Ajax.mensajeDeError(xhr));
                        });
                    }
                });
            });

            // Si la página se abre con una corrida en curso, se sigue mostrando.
            $.ajax({
                url: @json(route('planificacion.corrida')),
                type: 'GET',
                dataType: 'json',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            }).done(function (respuesta) {
                if (respuesta && respuesta.ok && respuesta.data && respuesta.data.estado === 'en_curso') {
                    abrirCorrida();
                    empezarARelojear();
                }
            });
        });
    </script>
@endpush
