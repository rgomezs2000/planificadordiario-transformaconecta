@extends('layouts.app')

@section('titulo', 'Diarios registrados · Mi Planificador Diario')

@push('estilos')
    {{-- Calendario del filtro por fecha, por CDN como el del formulario --}}
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-datepicker@1.10.1/dist/css/bootstrap-datepicker3.min.css">
@endpush

@section('contenido')

    {{-- Miniformulario de búsqueda, arriba de la tabla.

         La palabra clave se escribe y la tabla se filtra sola, sin botón: por eso
         "Filtrar por" sólo ofrece fecha y energía, que también se aplican al
         elegirlas. El bloque que se despliega va debajo del campo de la palabra
         clave, porque ese campo está siempre a la vista.
         "Limpiar" deja todo en blanco y vuelve a traer todos los diarios. --}}
    <section class="tf-filtros" id="filtros-diarios">
        <h4 class="tf-filtros__titulo">
            <i class="bi bi-funnel-fill" aria-hidden="true"></i>
            Buscar diario
        </h4>

        <div class="tf-filtros__cuerpo">
            {{-- Palabra clave: siempre a la vista, filtra mientras se escribe --}}
            <div class="tf-filtros__busqueda">
                <input type="search" class="form-control tf-filtros__palabra" id="filtro-palabra"
                       placeholder="Palabra clave: basta con una letra"
                       aria-label="Palabra clave del diario" autocomplete="off" maxlength="60"
                       data-tf-filtro-palabra>

                <p class="tf-filtros__ayuda">
                    Filtra mientras escribes; busca dentro de los objetivos, el horario, las notas
                    y el cierre del día.
                </p>
            </div>

            <div class="tf-filtros__fila">
                <div class="tf-filtros__grupo">
                    <label class="tf-etiqueta" for="filtro-por">Filtrar por</label>
                    <select class="form-select form-select-sm" id="filtro-por" data-tf-filtro-por>
                        <option value="todos">Todos los diarios</option>
                        <option value="fecha">Fecha</option>
                        <option value="energia">Energía</option>
                        <option value="periodo">Período</option>
                    </select>
                </div>

                {{-- Fecha: se aplica sola y marca el día de la semana --}}
                <div class="tf-filtros__grupo tf-filtros__grupo--ancho" data-tf-panel="fecha" hidden>
                    <label class="tf-etiqueta" for="filtro-fecha">Fecha</label>

                    <div class="tf-filtros__linea">
                        <input type="text" class="form-control form-control-sm" id="filtro-fecha"
                               placeholder="dd/mm/aaaa" autocomplete="off" data-tf-filtro-fecha>

                        <div class="tf-dias" data-tf-dias>
                            @foreach ($weekDays as $weekDay)
                                <span class="tf-dia__letra" data-dia="{{ $weekDay['value'] }}"
                                      title="{{ $weekDay['label'] }}">{{ $weekDay['label'] }}</span>
                            @endforeach
                        </div>
                    </div>

                    <p class="tf-filtros__ayuda">El día se marca solo al elegir la fecha.</p>
                </div>

                {{-- Energía: se aplica al elegirla --}}
                <div class="tf-filtros__grupo" data-tf-panel="energia" hidden>
                    <span class="tf-etiqueta">Energía</span>

                    <div class="tf-energia">
                        @foreach ($energyLevels as $energyLevel)
                            <input type="radio" class="btn-check" name="filtro_energia"
                                   id="filtro-energia-{{ $energyLevel->slug }}"
                                   value="{{ $energyLevel->slug }}" data-tf-filtro-energia>
                            <label class="tf-energia__boton tf-energia__boton--{{ $energyLevel->slug }}"
                                   for="filtro-energia-{{ $energyLevel->slug }}">
                                <span aria-hidden="true">{{ $energyLevel->emoji }}</span>
                                {{ $energyLevel->name }}
                            </label>
                        @endforeach
                    </div>

                    <p class="tf-filtros__ayuda">Vuelve a pulsar el mismo para quitarlo.</p>
                </div>

                {{-- Período: se elige el tipo y sólo se muestran sus campos.
                     Acá no va el día de la semana: el período abarca fechas
                     completas (la semana va de lunes a domingo). --}}
                <div class="tf-filtros__grupo tf-filtros__grupo--ancho" data-tf-panel="periodo" hidden>
                    <label class="tf-etiqueta" for="filtro-periodo-tipo">Período</label>

                    <div class="tf-filtros__linea">
                        <select class="form-select form-select-sm" id="filtro-periodo-tipo" data-tf-periodo-tipo>
                            <option value="semana">Semanal · elegir semana</option>
                            <option value="quincena">Quincenal · elegir quincena</option>
                            <option value="mes">Mensual · elegir mes</option>
                            <option value="trimestre">Trimestral · elegir trimestre</option>
                            <option value="semestre">Semestral · elegir semestre</option>
                            <option value="anio">Anual · elegir año</option>
                            <option value="rango">Rango de fechas · desde y hasta</option>
                        </select>

                        {{-- Una semana del calendario (el navegador la da como 2026-W40). --}}
                        <span class="tf-filtros__campo" data-tf-periodo="semana" hidden>
                            <input type="week" class="form-control form-control-sm" data-tf-campo="semana"
                                   aria-label="Semana del año">
                        </span>

                        {{-- Quincena: el mes y cuál de las dos mitades. --}}
                        <span class="tf-filtros__campo" data-tf-periodo="quincena" hidden>
                            <input type="month" class="form-control form-control-sm" data-tf-campo="mes"
                                   aria-label="Mes de la quincena">
                            <select class="form-select form-select-sm" data-tf-campo="mitad" aria-label="Quincena">
                                <option value="1">Primera quincena · del 1 al 15</option>
                                <option value="2">Segunda quincena · del 16 al fin</option>
                            </select>
                        </span>

                        {{-- Mes completo (el navegador lo da como 2026-09). --}}
                        <span class="tf-filtros__campo" data-tf-periodo="mes" hidden>
                            <input type="month" class="form-control form-control-sm" data-tf-campo="mes"
                                   aria-label="Mes">
                        </span>

                        {{-- Trimestre: el año y cuál de los cuatro. --}}
                        <span class="tf-filtros__campo" data-tf-periodo="trimestre" hidden>
                            <input type="number" class="form-control form-control-sm" data-tf-campo="anio"
                                   min="1900" max="2200" step="1" placeholder="Año" aria-label="Año del trimestre">
                            <select class="form-select form-select-sm" data-tf-campo="trimestre" aria-label="Trimestre">
                                <option value="1">Primero · enero a marzo</option>
                                <option value="2">Segundo · abril a junio</option>
                                <option value="3">Tercero · julio a septiembre</option>
                                <option value="4">Cuarto · octubre a diciembre</option>
                            </select>
                        </span>

                        {{-- Semestre: el año y cuál de los dos. --}}
                        <span class="tf-filtros__campo" data-tf-periodo="semestre" hidden>
                            <input type="number" class="form-control form-control-sm" data-tf-campo="anio"
                                   min="1900" max="2200" step="1" placeholder="Año" aria-label="Año del semestre">
                            <select class="form-select form-select-sm" data-tf-campo="semestre" aria-label="Semestre">
                                <option value="1">Primero · enero a junio</option>
                                <option value="2">Segundo · julio a diciembre</option>
                            </select>
                        </span>

                        {{-- Año completo. --}}
                        <span class="tf-filtros__campo" data-tf-periodo="anio" hidden>
                            <input type="number" class="form-control form-control-sm" data-tf-campo="anio"
                                   min="1900" max="2200" step="1" placeholder="Año" aria-label="Año">
                        </span>

                        {{-- Rango elegido a mano. --}}
                        <span class="tf-filtros__campo" data-tf-periodo="rango" hidden>
                            <input type="text" class="form-control form-control-sm" data-tf-campo="desde"
                                   placeholder="Desde dd/mm/aaaa" autocomplete="off" aria-label="Fecha desde">
                            <input type="text" class="form-control form-control-sm" data-tf-campo="hasta"
                                   placeholder="Hasta dd/mm/aaaa" autocomplete="off" aria-label="Fecha hasta">
                        </span>
                    </div>

                    <p class="tf-filtros__ayuda">
                        Se aplica solo al elegirlo. Las semanas van de lunes a domingo.
                    </p>
                </div>
            </div>
        </div>

        <div class="tf-filtros__acciones">
            <button type="button" class="tc-boton tc-boton--contorno" data-tf-limpiar>
                <i class="bi bi-eraser" aria-hidden="true"></i>
                Limpiar
            </button>
        </div>
    </section>

    <section class="tc-tarjeta tf-seccion mb-3">
        <h3 class="tf-titulo">
            <i class="bi bi-journal-text" aria-hidden="true"></i>
            Diarios registrados
        </h3>

        {{-- La tabla se llena por AJAX desde diario.tabla. DataTables pone el
             buscador, el selector de registros por página (5, 10, 25, 50 y 100)
             y el paginador. Si no hay filas escribe "No existen registros".

             Sin scrollX ni .table-responsive: con scrollX, DataTables reemplaza
             la cabecera por una copia que quedaba invisible al haber un pie de
             tabla. Las cuatro columnas se reparten solas (autoWidth). --}}
        <table class="table tf-tabla mb-0" id="tabla-diarios"
               data-url-tabla="{{ route('diario.tabla') }}"
               data-url-reporte="{{ route('diario.reporte') }}"
               data-url-ver="{{ route('diario.show', ['dailyPlan' => '__ID__']) }}"
               data-url-editar="{{ route('diario.edit', ['dailyPlan' => '__ID__']) }}"
               data-url-eliminar="{{ route('diario.destroy', ['dailyPlan' => '__ID__']) }}">
            <thead>
                <tr>
                    <th scope="col">Nº</th>
                    <th scope="col">Fecha</th>
                    <th scope="col">Energía</th>
                    <th scope="col" class="text-center">Acción</th>
                </tr>
            </thead>
            {{-- El pie repite la cabecera: así, cuando la tabla es larga, se
                 siguen leyendo los títulos abajo. DataTables sólo ordena por
                 los de arriba; éstos son la copia visual. --}}
            <tfoot>
                <tr>
                    <th scope="col">Nº</th>
                    <th scope="col">Fecha</th>
                    <th scope="col">Energía</th>
                    <th scope="col" class="text-center">Acción</th>
                </tr>
            </tfoot>
            <tbody></tbody>
        </table>
    </section>

    {{-- Los dos reportes: el detallado en Excel y el resumen de desempeño en PDF.
         El resumen abre su modal para elegir si es documento de muestra o real. --}}
    <div class="tf-acciones">
        <button type="button" class="tc-boton tc-boton--azul" data-tf-reporte>
            <i class="bi bi-file-earmark-text" aria-hidden="true"></i>
            Generar reporte detallado
        </button>

        <button type="button" class="tc-boton tc-boton--turquesa" data-tf-resumen
                title="Resumen de desempeño en PDF, con los filtros aplicados">
            <i class="bi bi-file-earmark-bar-graph" aria-hidden="true"></i>
            Generar resumen
        </button>

        <a class="tc-boton tc-boton--contorno" href="{{ route('home') }}">
            <i class="bi bi-x-lg" aria-hidden="true"></i>
            Cancelar
        </a>
    </div>

    @include('diario._modal_imprimir')
    @include('diario._modal_resumen')

