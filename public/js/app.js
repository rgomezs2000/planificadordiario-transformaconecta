/* ==========================================================================
   APP · Utilidades comunes del planificador
   Programa de Desarrollo Personal "Transforma-Conecta"
   --------------------------------------------------------------------------
   Aquí viven las funciones de cliente que comparten todas las pantallas:

     Planificador.Alerta    avisos con bootbox.js
     Planificador.Ajax      peticiones al servidor con el sobre {ok, message, data}
     Planificador.Tabla     tablas con DataTables en español
     Planificador.Util      ayudas varias

   Este archivo se carga al final: primero define el espacio de nombres y,
   cuando el documento está listo, arranca el reloj y el menú lateral.
   ========================================================================== */

window.Planificador = window.Planificador || {};

(function ($, P) {
    'use strict';

    /* ======================================================================
       Configuración general
       ====================================================================== */

    P.config = {
        tituloAvisos: 'Mi Planificador Diario',
        botonAceptar: 'Aceptar',
        botonCancelar: 'Cancelar',
        botonSi: 'Sí, continuar',
        segundosParaCerrar: 0,
        tabla: {
            pageLength: 10,
            lengthMenu: [[5, 10, 25, 50, 100], [5, 10, 25, 50, 100]],
            // Sin orden inicial: se respeta el orden en que el servidor manda
            // las filas. Cada tabla puede fijar el suyo con la opción "order".
            order: []
        }
    };

    /** Testigo CSRF que Laravel espera en las peticiones que escriben. */
    P.token = $('meta[name="csrf-token"]').attr('content') || null;

    /* ======================================================================
       Utilidades
       ====================================================================== */

    P.Util = {

        /** Escapa texto antes de inyectarlo en el HTML. */
        escapar: function (texto) {
            if (texto === null || texto === undefined) {
                return '';
            }

            return String(texto)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        },

        /** 1234.5 -> "1.234,50" (formato español, igual que en el servidor). */
        numero: function (valor, decimales) {
            var cifras = decimales === undefined ? 0 : decimales;

            return Number(valor || 0).toLocaleString('es-ES', {
                minimumFractionDigits: cifras,
                maximumFractionDigits: cifras
            });
        },

        /** 90 -> "1 h 30 min" */
        minutos: function (total) {
            var minutos = Math.round(Number(total) || 0);

            if (minutos <= 0) {
                return '0 min';
            }

            var horas = Math.floor(minutos / 60);
            var resto = minutos % 60;
            var partes = [];

            if (horas > 0) {
                partes.push(horas + ' h');
            }

            if (resto > 0) {
                partes.push(resto + ' min');
            }

            return partes.join(' ');
        },

        /** Vacía un contenedor antes de volver a pintarlo. */
        limpiar: function (selector) {
            $(selector).empty();

            return this;
        }
    };

    /* ======================================================================
       Avisos con bootbox.js
       ====================================================================== */

    P.Alerta = {

        /** ¿Está bootbox disponible? */
        disponible: function () {
            return typeof window.bootbox !== 'undefined';
        },

        /** Aviso genérico. $alCerrar se ejecuta al cerrar el aviso. */
        mostrar: function (mensaje, titulo, claseIcono, alCerrar) {
            var texto = '<div class="d-flex align-items-start gap-2">' +
                '<i class="' + (claseIcono || 'bi bi-info-circle') + ' fs-4"></i>' +
                '<div>' + P.Util.escapar(mensaje) + '</div></div>';

            if (!this.disponible()) {
                window.alert(mensaje);

                if ($.isFunction(alCerrar)) {
                    alCerrar();
                }

                return;
            }

            window.bootbox.alert({
                title: titulo || P.config.tituloAvisos,
                message: texto,
                centerVertical: true,
                // El callback va a nivel del diálogo, NO dentro del botón:
                // bootbox 6 no llama al callback declarado en buttons.ok, y el
                // aviso se cerraba sin ejecutar lo que venía después (por
                // ejemplo, la redirección al guardar).
                callback: function () {
                    if ($.isFunction(alCerrar)) {
                        alCerrar();
                    }
                },
                buttons: {
                    ok: {
                        label: P.config.botonAceptar,
                        className: 'btn btn-primary'
                    }
                }
            });
        },

        exito: function (mensaje, titulo, alCerrar) {
            this.mostrar(mensaje, titulo || 'Listo', 'bi bi-check-circle-fill text-success', alCerrar);
        },

        error: function (mensaje, titulo) {
            this.mostrar(mensaje, titulo || 'Ocurrió un problema', 'bi bi-exclamation-triangle-fill text-danger');
        },

        aviso: function (mensaje, titulo) {
            this.mostrar(mensaje, titulo || 'Atención', 'bi bi-exclamation-circle-fill text-warning');
        },

        info: function (mensaje, titulo) {
            this.mostrar(mensaje, titulo || 'Información', 'bi bi-info-circle-fill text-primary');
        },

        /**
         * Pregunta de sí o no.
         * confirmar({ mensaje: '...', alAceptar: function () { ... } })
         */
        confirmar: function (opciones) {
            var config = $.extend({
                titulo: 'Confirmar',
                mensaje: '¿Deseas continuar?',
                textoAceptar: P.config.botonSi,
                textoCancelar: P.config.botonCancelar,
                claseAceptar: 'btn btn-warning',
                alAceptar: null,
                alCancelar: null
            }, opciones);

            if (!this.disponible()) {
                if (window.confirm(config.mensaje) && $.isFunction(config.alAceptar)) {
                    config.alAceptar();
                }

                return;
            }

            window.bootbox.confirm({
                title: config.titulo,
                message: P.Util.escapar(config.mensaje),
                centerVertical: true,
                buttons: {
                    confirm: {
                        label: config.textoAceptar,
                        className: config.claseAceptar
                    },
                    cancel: {
                        label: config.textoCancelar,
                        className: 'btn btn-outline-secondary'
                    }
                },
                callback: function (aceptado) {
                    if (aceptado && $.isFunction(config.alAceptar)) {
                        config.alAceptar();
                    }

                    if (!aceptado && $.isFunction(config.alCancelar)) {
                        config.alCancelar();
                    }
                }
            });
        }
    };

    /* ======================================================================
       Peticiones al servidor
       ====================================================================== */

    P.Ajax = {

        /** Arma el texto del aviso a partir del error devuelto. */
        mensajeDeError: function (xhr) {
            var respuesta = xhr ? xhr.responseJSON : null;

            if (!respuesta) {
                return 'No se pudo conectar con el servidor.';
            }

            if (respuesta.errors) {
                var lista = [];

                $.each(respuesta.errors, function (campo, mensajes) {
                    lista.push($.isArray(mensajes) ? mensajes.join(' ') : mensajes);
                });

                if (lista.length) {
                    return lista.join('\n');
                }
            }

            if (respuesta.message) {
                return respuesta.message;
            }

            if (xhr.status === 404) {
                return 'No se encontró el registro solicitado.';
            }

            return 'Ocurrió un error inesperado (' + xhr.status + ').';
        },

        /**
         * Petición con el sobre { ok, message, data } del controlador.
         *
         * P.Ajax.peticion({
         *     url: '/diario/listar',
         *     tipo: 'GET',
         *     datos: { buscar: 'x' },
         *     alExito: function (respuesta) { ... },
         *     alError: function (xhr, mensaje) { ... },
         *     avisoExito: false
         * });
         */
        peticion: function (opciones) {
            var config = $.extend({
                url: '',
                tipo: 'GET',
                datos: null,
                avisoExito: false,
                avisoError: true,
                alExito: null,
                alError: null
            }, opciones);

            return $.ajax({
                url: config.url,
                type: config.tipo,
                data: config.datos,
                dataType: 'json',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).done(function (respuesta) {
                if (respuesta && respuesta.ok === false) {
                    if (config.avisoError) {
                        P.Alerta.error(respuesta.message || 'No se pudo completar la operación.');
                    }

                    if ($.isFunction(config.alError)) {
                        config.alError(null, respuesta.message);
                    }

                    return;
                }

                if (config.avisoExito && respuesta && respuesta.message) {
                    P.Alerta.exito(respuesta.message);
                }

                if ($.isFunction(config.alExito)) {
                    config.alExito(respuesta);
                }
            }).fail(function (xhr) {
                var mensaje = P.Ajax.mensajeDeError(xhr);

                if (config.avisoError) {
                    P.Alerta.error(mensaje);
                }

                if ($.isFunction(config.alError)) {
                    config.alError(xhr, mensaje);
                }
            });
        },

        get: function (url, datos, alExito) {
            return P.Ajax.peticion({ url: url, tipo: 'GET', datos: datos, alExito: alExito });
        },

        post: function (url, datos, alExito) {
            return P.Ajax.peticion({
                url: url, tipo: 'POST', datos: datos, alExito: alExito, avisoExito: true
            });
        },

        put: function (url, datos, alExito) {
            return P.Ajax.peticion({
                url: url, tipo: 'PUT', datos: datos, alExito: alExito, avisoExito: true
            });
        },

        eliminar: function (url, alExito) {
            return P.Ajax.peticion({
                url: url, tipo: 'DELETE', alExito: alExito, avisoExito: true
            });
        }
    };

    /* ======================================================================
       Tablas con DataTables
       ====================================================================== */

    P.Tabla = {

        /** Textos de DataTables en español. */
        idioma: {
            emptyTable: 'No existen registros',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
            infoEmpty: 'Mostrando 0 a 0 de 0 registros',
            infoFiltered: '(filtrado de _MAX_ registros totales)',
            infoThousands: '.',
            lengthMenu: 'Mostrar _MENU_ registros',
            loadingRecords: 'Cargando…',
            processing: 'Procesando…',
            search: 'Buscar:',
            zeroRecords: 'No se encontraron resultados',
            paginate: {
                first: 'Primero',
                last: 'Último',
                next: 'Siguiente',
                previous: 'Anterior'
            },
            aria: {
                sortAscending: ': orden ascendente',
                sortDescending: ': orden descendente'
            }
        },

        /** Crea una tabla aplicando los valores por defecto del sistema. */
        crear: function (selector, opciones) {
            var config = $.extend(true, {}, P.config.tabla, {
                language: P.Tabla.idioma
            }, opciones || {});

            return $(selector).DataTable(config);
        }
    };

    /* ======================================================================
       Documentos del diario (PDF e imagen, con o sin marca de agua)
       ====================================================================== */

    P.Impresion = {

        opciones: {
            selectorModal: '#modalImprimir',
            selectorMarca: '#imprimir-marca',
            // Los dos botones del pie: cada uno declara su formato y su
            // dirección en el propio HTML, así acá no hay nada hardcodeado.
            selectorGenerar: '#modalImprimir [data-formato]',
            selectorDisparadores: '[data-accion="pdf"], [data-accion="imagen"], #imprimir-diario'
        },

        modal: null,
        planId: null,

        iniciar: function () {
            var elemento = document.querySelector(this.opciones.selectorModal);

            if (! elemento || ! window.bootstrap) {
                return this;
            }

            this.modal = window.bootstrap.Modal.getOrCreateInstance(elemento);

            // Cualquier botón de imprimir abre el modal recordando su id.
            $(document)
                .off('click.pfImprimir', this.opciones.selectorDisparadores)
                .on('click.pfImprimir', this.opciones.selectorDisparadores, $.proxy(function (evento) {
                    evento.preventDefault();

                    this.abrir($(evento.currentTarget).attr('data-id'));
                }, this));

            $(this.opciones.selectorGenerar)
                .off('click.pfImprimir')
                .on('click.pfImprimir', $.proxy(function (evento) {
                    this.generar(evento.currentTarget);
                }, this));

            return this;
        },

        /** Sustituye __ID__ en las plantillas de dirección que trae el modal. */
        url: function (plantilla, id) {
            return String(plantilla || '').replace('__ID__', id);
        },

        abrir: function (id) {
            this.planId = id;
            $(this.opciones.selectorMarca).prop('checked', false);

            if (this.modal) {
                this.modal.show();
            }

            return this;
        },

        /**
         * Comprueba en el servidor que el diario exista y entrega el documento
         * pedido:
         *
         *   - el PDF se abre en otra pestaña, listo para imprimir o guardar;
         *   - la imagen se descarga (un JPG, o un ZIP con una imagen por página
         *     si el PDF tiene dos o más).
         *
         * El botón que se pulsó trae el formato en data-formato y su dirección
         * en data-url. Si el diario no existe, el aviso lo muestra
         * Planificador.Ajax.
         */
        generar: function (boton) {
            var self = this;
            var id = this.planId;
            var $boton = $(boton);
            var formato = $boton.attr('data-formato') === 'imagen' ? 'imagen' : 'pdf';

            if (! id) {
                P.Alerta.error('No se identificó el diario que quieres generar.');

                return this;
            }

            var $modal = $(this.opciones.selectorModal);
            var marca = $(this.opciones.selectorMarca).is(':checked') ? 1 : 0;
            var destino = this.url($boton.attr('data-url'), id) + '?marca=' + marca;

            P.Ajax.peticion({
                url: this.url($modal.attr('data-url-detalle'), id),
                alError: function () {
                    if (self.modal) {
                        self.modal.hide();
                    }
                },
                alExito: function () {
                    if (self.modal) {
                        self.modal.hide();
                    }

                    if (formato === 'imagen') {
                        self.descargar(destino);

                        return;
                    }

                    window.open(destino, '_blank');
                }
            });

            return this;
        },

        /**
         * Descarga un archivo sin salir de la página.
         *
         * Si el servidor no pudo generarlo contesta en JSON con el aviso (por
         * ejemplo, cuando falta el conversor de PDF a JPG); en ese caso se
         * muestra el mensaje en vez de bajar un archivo roto.
         */
        descargar: function (url) {
            var enlace = document.createElement('a');

            enlace.href = url;
            enlace.download = '';
            enlace.style.display = 'none';

            document.body.appendChild(enlace);
            enlace.click();
            document.body.removeChild(enlace);

            return this;
        }
    };

    /* ======================================================================
       Reporte detallado en Excel
       ====================================================================== */

    P.Reporte = {

        /**
         * Pide el Excel al servidor con los filtros que estén puestos y lo
         * descarga, sin recargar la página.
         *
         * La respuesta puede ser el archivo (cuando hay diarios) o un JSON con
         * el aviso (cuando no hay ninguno o algo falló), así que se mira el
         * tipo de contenido antes de descargar.
         */
        descargar: function (url, filtros, $boton) {
            var self = this;
            var textoBoton = $boton && $boton.length ? $boton.html() : null;

            if ($boton && $boton.length) {
                $boton.prop('disabled', true).html(
                    '<i class="bi bi-hourglass-split" aria-hidden="true"></i> Generando…'
                );
            }

            var terminar = function () {
                if ($boton && $boton.length && textoBoton !== null) {
                    $boton.prop('disabled', false).html(textoBoton);
                }
            };

            $.ajax({
                url: url,
                method: 'GET',
                data: filtros || {},
                cache: false,
                xhrFields: { responseType: 'blob' }
            }).done(function (datos, estado, xhr) {
                terminar();

                if (self.esJson(xhr)) {
                    self.leerAviso(xhr.response);

                    return;
                }

                self.guardarArchivo(datos, self.nombreDe(xhr));
                P.Alerta.exito('El reporte se descargó en Excel.', 'Reporte generado');
            }).fail(function (xhr) {
                terminar();

                if (self.esJson(xhr)) {
                    self.leerAviso(xhr.response);

                    return;
                }

                P.Alerta.error('No se pudo generar el reporte detallado.');
            });

            return this;
        },

        /** ¿El servidor contestó con un aviso en JSON en vez del archivo? */
        esJson: function (xhr) {
            var tipo = '';

            try {
                tipo = xhr.getResponseHeader('Content-Type') || '';
            } catch (e) {
                tipo = '';
            }

            return tipo.indexOf('application/json') !== -1;
        },

        /** Lee el aviso del servidor (viene como bloque binario) y lo muestra. */
        leerAviso: function (bloque) {
            if (! bloque || ! window.FileReader) {
                P.Alerta.error('No se pudo generar el reporte detallado.');

                return;
            }

            var lector = new window.FileReader();

            lector.onload = function () {
                var respuesta = null;

                try {
                    respuesta = JSON.parse(String(lector.result));
                } catch (e) {
                    respuesta = null;
                }

                P.Alerta.error((respuesta && respuesta.message) ||
                    'No se pudo generar el reporte detallado.');
            };

            lector.readAsText(bloque);
        },

        /** Nombre del archivo: el que manda el servidor o uno de reserva. */
        nombreDe: function (xhr) {
            var cabecera = '';

            try {
                cabecera = xhr.getResponseHeader('Content-Disposition') || '';
            } catch (e) {
                cabecera = '';
            }

            var encontrado = /filename="?([^"]+)"?/.exec(cabecera);

            return encontrado ? encontrado[1] : 'reporte-diario.xlsx';
        },

        /** Dispara la descarga del archivo que llegó. */
        guardarArchivo: function (datos, nombre) {
            var enlace = document.createElement('a');
            var objeto = window.URL.createObjectURL(datos);

            enlace.href = objeto;
            enlace.download = nombre;
            enlace.style.display = 'none';

            document.body.appendChild(enlace);
            enlace.click();
            document.body.removeChild(enlace);

            // Se libera la memoria del archivo descargado.
            window.setTimeout(function () {
                window.URL.revokeObjectURL(objeto);
            }, 1000);

            return this;
        }
    };

    /* ======================================================================
       Arranque
       ====================================================================== */

    P.arrancar = function () {

        // Todas las peticiones llevan el testigo CSRF y el formato JSON.
        if (P.token) {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': P.token,
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
        }

        if (P.Reloj) {
            P.Reloj.iniciar();
        }

        if (P.Menu) {
            P.Menu.iniciar();
        }

        if (P.Impresion) {
            P.Impresion.iniciar();
        }

        // El gráfico del día, en las pantallas del formulario.
        if (P.Grafico) {
            P.Grafico.iniciar();
        }

        // Las tablas marcadas con data-tabla="1" se inicializan solas.
        if ($.fn.DataTable) {
            $('table[data-tabla="1"]').each(function () {
                P.Tabla.crear(this);
            });
        }
    };

    $(function () {
        P.arrancar();
    });
})(jQuery, window.Planificador);
