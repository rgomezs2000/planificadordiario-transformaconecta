/* ==========================================================================
   SCRIPT · Funciones del formulario del diario
   Programa de Desarrollo Personal "Transforma-Conecta"
   --------------------------------------------------------------------------
   Se carga en la vista del formulario (resources/views/diario/formulario),
   que sirve para los tres modos:

       crear    /diario y /diario/today   → POST
       ver      /diario/{id}              → sólo lectura
       editar   /diario/{id}/editar       → PUT

   Funciones:
     1. Fecha (datepicker de Bootstrap) y día de la semana automático
     2. Tabla dinámica "Mi horario de hoy"
     3. Casillas que habilitan su campo de texto múltiple
     4. Botón limpiar
     5. Validación de los campos obligatorios y mensajes bajo el formulario
   6. Guardado por AJAX, con confirmación antes de registrar el diario
   ========================================================================== */

window.Planificador = window.Planificador || {};

(function ($, P) {
    'use strict';

    P.Formulario = {

        opciones: {
            selectorFormulario: '#formulario-diario',
            selectorFecha: '#plan_date',
            selectorDia: '.tf-dia',
            selectorTabla: '#horario-filas',
            selectorPlantilla: '#plantilla-horario',
            selectorVacio: '#horario-vacio',
            selectorAgregar: '#horario-agregar',
            selectorQuitar: '.tf-horario__quitar',
            selectorTodos: '#horario-todos',
            selectorCheckFila: '.tf-horario__check',
            selectorHabilita: '[data-tf-habilita]',
            selectorLimpiar: '#formulario-limpiar',
            selectorGuardar: '#formulario-guardar',
            selectorMensajes: '#formulario-mensajes',
            formatoFecha: 'yyyy-mm-dd'
        },

        modo: 'crear',
        soloLectura: false,
        contadorFilas: 0,
        guardando: false,

        /** Fecha con la que se abrió el formulario; la restituye "Limpiar". */
        fechaInicial: '',

        /* ==================================================================
           Arranque
           ================================================================== */

        iniciar: function (opciones) {
            if (opciones) {
                $.extend(this.opciones, opciones);
            }

            var $formulario = $(this.opciones.selectorFormulario);

            this.modo = $formulario.attr('data-modo') || 'crear';
            this.soloLectura = this.modo === 'ver';
            this.contadorFilas = parseInt($formulario.attr('data-indice-horario'), 10) || 0;

            // La fecha y el horario se preparan siempre: en modo ver también
            // sirven para marcar el día y para mostrar las franjas guardadas.
            this.iniciarFecha();
            this.iniciarHorario();

            if (! this.soloLectura) {
                this.iniciarAcciones();
                this.iniciarDetalles();
                this.iniciarValidacion();
                this.iniciarGuardado();
                this.sincronizarDetalles();
            }

            return this;
        },

        /* ==================================================================
           1. Fecha y día de la semana
           ================================================================== */

        iniciarFecha: function () {
            var $fecha = $(this.opciones.selectorFecha);

            if (! $fecha.length) {
                return;
            }

            // Si la fecha viene bloqueada (ruta /diario/today o modo ver) no se
            // abre el calendario: sólo se calcula el día de esa fecha.
            if (! $fecha.prop('readonly') && $.fn.datepicker) {
                $fecha.datepicker({
                    format: this.opciones.formatoFecha,
                    language: 'es',
                    autoclose: true,
                    todayHighlight: true,
                    weekStart: 1,
                    orientation: 'bottom auto',
                    // El calendario vive fuera del contenedor que scrollea para
                    // que no lo recorte el overflow.
                    container: 'body'
                });

                $fecha.on('changeDate', $.proxy(this.marcarDia, this));
            }

            $fecha.on('change blur', $.proxy(this.marcarDia, this));

            // Se recuerda la fecha de apertura: "Limpiar" la restituye tal cual.
            this.fechaInicial = $.trim(String($fecha.val() || ''));

            // Si la vista trae la fecha puesta, el día se marca de una vez.
            this.marcarDia();
        },

        /**
         * Marca el radiobutton del día que corresponde a la fecha elegida.
         * Los valores de los radiobuttons son los mismos que Date.getDay():
         * 0 = domingo, 1 = lunes, ... 6 = sábado.
         */
        marcarDia: function () {
            var valor = $.trim(String($(this.opciones.selectorFecha).val() || ''));
            var $dias = $(this.opciones.selectorDia);

            $dias.prop('checked', false);

            if (! /^\d{4}-\d{2}-\d{2}$/.test(valor)) {
                return;
            }

            var partes = valor.split('-');
            var fecha = new Date(Number(partes[0]), Number(partes[1]) - 1, Number(partes[2]));

            if (isNaN(fecha.getTime())) {
                return;
            }

            $dias.filter('[data-dia="' + fecha.getDay() + '"]').prop('checked', true);
        },

        /* ==================================================================
           2. Tabla dinámica del horario
           ================================================================== */

        iniciarHorario: function () {
            var self = this;

            $(this.opciones.selectorAgregar).on('click', function (evento) {
                evento.preventDefault();
                self.agregarFila();
            });

            $(this.opciones.selectorTabla).on('click', this.opciones.selectorQuitar, function (evento) {
                evento.preventDefault();
                $(this).closest('tr').remove();
                self.actualizarVacio();
                self.sincronizarTodos();

                if (! self.soloLectura) {
                    self.refrescarErrores();
                }
            });

            $(this.opciones.selectorTodos).on('change', function () {
                // El "change" se dispara a mano: marcar las casillas por código
                // no avisa a nadie, y el gráfico pinta las cumplidas más nítidas.
                $(self.opciones.selectorCheckFila)
                    .prop('checked', $(this).is(':checked'))
                    .trigger('change');

                $(this).prop('indeterminate', false);
            });

            $(this.opciones.selectorTabla).on('change', this.opciones.selectorCheckFila, function () {
                self.sincronizarTodos();
            });

            this.actualizarVacio();
        },

        /** Copia la plantilla, le pone el número de fila y la agrega al final. */
        agregarFila: function () {
            var plantilla = document.querySelector(this.opciones.selectorPlantilla);

            if (! plantilla) {
                return null;
            }

            var html = plantilla.innerHTML.replace(/__INDICE__/g, String(this.contadorFilas));
            this.contadorFilas++;

            var $fila = $(html).filter('tr');

            $(this.opciones.selectorTabla).append($fila);
            this.actualizarVacio();
            this.sincronizarTodos();

            $fila.find('input[type="time"]').trigger('focus');

            if (! this.soloLectura) {
                this.refrescarErrores();
            }

            return $fila;
        },

        /** La casilla de la cabecera queda marcada sólo si lo están todas. */
        sincronizarTodos: function () {
            var $filas = $(this.opciones.selectorCheckFila);
            var marcadas = $filas.filter(':checked').length;
            var $todos = $(this.opciones.selectorTodos);

            $todos.prop('checked', $filas.length > 0 && marcadas === $filas.length);
            $todos.prop('indeterminate', marcadas > 0 && marcadas < $filas.length);
        },

        /** Muestra u oculta el aviso de "presiona + para agregar". */
        actualizarVacio: function () {
            var filas = $(this.opciones.selectorTabla).find('tr').not('[id="horario-vacio"]').length;

            $(this.opciones.selectorVacio).toggle(filas === 0);
        },

        /* ==================================================================
           3. Casillas que habilitan su campo de texto múltiple
           ================================================================== */

        iniciarDetalles: function () {
            var self = this;

            $(document).on('change', this.opciones.selectorHabilita, function () {
                self.habilitarDetalle($(this));
            });
        },

        /**
         * Habilita o deshabilita el campo de texto que acompaña a la casilla.
         * Mientras está deshabilitado no se envía, así que un ítem sin marcar
         * nunca guarda descripción.
         */
        habilitarDetalle: function ($casilla) {
            var destino = $casilla.attr('data-tf-habilita');

            if (! destino) {
                return;
            }

            $('#' + destino).prop('disabled', ! $casilla.is(':checked'));
        },

        sincronizarDetalles: function () {
            var self = this;

            $(this.opciones.selectorHabilita).each(function () {
                self.habilitarDetalle($(this));
            });
        },

        /* ==================================================================
           4. Botón limpiar
           ================================================================== */

        iniciarAcciones: function () {
            var self = this;

            $(this.opciones.selectorLimpiar).on('click', function (evento) {
                evento.preventDefault();
                self.limpiar();
            });
        },

        /**
         * Vacía el formulario y lo deja como recién abierto.
         *
         * La única excepción es la fecha, que vuelve a la que tenía la pantalla
         * al abrirse: en /diario queda en blanco; en /diario/today se queda con
         * la fecha de hoy y al modificar con la del día que se está editando.
         * En los dos últimos casos el día tampoco se desmarca.
         */
        limpiar: function () {
            var $formulario = $(this.opciones.selectorFormulario);
            var $fecha = $(this.opciones.selectorFecha);
            var formulario = $formulario.get(0);

            if (formulario) {
                formulario.reset();
            }

            $(this.opciones.selectorTabla).find('tr').not('[id="horario-vacio"]').remove();
            this.actualizarVacio();

            $(this.opciones.selectorTodos).prop('checked', false).prop('indeterminate', false);

            // La fecha vuelve a como estaba al abrir el formulario: en /diario
            // queda en blanco; en /diario/today y al modificar se queda con la
            // fecha del día abierto, que es la identidad del registro.
            if ($.fn.datepicker && $fecha.data('datepicker')) {
                $fecha.datepicker('update', this.fechaInicial);
            }

            this.sincronizarDetalles();
            this.limpiarErrores();
            this.mostrarErrores([]);
            this.marcarDia();

            // El gráfico se rehace solo con los cambios del horario, pero aquí
            // las filas se quitan por código y eso no dispara ningún evento:
            // hay que avisarle para que vuelva al estado vacío.
            if (P.Grafico) {
                P.Grafico.actualizar();
            }

            return this;
        },

        /* ==================================================================
           5. Validación de los campos obligatorios
           ================================================================== */

        /**
         * Campos obligatorios: energía, fecha y día, los 3 objetivos, al menos
         * una franja del horario (con hora y actividad) y el cierre del día.
         */
        requeridos: function () {
            var lista = [
                { campo: '#plan_date', mensaje: 'Selecciona la fecha del diario.' },
                { campo: 'input[name="energy_level_id"]', mensaje: 'Selecciona tu nivel de energía de hoy.' }
            ];

            $('input[name^="goals["][name$="[description]"]').each(function (indice) {
                lista.push({
                    campo: this,
                    mensaje: 'Escribe el objetivo ' + (indice + 1) + ' de tus 3 objetivos principales.'
                });
            });

            var $filas = $(this.opciones.selectorTabla).find('tr').not('[id="horario-vacio"]');

            if (! $filas.length) {
                lista.push({
                    campo: this.opciones.selectorAgregar,
                    mensaje: 'Agrega al menos una franja en tu horario de hoy.'
                });
            }

            $filas.each(function (indice) {
                var $fila = $(this);

                lista.push({
                    campo: $fila.find('[data-tf-requerido="hora"]'),
                    mensaje: 'La franja ' + (indice + 1) + ' del horario necesita su hora.'
                });
                lista.push({
                    campo: $fila.find('[data-tf-requerido="actividad"]'),
                    mensaje: 'La franja ' + (indice + 1) + ' del horario necesita saber qué vas a hacer.'
                });
            });

            $.each([
                { campo: '#achievements', mensaje: 'Escribe lo que lograste hoy.' },
                { campo: '#pending', mensaje: 'Indica lo que quedó pendiente.' },
                { campo: '#pending_when', mensaje: 'Indica cuándo harás lo pendiente.' },
                { campo: '#proud_of', mensaje: 'Escribe por qué estás orgulloso/a de ti hoy.' }
            ], function (i, item) {
                lista.push(item);
            });

            return lista;
        },

        /** Valida todos los obligatorios y devuelve los errores encontrados. */
        validar: function () {
            var errores = [];

            $.each(this.requeridos(), function (i, requerido) {
                var $campo = $(requerido.campo).first();

                if (! $campo.length) {
                    return;
                }

                // Los radios se validan por grupo, no por elemento.
                if ($campo.is(':radio')) {
                    if (! $('input[name="' + $campo.attr('name') + '"]:checked').length) {
                        errores.push(requerido);
                    }

                    return;
                }

                if ($.trim(String($campo.val() || '')) === '') {
                    errores.push(requerido);
                }
            });

            return errores;
        },

        /** Pinta el panel de mensajes que va debajo del formulario. */
        mostrarErrores: function (errores) {
            this.limpiarErrores();

            var $caja = $(this.opciones.selectorMensajes);

            if (! errores.length) {
                $caja.prop('hidden', true).empty();

                return this;
            }

            var html = '<p class="tf-mensajes__titulo">' +
                '<i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>' +
                ' Revisa los campos obligatorios:</p><ul>';

            $.each(errores, function (i, error) {
                html += '<li>' + P.Util.escapar(error.mensaje) + '</li>';

                var $campo = $(error.campo).first();

                $campo.addClass('tf-campo--error');
                $campo.closest('.tf-objetivo, .tf-energia, .tf-horario__fila, .tf-check, .input-group')
                    .addClass('tf-campo--error');
            });

            $caja.html(html + '</ul>').prop('hidden', false);

            return this;
        },

        limpiarErrores: function () {
            $('.tf-campo--error').removeClass('tf-campo--error');
        },

        /** Vuelve a listar sólo los campos que están marcados como con error. */
        refrescarErrores: function () {
            var errores = $.grep(this.validar(), function (error) {
                return $(error.campo).first().hasClass('tf-campo--error');
            });

            this.mostrarErrores(errores);
        },

        iniciarValidacion: function () {
            var self = this;

            // Al salir de un obligatorio se avisa si quedó vacío.
            $(document)
                .off('blur.pfFormulario', '[data-tf-requerido], #plan_date')
                .on('blur.pfFormulario', '[data-tf-requerido], #plan_date', function () {
                    self.revisarCampo($(this));
                });

            // La energía es un grupo de radios: se revisa al cambiar.
            $(document)
                .off('change.pfFormulario', 'input[name="energy_level_id"]')
                .on('change.pfFormulario', 'input[name="energy_level_id"]', function () {
                    $('.tf-energia').removeClass('tf-campo--error');
                    $('input[name="energy_level_id"]').removeClass('tf-campo--error');
                    self.refrescarErrores();
                });
        },

        revisarCampo: function ($campo) {
            if (! $campo.length) {
                return;
            }

            var vacio = $.trim(String($campo.val() || '')) === '';

            $campo.toggleClass('tf-campo--error', vacio);
            $campo.closest('.tf-objetivo, .tf-horario__fila, .tf-check, .input-group')
                .toggleClass('tf-campo--error', vacio);

            this.refrescarErrores();
        },

        /** Lleva la vista hasta el campo con error y le da el foco. */
        enfocar: function (campo) {
            var $campo = $(campo).first();

            if (! $campo.length) {
                return;
            }

            var $contenedor = $('.tc-principal');
            var $destino = $campo.closest('.tf-objetivo, .tf-energia, .tf-horario__fila, .tf-check, .input-group');

            if (! $destino.length) {
                $destino = $campo;
            }

            // El que scrollea es el contenedor del contenido, no la ventana.
            if ($contenedor.length) {
                $contenedor.animate({
                    scrollTop: $destino.offset().top - $contenedor.offset().top +
                        $contenedor.scrollTop() - 20
                }, 250);
            }

            if ($campo[0] && $campo[0].focus) {
                try {
                    $campo[0].focus({ preventScroll: true });
                } catch (e) {
                    $campo.trigger('focus');
                }
            }
        },

        /* ==================================================================
           6. Guardado por AJAX
           ================================================================== */

        iniciarGuardado: function () {
            var self = this;

            $(this.opciones.selectorFormulario)
                .off('submit.pfFormulario')
                .on('submit.pfFormulario', function (evento) {
                    evento.preventDefault();
                    self.enviar();
                });
        },

        /**
         * Si falta algún obligatorio se muestran los mensajes debajo del
         * formulario y se lleva el foco al primero. Cuando todos están
         * completos, siempre pide la confirmación antes de guardar.
         */
        enviar: function () {
            var self = this;
            var errores = this.validar();

            if (errores.length) {
                this.mostrarErrores(errores);
                this.enfocar(errores[0].campo);

                return;
            }

            this.mostrarErrores([]);

            P.Alerta.confirmar({
                titulo: this.modo === 'editar' ? 'Modificar el diario' : 'Guardar el diario',
                mensaje: this.modo === 'editar'
                    ? '¿Desea guardar los cambios del diario?'
                    : '¿Desea guardar el diario?',
                textoAceptar: 'Sí, guardar',
                textoCancelar: 'Cancelar',
                claseAceptar: 'btn btn-warning',
                alAceptar: function () {
                    self.guardar();
                }
            });
        },

        /** Envía el formulario: POST al crear y PUT al modificar. */
        guardar: function () {
            if (this.guardando) {
                return this;
            }

            var self = this;
            var $formulario = $(this.opciones.selectorFormulario);
            var $boton = $(this.opciones.selectorGuardar);
            var textoBoton = $boton.html();
            var esEdicion = this.modo === 'editar';

            this.guardando = true;
            $boton.prop('disabled', true).html(
                '<i class="bi bi-hourglass-split" aria-hidden="true"></i> Guardando…'
            );

            var terminar = function () {
                self.guardando = false;
                $boton.prop('disabled', false).html(textoBoton);
            };

            P.Ajax.peticion({
                url: $formulario.attr('action'),
                tipo: esEdicion ? 'PUT' : 'POST',
                datos: $formulario.serialize(),
                avisoError: false,
                alExito: function (respuesta) {
                    terminar();

                    P.Alerta.exito(
                        respuesta.message,
                        esEdicion ? 'Diario actualizado' : 'Diario registrado',
                        function () {
                            window.location.href = $formulario.attr('data-url-exito');
                        }
                    );
                },
                alError: function (xhr, mensaje) {
                    terminar();
                    self.erroresDelServidor(xhr, mensaje);
                }
            });

            return this;
        },

        /**
         * Los errores del servidor se ven en los dos sitios: en el panel de
         * debajo del formulario y en una alerta.
         */
        erroresDelServidor: function (xhr, mensaje) {
            var respuesta = xhr ? xhr.responseJSON : null;
            var errores = [];

            if (respuesta && respuesta.errors) {
                $.each(respuesta.errors, function (campo, mensajes) {
                    var selector = '[name="' + P.Formulario.nombreDeCampo(campo) + '"]';

                    $.each($.isArray(mensajes) ? mensajes : [mensajes], function (i, texto) {
                        errores.push({ campo: selector, mensaje: texto });
                    });
                });
            }

            this.mostrarErrores(errores);
            P.Alerta.error(mensaje || 'No se pudo guardar el diario.');

            if (errores.length) {
                this.enfocar(errores[0].campo);
            }
        },

        /**
         * Laravel nombra los campos con puntos (goals.0.description) y el HTML
         * los nombra con corchetes (goals[0][description]). Aquí se traduce.
         */
        nombreDeCampo: function (campo) {
            var partes = String(campo || '').split('.');

            if (partes.length === 1) {
                return partes[0];
            }

            return partes[0] + '[' + partes.slice(1).join('][') + ']';
        }
    };

    /* ======================================================================
       Arranque: sólo actúa si la página trae el formulario
       ====================================================================== */

    $(function () {
        if ($(P.Formulario.opciones.selectorFormulario).length) {
            P.Formulario.iniciar();
        }
    });
})(jQuery, window.Planificador);
