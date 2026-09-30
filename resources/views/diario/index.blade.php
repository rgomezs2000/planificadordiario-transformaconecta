@extends('layouts.app')

@section('titulo', 'Diarios registrados · Mi Planificador Diario')

@section('contenido')

    <section class="tc-tarjeta tf-seccion mb-3">
        <h3 class="tf-titulo">
            <i class="bi bi-journal-text" aria-hidden="true"></i>
            Diarios registrados
        </h3>

        {{-- La tabla se llena por AJAX desde diario.tabla. DataTables pone el
             buscador, el selector de registros por página (5, 10, 25, 50 y 100)
             y el paginador. Si no hay filas escribe "No existen registros".

             No se envuelve en .table-responsive porque DataTables recomienda su
             propio scrollX: si no, los controles de la librería se desplazarían
             junto con la tabla en pantallas estrechas. --}}
        <table class="table tf-tabla mb-0" id="tabla-diarios"
               data-url-tabla="{{ route('diario.tabla') }}"
               data-url-ver="{{ route('diario.show', ['dailyPlan' => '__ID__']) }}"
               data-url-editar="{{ route('diario.edit', ['dailyPlan' => '__ID__']) }}"
               data-url-eliminar="{{ route('diario.destroy', ['dailyPlan' => '__ID__']) }}">
            <thead>
                <tr>
                    <th scope="col">ID</th>
                    <th scope="col">Fecha y día</th>
                    <th scope="col">Energía</th>
                    <th scope="col" class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </section>

    {{-- Los dos primeros todavía no hacen nada: quedan deshabilitados a propósito.
         Cuando se activen, sólo hay que quitarles el atributo disabled. --}}
    <div class="tf-acciones">
        <button type="button" class="tc-boton tc-boton--azul" disabled
                title="Disponible próximamente">
            <i class="bi bi-file-earmark-text" aria-hidden="true"></i>
            Generar reporte detallado
        </button>

        <button type="button" class="tc-boton tc-boton--turquesa" disabled
                title="Disponible próximamente">
            <i class="bi bi-file-earmark-bar-graph" aria-hidden="true"></i>
            Generar resumen
        </button>

        <a class="tc-boton tc-boton--contorno" href="{{ route('home') }}">
            <i class="bi bi-x-lg" aria-hidden="true"></i>
            Cancelar
        </a>
    </div>

    @include('diario._modal_imprimir')

@endsection

@push('scripts')
    <script>
        $(function () {
            var P = window.Planificador;

            if (! P || ! P.Tabla) {
                return;
            }

            var $tabla = $('#tabla-diarios');

            /** Sustituye __ID__ en las plantillas de dirección. */
            function url(plantilla, id) {
                return String($tabla.attr(plantilla) || '').replace('__ID__', id);
            }

            /** Botones de acción de cada fila. */
            function botones(id) {
                var acciones = [
                    ['modificar', 'bi-pencil-square', 'Modificar'],
                    ['ver', 'bi-eye', 'Ver'],
                    ['pdf', 'bi-file-earmark-pdf', 'Generar PDF'],
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
                scrollX: true,
                ajax: {
                    url: $tabla.attr('data-url-tabla'),
                    dataSrc: function (json) {
                        // Si el servidor contestó con error, se avisa.
                        if (! json || json.ok === false) {
                            P.Alerta.error((json && json.message) ||
                                'No se pudo cargar el listado de diarios.');

                            return [];
                        }

                        return (json.data && json.data.plans) || [];
                    },
                    error: function (xhr) {
                        P.Alerta.error(P.Ajax.mensajeDeError(xhr));
                    }
                },
                columns: [
                    { data: 'id', width: '5rem' },
                    {
                        data: 'date',
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

                if (accion === 'pdf') {
                    P.Impresion.abrir(id);

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
                            P.Ajax.eliminar(url('data-url-eliminar', id), function (respuesta) {
                                P.Alerta.exito(respuesta.message, 'Diario eliminado');
                                tabla.ajax.reload(null, false);
                            });
                        }
                    });
                }
            });
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
