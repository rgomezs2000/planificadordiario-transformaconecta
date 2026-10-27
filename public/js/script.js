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
     7. Cumplimiento del horario por AJAX en la vista de sólo lectura
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
            selectorGuardarHorario: '#horario-guardar',
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

        /** Última fecha consultada para traer la meta de la semana. */
        ultimaFechaMeta: null,

        /** Valores que se cargaron solos, para no pisar lo que la persona escribe. */
        metaCargada: { goal: '', why: '' },
        reflexionCargada: {},

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

            // Las casillas que habilitan minutos se enlazan en los dos modos:
            // en la vista de sólo lectura también se pueden marcar actividades.
            this.iniciarDetalles();

            if (! this.soloLectura) {
                this.iniciarAcciones();
                this.iniciarMetaSemanal();
                this.iniciarValidacion();
                this.iniciarGuardado();
            } else {
                // En la vista de sólo lectura se marcan el horario y las
                // actividades desde los botones de cada sección.
                this.iniciarCumplimiento();
                this.iniciarCumplimientoActividades();
            }

            this.sincronizarDetalles();

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
                this.seccionesPorDia(null);

                return;
            }

            var partes = valor.split('-');
            var fecha = new Date(Number(partes[0]), Number(partes[1]) - 1, Number(partes[2]));

            if (isNaN(fecha.getTime())) {
                this.seccionesPorDia(null);

                return;
            }

            $dias.filter('[data-dia="' + fecha.getDay() + '"]').prop('checked', true);

            // Las secciones que dependen del día (sábado y domingo) y la meta
            // de la semana de esa fecha se ajustan solas.
            this.seccionesPorDia(fecha.getDay());
            this.cargarMetaSemanal(valor);
        },

        /**
         * Muestra u oculta lo que sólo aplica a un día de la semana.
         *
         * El sábado suma las actividades del cambio de semana; el domingo,
         * la reflexión semanal. Los campos de una sección oculta se
         * deshabilitan para que no viajen al guardar.
         *
         * @param {number|null} dia 0 = domingo … 6 = sábado; null si no hay fecha.
         */
        seccionesPorDia: function (dia) {
            var self = this;
            var letras = ['D', 'L', 'M', 'M', 'J', 'V', 'S'];
            var nombres = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

            $('[data-solo-dia]').each(function () {
                var $bloque = $(this);
                var solo = $.trim(String($bloque.attr('data-solo-dia')));

                if (solo === '') {
                    return;
                }

                var visible = dia !== null && parseInt(solo, 10) === dia;

                $bloque.prop('hidden', !visible);
                $bloque.find('input, textarea, select').each(function () {
                    if (self.soloLectura) {
                        // En la vista de sólo lectura las actividades del
                        // sábado también se marcan y se guardan con su botón;
                        // la reflexión, en cambio, sigue siendo de lectura.
                        if ($bloque.hasClass('tf-actividad')) {
                            $(this).prop('disabled', ! visible);
                        } else {
                            $(this).prop('disabled', true);
                        }

                        return;
                    }

                    $(this).prop('disabled', !visible);
                });
            });

            if (dia === null) {
                return;
            }

            $('#meta-personal-dia').text(letras[dia]);
            $('#meta-personal-dia-nombre').text(nombres[dia]);

            // Los minutos de una actividad siguen deshabilitados hasta que su
            // casilla está marcada, aunque la sección haya quedado visible.
            this.sincronizarDetalles();
        },

        /**
         * Trae la meta de la semana de la fecha elegida.
         *
         * Si esa semana ya la tiene escrita, el formulario la muestra; si no,
         * los campos quedan vacíos para completarlos. Sólo se rellenan campos
         * vacíos: nunca pisa lo que la persona ya escribió.
         */
        cargarMetaSemanal: function (fecha) {
            if (this.soloLectura) {
                return this;
            }

            var url = $(this.opciones.selectorFormulario).attr('data-url-meta-semanal');

            if (! url || ! /^\d{4}-\d{2}-\d{2}$/.test(String(fecha || ''))) {
                return this;
            }

            if (fecha === this.ultimaFechaMeta) {
                return this;
            }

            this.ultimaFechaMeta = fecha;

            var self = this;

            $.getJSON(url, { fecha: fecha })
                .done(function (respuesta) {
                    var datos = respuesta && respuesta.data ? respuesta.data : null;

                    if (! datos) {
                        return;
                    }

                    var $meta = $('#weekly_goal_goal');
                    var $porque = $('#weekly_goal_why');

                    // Se reemplaza el valor cuando el campo sigue vacío o
                    // todavía tiene lo que se cargó solo; si la persona lo
                    // escribió, se respeta.
                    var metaActual = $meta.length ? $.trim(String($meta.val() || '')) : '';
                    var porqueActual = $porque.length ? $.trim(String($porque.val() || '')) : '';

                    if ($meta.length && (metaActual === '' || metaActual === self.metaCargada.goal)) {
                        $meta.val(datos.goal || '');
                    }

                    if ($porque.length && (porqueActual === '' || porqueActual === self.metaCargada.why)) {
                        $porque.val(datos.why_important || '');
                    }

                    self.metaCargada = {
                        goal: $meta.length ? $.trim(String($meta.val() || '')) : '',
                        why: $porque.length ? $.trim(String($porque.val() || '')) : ''
                    };

                    self.aplicarEstadoMeta(!! datos.goal);

                    $('#meta-semanal-etiqueta').text(
                        datos.label || 'Aún no hay meta para esta semana'
                    );

                    if (datos.goal) {
                        $('#meta-personal-meta').html(
                            'Tu meta de la semana: <strong>' + P.Util.escapar(datos.goal) + '</strong>'
                        );
                    } else {
                        $('#meta-personal-meta').text(
                            'La meta de la semana se muestra aquí en cuanto la escribas.'
                        );
                    }

                    // Reflexión: las seis respuestas de esa semana. Igual que
                    // la meta, sólo se reemplaza lo que sigue como se cargó.
                    var respuestas = datos.reflection && datos.reflection.answers
                        ? datos.reflection.answers
                        : {};

                    $.each([
                        'how_was_week', 'what_achieved', 'what_was_hard',
                        'what_learned', 'how_improved', 'what_different'
                    ], function (i, clave) {
                        var $campo = $('#reflexion-' + clave);

                        if (! $campo.length) {
                            return;
                        }

                        var actual = $.trim(String($campo.val() || ''));
                        var cargado = self.reflexionCargada[clave] || '';

                        if (actual === '' || actual === cargado) {
                            $campo.val(respuestas[clave] || '');
                        }

                        self.reflexionCargada[clave] = $.trim(String($campo.val() || ''));
                    });

                    if (datos.reflection && datos.reflection.label) {
                        $('#reflexion-semanal-etiqueta').text(datos.reflection.label);
                    }
                })
                .fail(function () {
                    // Si la consulta falla, el formulario sigue funcionando:
                    // simplemente no precarga la meta de esa semana.
                });

            return this;
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
            // La cabecera arranca marcada o a medias según lo que ya estaba
            // cumplido, también en la vista de sólo lectura.
            this.sincronizarTodos();
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
           2 bis. Cumplimiento del horario (vista de sólo lectura)
           ================================================================== */

        /**
         * Enlaza el botón del final de la sección. Al pulsarlo se pide una
         * confirmación con bootbox; si se cancela no pasa nada y, si se
         * acepta, las casillas se guardan por AJAX contra el controlador del
         * diario. La respuesta se muestra en otra alerta: el éxito con el
         * mensaje del servidor o el error con su causa.
         */
        iniciarCumplimiento: function () {
            var self = this;

            $(this.opciones.selectorGuardarHorario)
                .off('click.pfCumplimiento')
                .on('click.pfCumplimiento', function (evento) {
                    evento.preventDefault();
                    self.confirmarCumplimiento($(this));
                });
        },

        /** Las franjas que hay ahora mismo en la tabla, con su estado. */
        franjasDelHorario: function () {
            var self = this;
            var franjas = [];

            $(this.opciones.selectorTabla).find('tr').not('[id="horario-vacio"]').each(function () {
                var $fila = $(this);
                var id = parseInt($fila.find('input[name$="[id]"]').val(), 10);

                if (! id) {
                    return;
                }

                franjas.push({
                    id: id,
                    is_done: $fila.find(self.opciones.selectorCheckFila).is(':checked') ? 1 : 0
                });
            });

            return franjas;
        },

        /** Cuenta lo que se va a guardar y pide la confirmación. */
        confirmarCumplimiento: function ($boton) {
            var self = this;
            var franjas = this.franjasDelHorario();

            if (! franjas.length) {
                P.Alerta.info('Este diario no tiene franjas en el horario para marcar.');

                return this;
            }

            var cumplidas = $.grep(franjas, function (franja) {
                return franja.is_done === 1;
            }).length;
            var sinCumplir = franjas.length - cumplidas;
            var total = franjas.length === 1 ? '1 franja' : franjas.length + ' franjas';
            var marcadas = cumplidas === 1
                ? '1 marcada como cumplida'
                : cumplidas + ' marcadas como cumplidas';
            var sinMarcar = sinCumplir === 1 ? '1 sin cumplir' : sinCumplir + ' sin cumplir';

            P.Alerta.confirmar({
                titulo: 'Guardar el cumplimiento del horario',
                mensaje: 'Se guardará el cumplimiento de ' + total + ': ' + marcadas +
                    ' y ' + sinMarcar + '. ¿Deseas continuar?',
                textoAceptar: 'Sí, guardar',
                textoCancelar: 'Cancelar',
                claseAceptar: 'btn btn-success',
                alAceptar: function () {
                    self.guardarCumplimiento($boton, franjas);
                }
            });

            return this;
        },

        /** Envía las franjas al controlador y muestra el resultado. */
        guardarCumplimiento: function ($boton, franjas) {
            var self = this;
            var texto = $boton.html();

            $boton.prop('disabled', true).html(
                '<i class="bi bi-hourglass-split" aria-hidden="true"></i> Guardando…'
            );

            var terminar = function () {
                $boton.prop('disabled', false).html(texto);
            };

            P.Ajax.peticion({
                url: $boton.attr('data-url'),
                tipo: 'PATCH',
                datos: { franjas: franjas },
                avisoError: false,
                alExito: function (respuesta) {
                    terminar();

                    var datos = respuesta && respuesta.data ? respuesta.data : {};
                    self.aplicarCumplimiento(datos.franjas);

                    P.Alerta.exito(
                        (respuesta && respuesta.message) ||
                            'Se guardó el cumplimiento del horario.',
                        'Cumplimiento guardado'
                    );
                },
                alError: function (xhr, mensaje) {
                    terminar();

                    P.Alerta.error(
                        mensaje || 'No se pudo guardar el cumplimiento del horario.',
                        'Ocurrió un problema'
                    );
                }
            });

            return this;
        },

        /**
         * Deja las casillas como las devolvió el servidor. Se evita disparar
         * el "change" de cada fila: basta con sincronizar la cabecera y
         * redibujar el gráfico una sola vez.
         */
        aplicarCumplimiento: function (franjas) {
            if (! $.isArray(franjas)) {
                return this;
            }

            var self = this;
            var hechos = {};
            var cambio = false;

            $.each(franjas, function (i, franja) {
                hechos[String(franja.id)] = !! franja.is_done;
            });

            $(this.opciones.selectorTabla).find('tr').not('[id="horario-vacio"]').each(function () {
                var $fila = $(this);
                var id = String($fila.find('input[name$="[id]"]').val() || '');

                if (! id || ! Object.prototype.hasOwnProperty.call(hechos, id)) {
                    return;
                }

                var $check = $fila.find(self.opciones.selectorCheckFila);

                if ($check.is(':checked') !== hechos[id]) {
                    $check.prop('checked', hechos[id]);
                    cambio = true;
                }
            });

            this.sincronizarTodos();

            if (cambio && P.Grafico) {
                P.Grafico.actualizar();
            }

            return this;
        },

        /* ==================================================================
           2 ter. Actividades diarias (vista de sólo lectura)
           ================================================================== */

        /**
         * Enlaza el botón del final de "Actividades diarias". Igual que el del
         * horario: confirma con bootbox y guarda por AJAX contra el diario.
         */
        iniciarCumplimientoActividades: function () {
            var self = this;

            $('#actividades-guardar')
                .off('click.pfActividades')
                .on('click.pfActividades', function (evento) {
                    evento.preventDefault();
                    self.confirmarActividades($(this));
                });
        },

        /** Las actividades visibles del día, con su estado y sus minutos. */
        actividadesDelDia: function () {
            var actividades = [];

            $('tr.tf-actividad').each(function () {
                var $fila = $(this);

                if ($fila.is(':hidden')) {
                    return;
                }

                var id = parseInt($fila.find('input[name$="[daily_activity_id]"]').val(), 10);

                if (! id) {
                    return;
                }

                var $check = $fila.find('.tf-actividad__check');
                var $minutos = $fila.find('input[name$="[minutes]"]');
                var minutos = null;

                if ($minutos.length) {
                    minutos = parseInt($minutos.val(), 10);
                    minutos = isNaN(minutos) || minutos < 0 ? 0 : minutos;
                }

                actividades.push({
                    daily_activity_id: id,
                    is_done: $check.is(':checked') ? 1 : 0,
                    minutes: minutos
                });
            });

            return actividades;
        },

        /** Cuenta lo que se va a guardar y pide la confirmación. */
        confirmarActividades: function ($boton) {
            var self = this;
            var actividades = this.actividadesDelDia();

            if (! actividades.length) {
                P.Alerta.info('Este diario no tiene actividades para marcar.');

                return this;
            }

            var hechas = $.grep(actividades, function (actividad) {
                return actividad.is_done === 1;
            }).length;
            var total = actividades.length;
            var texto = total === 1 ? '1 actividad' : total + ' actividades';
            var marcadas = hechas === 1 ? '1 marcada' : hechas + ' marcadas';

            P.Alerta.confirmar({
                titulo: 'Guardar las actividades del día',
                mensaje: 'Se guardará el estado de ' + texto + ': ' + marcadas +
                    ' como cumplidas y ' + (total - hechas) + ' sin cumplir. ¿Deseas continuar?',
                textoAceptar: 'Sí, guardar',
                textoCancelar: 'Cancelar',
                claseAceptar: 'btn btn-success',
                alAceptar: function () {
                    self.guardarActividades($boton, actividades);
                }
            });

            return this;
        },

        /** Envía las actividades al controlador y muestra el resultado. */
        guardarActividades: function ($boton, actividades) {
            var self = this;
            var texto = $boton.html();

            $boton.prop('disabled', true).html(
                '<i class="bi bi-hourglass-split" aria-hidden="true"></i> Guardando…'
            );

            var terminar = function () {
                $boton.prop('disabled', false).html(texto);
            };

            P.Ajax.peticion({
                url: $boton.attr('data-url'),
                tipo: 'PATCH',
                datos: { actividades: actividades },
                avisoError: false,
                alExito: function (respuesta) {
                    terminar();

                    var datos = respuesta && respuesta.data ? respuesta.data : {};
                    self.aplicarActividades(datos.activities);

                    P.Alerta.exito(
                        (respuesta && respuesta.message) || 'Se guardaron las actividades.',
                        'Actividades guardadas'
                    );
                },
                alError: function (xhr, mensaje) {
                    terminar();

                    P.Alerta.error(
                        mensaje || 'No se pudieron guardar las actividades.',
                        'Ocurrió un problema'
                    );
                }
            });

            return this;
        },

        /** Deja las casillas y los minutos como los devolvió el servidor. */
        aplicarActividades: function (actividades) {
            if (! $.isArray(actividades)) {
                return this;
            }

            var hechos = {};

            $.each(actividades, function (i, actividad) {
                hechos[String(actividad.daily_activity_id)] = actividad;
            });

            $('tr.tf-actividad').each(function () {
                var $fila = $(this);
                var id = String($fila.find('input[name$="[daily_activity_id]"]').val() || '');
                var datos = hechos[id];

                if (! datos) {
                    return;
                }

                $fila.find('.tf-actividad__check').prop('checked', !! datos.is_done);

                var $minutos = $fila.find('input[name$="[minutes]"]');

                if ($minutos.length) {
                    $minutos.val(datos.minutes === null ? '' : datos.minutes);
                }
            });

            this.sincronizarDetalles();

            return this;
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
           3 bis. Meta semanal: reflejo en el seguimiento personal
           ================================================================== */

        /** Mientras se escribe la meta, la sección personal muestra el texto. */
        iniciarMetaSemanal: function () {
            var self = this;

            // Lo que ya vino cargado del servidor cuenta como "cargado solo":
            // si se cambia de semana, se puede reemplazar sin perder nada.
            this.metaCargada = {
                goal: $.trim(String($('#weekly_goal_goal').val() || '')),
                why: $.trim(String($('#weekly_goal_why').val() || ''))
            };

            this.reflexionCargada = {};

            $('textarea[name^="weekly_reflection["]').each(function () {
                var coincidencia = String($(this).attr('name') || '').match(/\[([a-z_]+)\]/);

                if (coincidencia) {
                    self.reflexionCargada[coincidencia[1]] = $.trim(String($(this).val() || ''));
                }
            });

            // La meta registrada se muestra bloqueada; este botón la habilita
            // y marca el campo oculto que autoriza el cambio en el servidor.
            $('#meta-semanal-editar')
                .off('click.pfMetaEditar')
                .on('click.pfMetaEditar', function () {
                    $('#weekly_goal_goal, #weekly_goal_why').prop('readonly', false);
                    $('#weekly_goal_actualizar').val('1');
                    $(this).hide();

                    $('#meta-semanal-tag')
                        .text('Editando')
                        .removeClass('tf-obligatorio-tag--suave');

                    $('#meta-semanal-nota').text(
                        'Editando la meta de la semana: el cambio vale para todos sus días.'
                    );

                    $('#weekly_goal_goal').trigger('focus');
                });

            this.aplicarEstadoMeta($.trim(String($('#weekly_goal_goal').val() || '')) !== '');

            $('#weekly_goal_goal')
                .off('input.pfMeta')
                .on('input.pfMeta', function () {
                    self.reflejarMetaSemanal($(this).val());
                });
        },

        /**
         * Deja la sección de la meta en su estado: registrada (bloqueada, con
         * el botón para cambiarla) o pendiente (editable y obligatoria).
         */
        aplicarEstadoMeta: function (registrada) {
            var $goal = $('#weekly_goal_goal');
            var $why = $('#weekly_goal_why');
            var $boton = $('#meta-semanal-editar');
            var $tag = $('#meta-semanal-tag');
            var $etiqueta = $('#weekly_goal_goal_label');
            var $nota = $('#meta-semanal-nota');

            $('#weekly_goal_actualizar').val('0');

            if (this.soloLectura) {
                $goal.prop('readonly', true);
                $why.prop('readonly', true);

                return this;
            }

            $goal.prop('readonly', registrada);
            $why.prop('readonly', registrada);
            $etiqueta.toggleClass('tf-obligatorio', ! registrada);
            $boton.prop('hidden', ! registrada).toggle(registrada);

            if (registrada) {
                $tag.text('Registrada').addClass('tf-obligatorio-tag--suave');
                $nota.text(
                    'Ya está registrada para esta semana: se usa en todos sus días. '
                    + 'Si necesitas cambiarla, pulsa «Cambiar meta de la semana».'
                );
            } else {
                $tag.text('Obligatorio').removeClass('tf-obligatorio-tag--suave');
                $nota.text(
                    'Aún no hay meta para esta semana: escríbela y se compartirá con todos sus días.'
                );
            }

            return this;
        },

        reflejarMetaSemanal: function (texto) {
            texto = $.trim(String(texto || ''));

            if (texto === '') {
                $('#meta-personal-meta').text('La meta de la semana se muestra aquí en cuanto la escribas.');

                return;
            }

            $('#meta-personal-meta').html('Tu meta de la semana: <strong>' + P.Util.escapar(texto) + '</strong>');
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
            this.ultimaFechaMeta = null;
            this.reflejarMetaSemanal('');
            this.aplicarEstadoMeta($.trim(String($('#weekly_goal_goal').val() || '')) !== '');
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

            lista.push({
                campo: '#weekly_goal_goal',
                mensaje: 'Escribe la meta de esta semana: es un campo obligatorio.'
            });

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