@endsection

@push('scripts')
    {{-- Calendario del filtro por fecha, por CDN con su traducción --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap-datepicker@1.10.1/dist/js/bootstrap-datepicker.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap-datepicker@1.10.1/dist/locales/bootstrap-datepicker.es.min.js"></script>

    <script>
        $(function () {
            var P = window.Planificador;

            if (! P || ! P.Tabla) {
                return;
            }

            var $tabla = $('#tabla-diarios');
            var $filtros = $('#filtros-diarios');

            /** Filtros activos del buscador: es lo que viaja al servidor. */
            var filtros = { fecha: '', energia: '', palabra: '' };

            /** Lo último que se pidió, para no repetir la misma búsqueda. */
            var ultimaBusqueda = null;

            /** Espera antes de buscar mientras se escribe, para no pedir por tecla. */
            var espera = null;

            /** Sustituye __ID__ en las plantillas de dirección. */
            function url(plantilla, id) {
                return String($tabla.attr(plantilla) || '').replace('__ID__', id);
            }

            /** Botones de acción de cada fila. */
            function botones(id) {
                var acciones = [
                    ['modificar', 'bi-pencil-square', 'Modificar'],
                    ['ver', 'bi-eye', 'Ver'],
                    // Un solo botón de documentos: abre el modal, donde se elige
                    // entre el PDF y la imagen (y si lleva la marca de agua).
                    ['pdf', 'bi-file-earmark-pdf', 'Imprimir PDF o imagen'],
                    ['eliminar', 'bi-trash', 'Eliminar']
                ];

                var html = '<div class="tf-tabla__acciones">';

                acciones.forEach(function (accion) {
                    html += '<button type="button" class="tf-accion tf-accion--' + accion[0] + '"' +
                        ' data-accion="' + accion[0] + '" data-id="' + P.Util.escapar(id) + '"' +
                        ' title="' + accion[2] + '" aria-label="' + accion[2] + '">' +
                        '<i class="bi ' + accion[1] + '" aria-hidden="true"></i></button>';
                });

                return html + '</div>';
            }

            var tabla = P.Tabla.crear('#tabla-diarios', {
                // Sin scrollX: con él, DataTables reemplazaba la cabecera por
                // una copia que quedaba invisible al haber un pie de tabla.
                // Con autoWidth, las cuatro columnas se reparten solas.
                autoWidth: true,
                // Por defecto se ordena por la fecha del diario (columna 1), del
                // más antiguo al más actual: nunca por el número de registro ni
                // por la fecha de creación. Al pulsar una cabecera se reordena
                // por ese campo.
                order: [[1, 'asc']],
                ajax: {
                    url: $tabla.attr('data-url-tabla'),
                    // Los filtros viajan en todas las peticiones, así también se
                    // respetan al recargar la tabla después de eliminar.
                    data: function (datos) {
                        return $.extend({}, datos, filtros);
                    },
                    dataSrc: function (json) {
                        // Si el servidor contestó con error, se avisa.
                        if (! json || json.ok === false) {
                            P.Alerta.error((json && json.message) ||
                                'No se pudo cargar el listado de diarios.');

                            return [];
                        }

                        return (json.data && json.data.plans) || [];
                    },
                    error: function (xhr, textoEstado) {
                        // DataTables cancela la petición anterior cuando llega
                        // una búsqueda nueva. Esa cancelación no es una falla:
                        // avisar de ella mostraba un error falso al escribir.
                        if (textoEstado === 'abort') {
                            return;
                        }

                        P.Alerta.error(P.Ajax.mensajeDeError(xhr));
                    }
                },
                columns: [
                    { data: 'id', className: 'text-center' },
                    {
                        data: 'date',
                        className: 'text-center',
                        render: function (dato, tipo, fila) {
                            if (tipo !== 'display') {
                                return dato;   // ordena por la fecha ISO
                            }

                            return '<span class="tf-tabla__dia">' +
                                P.Util.escapar(fila.day_letter) + '</span>' +
                                P.Util.escapar(fila.date_label);
                        }
                    },
                    {
                        data: 'energy',
                        className: 'text-center',
                        render: function (dato, tipo, fila) {
                            if (tipo !== 'display') {
                                return dato || '';
                            }

                            if (! dato) {
                                return '<span class="tf-tabla__sin-dato">—</span>';
                            }

                            return '<span class="tf-energia-chip tf-energia-chip--' +
                                P.Util.escapar(fila.energy_slug || '') + '">' +
                                '<span aria-hidden="true">' + P.Util.escapar(fila.energy_emoji || '') +
                                '</span> ' + P.Util.escapar(dato) + '</span>';
                        }
                    },
                    {
                        data: 'id',
                        orderable: false,
                        className: 'text-center',
                        render: function (id, tipo, fila) {
                            return tipo === 'display' ? botones(id) : id;
                        }
                    }
                ]
            });

            /* --- Acciones de cada fila (delegadas: las filas las pinta DataTables) --- */
            $tabla.find('tbody').on('click', '[data-accion]', function () {
                var $boton = $(this);
                var accion = $boton.attr('data-accion');
                var id = $boton.attr('data-id');
                var fila = tabla.row($boton.closest('tr')).data() || {};

                if (accion === 'ver') {
                    window.location.href = url('data-url-ver', id);

                    return;
                }

                if (accion === 'modificar') {
                    window.location.href = url('data-url-editar', id);

                    return;
                }

                // Los dos formatos abren el mismo modal: ahí se elige entre PDF
                // e imagen, y si el documento lleva la marca de agua.
                if (accion === 'pdf' || accion === 'imagen') {
                    if (P.Impresion) {
                        P.Impresion.abrir(id);
                    }

                    return;
                }

                if (accion === 'eliminar') {
                    P.Alerta.confirmar({
                        titulo: 'Eliminar el diario',
                        mensaje: '¿Deseas eliminar el diario del ' + (fila.date_label || 'día') +
                            '? Esta acción no se puede deshacer.',
                        textoAceptar: 'Sí, eliminar',
                        claseAceptar: 'btn btn-danger',
                        alAceptar: function () {
                            // avisoExito va en falso a propósito: si no, el
                            // ayudante mostraría un aviso y la vista otro, y
                            // salían dos mensajes de la misma eliminación.
                            P.Ajax.peticion({
                                url: url('data-url-eliminar', id),
                                tipo: 'DELETE',
                                avisoExito: false,
                                alExito: function (respuesta) {
                                    // Un solo aviso y, al aceptarlo, se recarga
                                    // la página entera (no la tabla por AJAX).
                                    P.Alerta.exito(respuesta.message, 'Diario eliminado', function () {
                                        window.location.reload();
                                    });
                                }
                            });
                        }
                    });
                }
            });

            /* ==================================================================
               Miniformulario de filtros
               ================================================================== */

            /** Convierte dd/mm/aaaa en una fecha de JavaScript. */
            function comoFecha(texto) {
                var partes = String(texto || '').split('/');

                if (partes.length !== 3) {
                    return null;
                }

                var dia = parseInt(partes[0], 10);
                var mes = parseInt(partes[1], 10);
                var anio = parseInt(partes[2], 10);

                if (! dia || ! mes || ! anio || String(anio).length !== 4) {
                    return null;
                }

                var fecha = new Date(anio, mes - 1, dia);

                return isNaN(fecha.getTime()) || fecha.getDate() !== dia ? null : fecha;
            }

            /** Marca el círculo del día que corresponde a la fecha elegida. */
            function marcarDia(texto) {
                $filtros.find('[data-dia]').removeClass('tf-dia__letra--activo');

                var fecha = comoFecha(texto);

                if (fecha) {
                    $filtros.find('[data-dia="' + fecha.getDay() + '"]')
                        .addClass('tf-dia__letra--activo');
                }
            }

            /** Muestra el bloque elegido y los que ya tienen un valor cargado. */
            function pintarPaneles() {
                var elegido = $filtros.find('[data-tf-filtro-por]').val();

                $filtros.find('[data-tf-panel]').each(function () {
                    var $panel = $(this);
                    var nombre = $panel.attr('data-tf-panel');

                    $panel.prop('hidden', elegido !== nombre && ! filtros[nombre]);
                });

                pintarPeriodo();
            }

            /** Dentro de período se ve sólo el campo del tipo elegido. */
            function pintarPeriodo() {
                var tipo = $filtros.find('[data-tf-periodo-tipo]').val();

                $filtros.find('[data-tf-periodo]').each(function () {
                    $(this).prop('hidden', $(this).attr('data-tf-periodo') !== tipo);
                });
            }

            /**
             * Lo que se le manda al servidor según el período elegido.
             *
             * Los campos se leen sólo del bloque visible, así el mes de la
             * quincena no se confunde con el del mes completo. Un período a
             * medio llenar no filtra: se devuelve vacío.
             */
            function leerPeriodo() {
                var tipo = $filtros.find('[data-tf-periodo-tipo]').val();
                var $bloque = $filtros.find('[data-tf-periodo="' + tipo + '"]');

                if (! tipo || ! $bloque.length) {
                    return {};
                }

                var datos = { periodo: tipo };

                $bloque.find('[data-tf-campo]').each(function () {
                    datos[$(this).attr('data-tf-campo')] = $.trim($(this).val() || '');
                });

                var requeridos = {
                    semana: ['semana'], quincena: ['mes'], mes: ['mes'], anio: ['anio'],
                    trimestre: ['anio'], semestre: ['anio'], rango: ['desde', 'hasta']
                };
                var completo = true;

                $.each(requeridos[tipo] || [], function (indice, campo) {
                    if (! datos[campo]) {
                        completo = false;
                    }
                });

                return completo ? datos : {};
            }

            /** ¿Los dos juegos de filtros son el mismo? */
            function filtrosIguales(uno, otro) {
                var claves = ['fecha', 'energia', 'palabra', 'periodo', 'semana', 'mes', 'mitad',
                    'anio', 'trimestre', 'semestre', 'desde', 'hasta'];

                for (var i = 0; i < claves.length; i++) {
                    if ((uno[claves[i]] || '') !== (otro[claves[i]] || '')) {
                        return false;
                    }
                }

                return true;
            }

            /**
             * Toma lo que hay en el buscador y vuelve a pedir la tabla.
             * No hay botón: la palabra clave, la fecha, la energía y el período
             * se aplican solos en cuanto cambian.
             */
            function aplicar() {
                var nuevos = $.extend({
                    fecha: $.trim($filtros.find('[data-tf-filtro-fecha]').val()),
                    energia: $filtros.find('[data-tf-filtro-energia]:checked').val() || '',
                    palabra: $.trim($filtros.find('[data-tf-filtro-palabra]').val()),
                    periodo: '', semana: '', mes: '', mitad: '',
                    anio: '', trimestre: '', semestre: '', desde: '', hasta: ''
                }, leerPeriodo());

                // Si es exactamente lo mismo que ya se está mostrando, no se
                // pide de nuevo: así no se cancelan peticiones sin motivo.
                var repetida = ultimaBusqueda !== null && filtrosIguales(nuevos, ultimaBusqueda);

                filtros = nuevos;
                pintarPaneles();

                if (repetida) {
                    return;
                }

                ultimaBusqueda = $.extend({}, nuevos);
                tabla.ajax.reload();
            }

            /**
             * Los filtros que están aplicados ahora mismo. El resumen los usa
             * para armar su dirección con lo mismo que muestra la tabla.
             */
            P.filtrosDelListado = function () {
                return filtros;
            };

            /** Aplica con una pequeña espera, para no pedir en cada tecla. */
            function aplicarAlEscribir() {
                window.clearTimeout(espera);
                espera = window.setTimeout(aplicar, 350);
            }

            /** Deja el buscador en blanco y trae todos los diarios. */
            function limpiar() {
                window.clearTimeout(espera);

                filtros = { fecha: '', energia: '', palabra: '', periodo: '' };
                // Se olvida lo buscado: la próxima búsqueda se hace siempre.
                ultimaBusqueda = { fecha: '', energia: '', palabra: '', periodo: '' };

                $filtros.find('[data-tf-filtro-por]').val('todos');
                $filtros.find('[data-tf-filtro-fecha]').val('');
                $filtros.find('[data-tf-filtro-palabra]').val('');
                $filtros.find('[data-tf-filtro-energia]').prop('checked', false);
                // El período vuelve a su primera opción y sin valores.
                $filtros.find('[data-tf-periodo-tipo]').val('semana');
                $filtros.find('[data-tf-campo]').val('');

                marcarDia('');
                pintarPaneles();
                tabla.ajax.reload();
            }

            // Los dos campos del rango usan el mismo calendario en dd/mm/aaaa.
            $filtros.find('[data-tf-periodo="rango"] [data-tf-campo]').datepicker({
                format: 'dd/mm/yyyy',
                language: 'es',
                autoclose: true,
                todayHighlight: true,
                orientation: 'bottom auto'
            }).on('change changeDate', function () {
                aplicar();
            });

            // Calendario en dd/mm/aaaa, igual que el del formulario.
            $filtros.find('[data-tf-filtro-fecha]').datepicker({
                format: 'dd/mm/yyyy',
                language: 'es',
                autoclose: true,
                todayHighlight: true,
                orientation: 'bottom auto'
            }).on('change changeDate', function () {
                marcarDia($(this).val());
                aplicar();
            });

            // Escribir en la palabra clave filtra solo; borrar vuelve a traer todo.
            $filtros.on('input search', '[data-tf-filtro-palabra]', aplicarAlEscribir);

            // Enter no espera: aplica en el momento.
            $filtros.on('keydown', '[data-tf-filtro-palabra]', function (evento) {
                if (evento.key === 'Enter') {
                    evento.preventDefault();
                    window.clearTimeout(espera);
                    aplicar();
                }
            });

            $filtros.on('change', '[data-tf-filtro-por]', pintarPaneles);

            // Período: al cambiar el tipo se muestran sus campos y se aplica;
            // al cambiar cualquier campo, se aplica solo.
            $filtros.on('change', '[data-tf-periodo-tipo]', function () {
                pintarPeriodo();
                aplicar();
            });

            $filtros.on('change', '[data-tf-campo]', aplicar);
            $filtros.on('input', '[data-tf-campo]', aplicarAlEscribir);

            // Elegir una energía la aplica sola.
            $filtros.on('change', '[data-tf-filtro-energia]', function () {
                filtros.energia = $(this).val();
                pintarPaneles();
                aplicar();
            });

            // Volver a pulsar la energía marcada la quita: los radios no se
            // desmarcan solos, así que se hace a mano.
            $filtros.on('click', '.tf-energia__boton', function () {
                var $radio = $('#' + $(this).attr('for'));

                if ($radio.prop('checked') && filtros.energia) {
                    $radio.prop('checked', false);
                    filtros.energia = '';
                    pintarPaneles();
                    aplicar();

                    return false;
                }

                return true;
            });

            $filtros.on('click', '[data-tf-limpiar]', limpiar);

            // Reporte detallado en Excel: se genera con los filtros que estén
            // puestos, así el archivo trae lo mismo que se ve en la tabla.
            // El resumen de desempeño: primero se elige en el modal si el PDF
            // lleva la marca de muestra, y recién ahí se genera, con los filtros
            // que están aplicados en el buscador.
            var modalResumen = new bootstrap.Modal(document.getElementById('modalResumen'));

            $('[data-tf-resumen]').on('click', function () {
                modalResumen.show();
            });

            $('#resumen-generar').on('click', function () {
                var partes = [];

                $.each(P.filtrosDelListado ? P.filtrosDelListado() : {}, function (clave, valor) {
                    if (valor) {
                        partes.push(encodeURIComponent(clave) + '=' + encodeURIComponent(valor));
                    }
                });

                if ($('#resumen-marca').is(':checked')) {
                    partes.push('marca=1');
                }

                window.open(
                    $('#modalResumen').data('url-resumen') + (partes.length ? '?' + partes.join('&') : ''),
                    '_blank'
                );

                modalResumen.hide();
            });

            $('[data-tf-reporte]').on('click', function () {
                if (! P.Reporte) {
                    return;
                }

                P.Reporte.descargar($tabla.attr('data-url-reporte'), filtros, $(this));
            });

            pintarPaneles();
        });
    </script>

    @if (session('error'))
        <script>
            $(function () {
                if (window.Planificador && window.Planificador.Alerta) {
                    window.Planificador.Alerta.error(@json(session('error')));
                }
            });
        </script>
    @endif
@endpush
