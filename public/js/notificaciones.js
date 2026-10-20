/* ==========================================================================
   NOTIFICACIONES · Campanita, buzón y avisos en pantalla
   Programa de Desarrollo Personal "Transforma-Conecta"
   --------------------------------------------------------------------------
   Este archivo es el tiempo real del módulo:

     · consulta el contador cada N segundos (data-intervalo) y actualiza el
       globo de la campanita y el resumen;
     · cuando aparece una notificación nueva, la avisa con una tarjeta en
       pantalla, un sonido corto (Web Audio, sin archivos) y, si se permite,
       un aviso del escritorio del navegador;
     · llena la tabla del panel y la de la bandeja completa con DataTables;
     · maneja los checkbox, las acciones por lote y el modal: al cerrarlo, la
       notificación se marca como leída.

   La persona ve un solo buzón. El sistema guarda el historial y mantiene el
   espejo interno para el push; la pantalla no separa las notificaciones por
   motor.

   Uso: se arranca solo al cargar la página; la configuración (rutas, sonido,
   intervalo) se lee de los data-* de #campanaNotificaciones.
   ========================================================================== */

window.Planificador = window.Planificador || {};

(function ($, P) {
    'use strict';

    var CLAVE_VISTAS = 'tc-notificaciones-vistas';
    var CLAVE_SONIDO = 'tc-notificaciones-sonido';
    var CLAVE_ESCRITORIO = 'tc-notificaciones-escritorio';

    /** Cómo se llaman los campos de "meta" en el modal. */
    var ETIQUETAS_META = {
        ranura: 'Ranura',
        objetivo_tipo: 'Tipo de objetivo',
        descripcion: 'Objetivo',
        hora: 'Hora',
        actividad: 'Actividad',
        minutos_de_retraso: 'Minutos de retraso',
        margen_minutos: 'Margen de gracia',
        fecha: 'Fecha',
        hora_de_aviso: 'Hora del aviso'
    };

    /** Escapa un texto antes de meterlo en HTML. */
    function escapar(texto) {
        return P.Util ? P.Util.escapar(texto) : String(texto === null || texto === undefined ? '' : texto);
    }

    /** Recorta un texto largo sin cortar palabras a la mitad. */
    function recortar(texto, largo) {
        texto = String(texto || '');

        return texto.length > largo ? texto.slice(0, largo - 1).trim() + '…' : texto;
    }

    /** Reemplaza __UUID__ en una plantilla de dirección. */
    function urlDe(plantilla, uuid) {
        return String(plantilla || '').replace('__UUID__', encodeURIComponent(uuid));
    }

    function existe(id) {
        return document.getElementById(id) !== null;
    }

    P.Notificaciones = {

        /** Se cambian al llamar a iniciar() si hiciera falta. */
        opciones: {
            selectorCampana: '#campanaNotificaciones',
            selectorPanel: '#buzonNotificaciones',
            selectorModal: '#modalNotificacion'
        },

        /** Configuración leída de los data-* de la campanita. */
        config: {},

        iniciado: false,

        /** Todo lo que cambia mientras la pantalla está abierta. */
        estado: {
            cargado: false,
            noLeidas: 0,
            timer: null,
            tablas: {},
            modal: null,
            actual: null,
            vistas: {},
            filtros: { estado: 'todas', tipo: '', q: '' },
            esperaFiltro: null,
            esperaMensaje: null,
            filtrosEnlazados: false,
            sonidoActivo: true,
            escritorioActivo: false,
            audio: null
        },

        /* ==================================================================
           Arranque
           ================================================================== */

        iniciar: function (opciones) {
            if (this.iniciado) {
                return this;
            }

            if (opciones) {
                $.extend(true, this.opciones, opciones);
            }

            var $campana = $(this.opciones.selectorCampana);

            if (! $campana.length) {
                return this;
            }

            if (! window.bootstrap) {
                return this;
            }

            this.$campana = $campana;
            this.$panel = $(this.opciones.selectorPanel);
            this.$modal = $(this.opciones.selectorModal);
            this.config = this.leerConfiguracion($campana);
            this.estado.vistas = this.leerVistas();

            this.prepararPreferencias();
            this.prepararModal();
            this.prepararPanel();
            this.prepararAcciones();
            this.iniciarPagina();

            this.estado.modal = existe('modalNotificacion')
                ? window.bootstrap.Modal.getOrCreateInstance(document.getElementById('modalNotificacion'))
                : null;

            this.iniciado = true;

            this.programar();

            // La primera consulta espera un momento: así no compite con las
            // peticiones que la propia página dispara al cargar y la cookie de
            // sesión ya está asentada cuando llega la campanita.
            var self = this;
            window.setTimeout(function () {
                self.sondear(true);
            }, 1200);

            return this;
        },

        /** Lee las direcciones y los valores del bloque de la campanita. */
        leerConfiguracion: function ($campana) {
            function dato(nombre) {
                return $campana.attr('data-' + nombre);
            }

            return {
                contador: dato('url-contador'),
                tabla: dato('url-tabla'),
                mostrar: dato('url-mostrar'),
                leida: dato('url-leida'),
                descartar: dato('url-descartar'),
                loteLeidas: dato('url-lote-leidas'),
                loteDescartar: dato('url-lote-descartar'),
                sincronizar: dato('url-sincronizar'),
                panel: dato('url-panel'),
                intervalo: parseInt(dato('intervalo'), 10) || 15000,
                sonido: String(dato('sonido')) === '1',
                escritorio: String(dato('escritorio')) === '1',
                icono: dato('icono')
            };
        },

        /* ==================================================================
           Sondeo del contador (tiempo real)
           ================================================================== */

        /** Programa la próxima consulta. */
        programar: function () {
            var self = this;

            window.clearInterval(this.estado.timer);
            this.estado.timer = window.setInterval(function () {
                if (document.visibilityState === 'visible') {
                    self.sondear(false);
                }
            }, Math.max(5000, this.config.intervalo));

            $(document).off('visibilitychange.tcNotificaciones')
                .on('visibilitychange.tcNotificaciones', function () {
                    if (document.visibilityState === 'visible') {
                        self.sondear(false);
                    }
                });

            return this;
        },

        /** Una consulta al contador. En la primera no se avisa nada. */
        sondear: function (inicial) {
            var self = this;

            $.ajax({
                url: this.config.contador,
                type: 'GET',
                dataType: 'json',
                cache: false,
                timeout: 20000
            }).done(function (respuesta) {
                if (respuesta && respuesta.ok && respuesta.data) {
                    self.aplicarContador(respuesta.data, !!inicial);
                }
            });
        },

        /** Refresca la campanita y el resumen. */
        aplicarContador: function (datos, inicial) {
            var vistas = this.estado.vistas;
            var nuevas = [];

            $.each(datos.ultimas || [], function (indice, notificacion) {
                if (! notificacion || ! notificacion.uuid) {
                    return;
                }

                var conocida = !! vistas[notificacion.uuid];
                vistas[notificacion.uuid] = true;

                if (! conocida && ! notificacion.leida) {
                    nuevas.push(notificacion);
                }
            });

            this.guardarVistas();
            this.actualizarCampana(Number(datos.no_leidas || 0), inicial ? [] : nuevas);
            this.actualizarResumen(datos);

            this.estado.cargado = true;

            if (! inicial && nuevas.length > 0) {
                this.reproducirSonido();
                this.mostrarAviso(nuevas);
                this.mostrarEscritorio(nuevas);
            }
        },

        /* ==================================================================
           Campanita
           ================================================================== */

        actualizarCampana: function (noLeidas, nuevas) {
            this.estado.noLeidas = noLeidas;

            var $contador = this.$campana.find('[data-campana-contador]');
            var $punto = this.$campana.find('[data-campana-punto]');
            var $boton = this.$campana.find('#campana-boton');

            if (noLeidas > 0) {
                $contador.text(noLeidas > 99 ? '99+' : String(noLeidas)).prop('hidden', false);
                $punto.prop('hidden', false);
                $boton.attr('aria-label', 'Abrir el buzón de notificaciones: ' + noLeidas + ' sin leer');
            } else {
                $contador.text('0').prop('hidden', true);
                $punto.prop('hidden', true);
                $boton.attr('aria-label', 'Abrir el buzón de notificaciones');
            }

            if (nuevas && nuevas.length > 0) {
                var self = this;

                this.$campana.removeClass('tc-campana--nueva');
                // Fuerza a que la animación vuelva a empezar.
                void this.$campana[0].offsetWidth;
                this.$campana.addClass('tc-campana--nueva');

                window.setTimeout(function () {
                    self.$campana.removeClass('tc-campana--nueva');
                }, 1900);
            }
        },

        actualizarResumen: function (datos) {
            var sinLeer = Number(datos.no_leidas || 0);
            var total = Number(datos.total || 0);
            var texto;

            if (sinLeer === 0) {
                texto = total === 0 ? 'El buzón está vacío.' : 'No tienes notificaciones sin leer.';
            } else {
                texto = sinLeer + (sinLeer === 1 ? ' notificación sin leer' : ' notificaciones sin leer');
            }

            if (total > 0) {
                texto += ' · ' + total + ' en la bandeja';
            }

            $('[data-buzon-resumen]').text(texto);
        },

        aplicarConteo: function (conteo) {
            if (! conteo) {
                return;
            }

            this.actualizarCampana(Number(conteo.no_leidas || 0), []);
            this.actualizarResumen(conteo);
        },

        /* ==================================================================
           Avisos en pantalla, sonido y escritorio
           ================================================================== */

        /** Una tarjeta por vez; si hay más, lo dice en "+N más". */
        mostrarAviso: function (nuevas) {
            var self = this;
            var principal = nuevas[0];
            var adicionales = nuevas.length - 1;
            var prioridad = principal.prioridad || 'media';

            if (! principal) {
                return;
            }

            var $aviso = $('<div class="tc-aviso" role="alert"></div>')
                .addClass('tc-aviso--' + prioridad);

            var $icono = $('<span class="tc-aviso__icono" aria-hidden="true"></span>')
                .append($('<i class="bi bi-bell-fill"></i>'));

            var $cuerpo = $('<div class="tc-aviso__cuerpo"></div>');
            $cuerpo.append($('<span class="tc-aviso__tipo"></span>').text(principal.tipo_etiqueta || 'Notificación'));
            $cuerpo.append($('<span class="tc-aviso__titulo"></span>').text(principal.titulo || 'Nueva notificación'));
            $cuerpo.append($('<p class="tc-aviso__texto"></p>').text(recortar(principal.mensaje, 150)));

            if (adicionales > 0) {
                $cuerpo.append($('<p class="tc-aviso__mas"></p>')
                    .text('+' + adicionales + (adicionales === 1 ? ' notificación más' : ' notificaciones más') + ' en el buzón'));
            }

            var $cerrar = $('<button type="button" class="tc-aviso__cerrar" data-aviso-cerrar aria-label="Cerrar el aviso">')
                .append($('<i class="bi bi-x-lg"></i>'));

            $aviso.append($icono, $cuerpo, $cerrar);

            $aviso.on('click', function (evento) {
                if ($(evento.target).closest('[data-aviso-cerrar]').length) {
                    return;
                }

                self.abrir(principal.uuid);
                self.cerrarAviso($aviso);
            });

            $cerrar.on('click', function (evento) {
                evento.stopPropagation();
                self.cerrarAviso($aviso);
            });

            $('#tc-avisos-notificacion').append($aviso);

            window.requestAnimationFrame(function () {
                $aviso.addClass('tc-aviso--visible');
            });

            window.setTimeout(function () {
                self.cerrarAviso($aviso);
            }, 9000);
        },

        cerrarAviso: function ($aviso) {
            if (! $aviso || ! $aviso.length) {
                return;
            }

            $aviso.removeClass('tc-aviso--visible');

            window.setTimeout(function () {
                $aviso.remove();
            }, 250);
        },

        /** Prepara el audio y los interruptores de sonido y escritorio. */
        prepararPreferencias: function () {
            var self = this;

            var sonido = window.localStorage.getItem(CLAVE_SONIDO);
            this.estado.sonidoActivo = sonido === null ? this.config.sonido : sonido === '1';

            var escritorio = window.localStorage.getItem(CLAVE_ESCRITORIO);
            this.estado.escritorioActivo = escritorio === null ? this.config.escritorio : escritorio === '1';

            this.pintarBotonSonido();
            this.pintarBotonEscritorio();

            $(document).on('click.tcNotificaciones', '[data-buzon-sonido]', function () {
                self.alternarSonido();
            });

            $(document).on('click.tcNotificaciones', '[data-buzon-escritorio]', function () {
                self.alternarEscritorio();
            });

            // Los navegadores no dejan sonar audio hasta que la persona
            // interactúa: se desbloquea en el primer clic o tecla.
            $(document).one('pointerdown.tcNotificaciones keydown.tcNotificaciones', function () {
                self.desbloquearAudio();
            });
        },

        pintarBotonSonido: function () {
            var $boton = $('[data-buzon-sonido]');
            var activo = this.estado.sonidoActivo;

            $boton.attr('aria-pressed', activo ? 'true' : 'false');
            $boton.find('[data-icono-sonido]')
                .attr('class', (activo ? 'bi bi-volume-up-fill' : 'bi bi-volume-mute-fill') + ' ');
            $boton.find('[data-texto-sonido]').text(activo ? 'Sonido' : 'Silencio');
            $boton.attr('title', activo
                ? 'Silenciar el sonido de los avisos'
                : 'Activar el sonido de los avisos');
        },

        pintarBotonEscritorio: function () {
            var $boton = $('[data-buzon-escritorio]');
            var activo = this.estado.escritorioActivo;

            $boton.attr('aria-pressed', activo ? 'true' : 'false');
            $boton.find('[data-icono-escritorio]')
                .attr('class', (activo ? 'bi bi-display-fill' : 'bi bi-display') + ' ');
            $boton.find('[data-texto-escritorio]').text(activo ? 'Escritorio' : 'Escritorio');
            $boton.attr('title', activo
                ? 'Desactivar los avisos del escritorio'
                : 'Permitir avisos del escritorio del navegador');
        },

        alternarSonido: function () {
            this.estado.sonidoActivo = ! this.estado.sonidoActivo;
            window.localStorage.setItem(CLAVE_SONIDO, this.estado.sonidoActivo ? '1' : '0');
            this.pintarBotonSonido();

            if (this.estado.sonidoActivo) {
                this.desbloquearAudio();
                this.reproducirSonido();
                this.mensaje('Sonido de avisos activado.');
            } else {
                this.mensaje('Sonido de avisos silenciado.');
            }
        },

        alternarEscritorio: function () {
            var self = this;

            if (! ('Notification' in window)) {
                P.Alerta.info('Este navegador no admite avisos del escritorio.');
                return;
            }

            if (window.Notification.permission === 'denied') {
                P.Alerta.aviso('Los avisos del escritorio están bloqueados para este sitio. '
                    + 'Se pueden habilitar desde la configuración del navegador.');
                return;
            }

            if (window.Notification.permission === 'granted') {
                this.estado.escritorioActivo = ! this.estado.escritorioActivo;
                window.localStorage.setItem(CLAVE_ESCRITORIO, this.estado.escritorioActivo ? '1' : '0');
                this.pintarBotonEscritorio();
                this.mensaje(this.estado.escritorioActivo
                    ? 'Avisos del escritorio activados.'
                    : 'Avisos del escritorio desactivados.');

                return;
            }

            window.Notification.requestPermission().then(function (permiso) {
                if (permiso !== 'granted') {
                    self.mensaje('No se autorizaron los avisos del escritorio.');
                    return;
                }

                self.estado.escritorioActivo = true;
                window.localStorage.setItem(CLAVE_ESCRITORIO, '1');
                self.pintarBotonEscritorio();
                self.mensaje('Avisos del escritorio activados.');
            }).catch(function () {
                self.mensaje('No se pudieron activar los avisos del escritorio.');
            });
        },

        desbloquearAudio: function () {
            try {
                var Contexto = window.AudioContext || window.webkitAudioContext;

                if (! Contexto) {
                    return;
                }

                if (! this.estado.audio) {
                    this.estado.audio = new Contexto();
                }

                if (this.estado.audio.state === 'suspended') {
                    this.estado.audio.resume();
                }
            } catch (error) {
                // Sin audio no se rompe nada: el aviso visual sigue funcionando.
            }
        },

        /** Un timbre corto de dos notas, sintetizado (no requiere archivos). */
        reproducirSonido: function () {
            if (! this.estado.sonidoActivo) {
                return;
            }

            this.desbloquearAudio();

            var contexto = this.estado.audio;

            if (! contexto || contexto.state !== 'running') {
                return;
            }

            try {
                var inicio = contexto.currentTime;
                var notas = [
                    { frecuencia: 880.00, retraso: 0, duracion: 0.18 },
                    { frecuencia: 1174.66, retraso: 0.16, duracion: 0.30 }
                ];

                $.each(notas, function (indice, nota) {
                    var oscilador = contexto.createOscillator();
                    var ganancia = contexto.createGain();
                    var desde = inicio + nota.retraso;

                    oscilador.type = 'sine';
                    oscilador.frequency.setValueAtTime(nota.frecuencia, desde);

                    ganancia.gain.setValueAtTime(0.0001, desde);
                    ganancia.gain.exponentialRampToValueAtTime(0.09, desde + 0.02);
                    ganancia.gain.exponentialRampToValueAtTime(0.0001, desde + nota.duracion);

                    oscilador.connect(ganancia);
                    ganancia.connect(contexto.destination);
                    oscilador.start(desde);
                    oscilador.stop(desde + nota.duracion + 0.02);
                });
            } catch (error) {
                // Igual que arriba: el aviso visual es lo importante.
            }
        },

        mostrarEscritorio: function (nuevas) {
            if (! this.estado.escritorioActivo
                || ! ('Notification' in window)
                || window.Notification.permission !== 'granted'
                || ! nuevas.length) {
                return;
            }

            var principal = nuevas[0];
            var self = this;

            try {
                var notificacion = new window.Notification(principal.titulo || 'Nueva notificación', {
                    body: principal.mensaje + (nuevas.length > 1 ? ' (+' + (nuevas.length - 1) + ' más)' : ''),
                    icon: this.config.icono || undefined,
                    tag: principal.uuid,
                    requireInteraction: false
                });

                notificacion.onclick = function () {
                    window.focus();
                    self.abrir(principal.uuid);
                    notificacion.close();
                };

                window.setTimeout(function () {
                    notificacion.close();
                }, 12000);
            } catch (error) {
                // Algunos navegadores (móviles) exigen service worker.
            }
        },

        /* ==================================================================
           Preferencias guardadas
           ================================================================== */

        leerVistas: function () {
            var vistas = {};

            try {
                $.each(JSON.parse(window.sessionStorage.getItem(CLAVE_VISTAS) || '[]'), function (indice, uuid) {
                    vistas[uuid] = true;
                });
            } catch (error) {
                vistas = {};
            }

            return vistas;
        },

        guardarVistas: function () {
            try {
                var uuids = Object.keys(this.estado.vistas);

                // No hace falta guardar un historial eterno: con las últimas
                // 200 alcanza para no repetir avisos mientras la pestaña vive.
                window.sessionStorage.setItem(CLAVE_VISTAS, JSON.stringify(uuids.slice(-200)));
            } catch (error) {
                // sessionStorage puede estar bloqueado; se sigue sin él.
            }
        },

        /* ==================================================================
           Panel y tablas
           ================================================================== */

        prepararPanel: function () {
            var self = this;

            this.$panel.on('show.bs.offcanvas.tcNotificaciones', function () {
                self.sondear(false);
            });

            this.$panel.on('shown.bs.offcanvas.tcNotificaciones', function () {
                self.cargarPanel();
            });
        },

        cargarPanel: function () {
            this.prepararTablaPanel();
            this.recargarTabla('tabla-notificaciones-panel');
            this.sondear(false);
        },

        prepararTablaPanel: function () {
            var id = 'tabla-notificaciones-panel';

            if (! existe(id)) {
                return;
            }

            this.crearTabla(id, { pagina: false });
            this.recargarTabla(id);
        },

        /**
         * Crea una tabla del buzón. DataTables no se lleva bien con contenedores
         * ocultos, así que la del panel se crea cuando el offcanvas se muestra
         * y la de la página al cargar el documento.
         */
        crearTabla: function (id, opciones) {
            if (! existe(id) || ! $.fn.DataTable) {
                return null;
            }

            if (this.estado.tablas[id]) {
                return this.estado.tablas[id];
            }

            var self = this;
            var $tabla = $('#' + id);
            var pagina = !!(opciones && opciones.pagina);

            var tabla = P.Tabla.crear($tabla, {
                pageLength: pagina ? 10 : 5,
                lengthChange: pagina,
                searching: false,
                info: pagina,
                pagingType: pagina ? 'full_numbers' : 'simple',
                autoWidth: false,
                order: [[2, 'desc']],
                ajax: {
                    url: $tabla.attr('data-url'),
                    cache: false,
                    data: function (datos) {
                        return pagina ? $.extend({}, datos, self.filtrosPagina()) : datos;
                    },
                    dataSrc: function (json) {
                        return self.recibirTabla(json);
                    }
                },
                columns: [
                    {
                        data: 'uuid',
                        orderable: false,
                        className: 'tc-buzon__col-check',
                        render: function (uuid, tipo, fila) {
                            return tipo === 'display' ? self.celdaCheckbox(fila) : (uuid || '');
                        }
                    },
                    {
                        data: 'titulo',
                        orderable: true,
                        render: function (titulo, tipo, fila) {
                            return tipo === 'display' ? self.celdaResumen(fila) : (titulo || '');
                        }
                    },
                    {
                        data: 'fecha_hora_iso',
                        className: 'tc-buzon__col-fecha',
                        render: function (fecha, tipo, fila) {
                            return tipo === 'display' ? self.celdaFecha(fila) : (fecha || '');
                        }
                    }
                ],
                drawCallback: function () {
                    self.actualizarSeleccion(id);
                }
            });

            this.estado.tablas[id] = { tabla: tabla, pagina: pagina };
            this.enlazarTabla(id);

            return this.estado.tablas[id];
        },

        /** Procesa la respuesta del servidor para una tabla. */
        recibirTabla: function (json) {
            if (! json || json.ok === false) {
                this.mensaje((json && json.message) || 'No se pudo cargar el buzón de notificaciones.');

                return [];
            }

            var datos = json.data || {};

            if (datos.conteo) {
                this.aplicarConteo(datos.conteo);
            }

            return datos.notificaciones || [];
        },

        celdaCheckbox: function (fila) {
            return '<input type="checkbox" class="form-check-input" data-buzon-check'
                + ' value="' + escapar(fila.uuid) + '"'
                + ' aria-label="Seleccionar: ' + escapar(fila.titulo) + '">';
        },

        celdaResumen: function (fila) {
            var chip = fila.leida
                ? '<span class="tc-buzon__chip tc-buzon__chip--leida">Leída</span>'
                : '<span class="tc-buzon__chip tc-buzon__chip--nueva">Nueva</span>';

            return '<div class="tc-buzon__fila">'
                + '<div class="tc-buzon__linea">'
                + '<span class="tc-buzon__tipo tc-buzon__tipo--' + escapar(fila.tipo) + '">'
                + escapar(fila.tipo_etiqueta || fila.tipo) + '</span>'
                + '<span class="tc-buzon__titulo">' + escapar(fila.titulo) + '</span>'
                + chip
                + '</div>'
                + '<span class="tc-buzon__resumen">' + escapar(recortar(fila.mensaje, 120)) + '</span>'
                + '</div>';
        },

        celdaFecha: function (fila) {
            return '<span class="tc-buzon__fecha-dia">' + escapar(fila.fecha_corta || fila.fecha) + '</span>'
                + '<span class="tc-buzon__fecha-hora">' + escapar(fila.hora) + '</span>';
        },

        enlazarTabla: function (id) {
            var self = this;
            var $tabla = $('#' + id);

            $tabla.off('.tcNotificacionesFila');

            $tabla.on('click.tcNotificacionesFila', 'tbody tr', function (evento) {
                if ($(evento.target).closest('input, button, a, label').length) {
                    return;
                }

                var registro = self.estado.tablas[id];

                if (! registro) {
                    return;
                }

                var fila = registro.tabla.row(this).data();

                if (fila) {
                    self.abrir(fila.uuid);
                }
            });

            $tabla.on('change.tcNotificacionesFila', '[data-buzon-check]', function () {
                self.actualizarSeleccion(id);
            });

            $tabla.on('change.tcNotificacionesFila', 'thead [data-buzon-todos]', function () {
                $tabla.find('tbody [data-buzon-check]').prop('checked', $(this).prop('checked'));
                self.actualizarSeleccion(id);
            });
        },

        recargarTabla: function (id) {
            var registro = this.estado.tablas[id];

            if (registro && registro.tabla && $.fn.DataTable.isDataTable('#' + id)) {
                registro.tabla.ajax.reload(null, false);
            }
        },

        recargarTablas: function () {
            var self = this;

            $.each(this.estado.tablas, function (id) {
                self.recargarTabla(id);
            });
        },

        recargarPaginas: function () {
            var self = this;

            $.each(this.estado.tablas, function (id, registro) {
                if (registro.pagina) {
                    registro.tabla.ajax.reload();
                }
            });
        },

        /* ==================================================================
           Selección y acciones por lote
           ================================================================== */

        uuidsDe: function (id) {
            var uuids = [];

            $('#' + id + ' tbody [data-buzon-check]:checked').each(function () {
                uuids.push($(this).val());
            });

            return uuids;
        },

        actualizarSeleccion: function (id) {
            if (! existe(id)) {
                return;
            }

            var uuids = this.uuidsDe(id);
            var total = $('#' + id + ' tbody [data-buzon-check]').length;
            var $caja = $('#' + id).closest('[data-buzon-caja]');

            $caja.find('[data-buzon-seleccion]').text(
                uuids.length === 0
                    ? '0 seleccionadas'
                    : uuids.length + (uuids.length === 1 ? ' seleccionada' : ' seleccionadas')
            );

            $caja.find('[data-buzon-marcar], [data-buzon-archivar]')
                .prop('disabled', uuids.length === 0);

            $('#' + id + ' thead [data-buzon-todos]')
                .prop('checked', total > 0 && uuids.length === total)
                .prop('indeterminate', uuids.length > 0 && uuids.length < total);
        },

        marcarSeleccion: function (id) {
            var uuids = this.uuidsDe(id);

            if (! uuids.length) {
                return;
            }

            var self = this;

            $.ajax({
                url: this.config.loteLeidas,
                type: 'PATCH',
                dataType: 'json',
                data: { uuids: uuids }
            }).done(function (respuesta) {
                if (! respuesta || respuesta.ok === false) {
                    self.mensaje((respuesta && respuesta.message) || 'No se pudieron marcar las notificaciones.');

                    return;
                }

                self.mensaje(respuesta.message);
                aplicarRespuesta(respuesta, self);
                self.recargarTablas();
            }).fail(function (xhr) {
                P.Alerta.error(P.Ajax.mensajeDeError(xhr));
            });
        },

        archivarSeleccion: function (id) {
            var uuids = this.uuidsDe(id);

            if (! uuids.length) {
                return;
            }

            var self = this;

            P.Alerta.confirmar({
                titulo: 'Archivar notificaciones',
                mensaje: '¿Sacar ' + uuids.length + (uuids.length === 1 ? ' notificación' : ' notificaciones')
                    + ' de la bandeja? El mismo recordatorio no se vuelve a generar.',
                textoAceptar: 'Sí, archivar',
                claseAceptar: 'btn btn-warning',
                alAceptar: function () {
                    $.ajax({
                        url: self.config.loteDescartar,
                        type: 'DELETE',
                        dataType: 'json',
                        data: { uuids: uuids }
                    }).done(function (respuesta) {
                        if (! respuesta || respuesta.ok === false) {
                            self.mensaje((respuesta && respuesta.message) || 'No se pudieron archivar.');

                            return;
                        }

                        self.mensaje(respuesta.message);
                        aplicarRespuesta(respuesta, self);
                        self.recargarTablas();
                    }).fail(function (xhr) {
                        P.Alerta.error(P.Ajax.mensajeDeError(xhr));
                    });
                }
            });
        },

        /* ==================================================================
           Modal
           ================================================================== */

        prepararModal: function () {
            var self = this;

            if (! existe('modalNotificacion')) {
                return;
            }

            this.$modal.on('hidden.bs.modal.tcNotificaciones', function () {
                self.alCerrarModal();
            });

            this.$modal.on('click.tcNotificaciones', '[data-modal-archivar]', function () {
                var actual = self.estado.actual;

                if (! actual) {
                    return;
                }

                P.Alerta.confirmar({
                    titulo: 'Archivar notificación',
                    mensaje: '¿Sacar esta notificación de la bandeja? No se vuelve a generar el mismo recordatorio.',
                    textoAceptar: 'Sí, archivar',
                    claseAceptar: 'btn btn-warning',
                    alAceptar: function () {
                        self.archivar(actual.uuid);
                    }
                });
            });
        },

        abrir: function (uuid) {
            var self = this;

            $.ajax({
                url: urlDe(this.config.mostrar, uuid),
                type: 'GET',
                dataType: 'json',
                cache: false
            }).done(function (respuesta) {
                if (! respuesta || respuesta.ok === false) {
                    P.Alerta.error((respuesta && respuesta.message) || 'No se pudo abrir la notificación.');

                    return;
                }

                self.pintarModal(respuesta.data.notificacion, respuesta.data.plan_url);
            }).fail(function (xhr) {
                P.Alerta.error(P.Ajax.mensajeDeError(xhr));
            });
        },

        pintarModal: function (notificacion, planUrl) {
            if (! notificacion) {
                return;
            }

            this.estado.actual = {
                uuid: notificacion.uuid,
                leida: !! notificacion.leida,
                archivada: false
            };

            var $modal = this.$modal;

            $modal.find('[data-modal-tipo]').text(notificacion.tipo_etiqueta || 'Notificación');
            $modal.find('[data-modal-titulo]').text(notificacion.titulo || 'Notificación');
            $modal.find('[data-modal-mensaje]').text(notificacion.mensaje || '');

            var prioridad = notificacion.prioridad || 'media';

            $modal.find('[data-modal-prioridad]')
                .text('Prioridad ' + (notificacion.prioridad_etiqueta || prioridad))
                .removeClass('tc-notificacion__chip--alta tc-notificacion__chip--media tc-notificacion__chip--baja')
                .addClass('tc-notificacion__chip--' + prioridad);

            var fecha = [notificacion.fecha_label, notificacion.hora].filter(Boolean).join(' · ');
            $modal.find('[data-modal-fecha]').text(fecha);

            this.pintarDetalle(notificacion.meta || {});

            var $cajaPlan = $modal.find('[data-modal-plan-caja]');
            var $plan = $modal.find('[data-modal-plan]');

            if (planUrl) {
                $plan.attr('href', planUrl);
                $cajaPlan.prop('hidden', false);
            } else {
                $plan.removeAttr('href');
                $cajaPlan.prop('hidden', true);
            }

            if (this.estado.modal) {
                this.estado.modal.show();
            }
        },

        pintarDetalle: function (meta) {
            var $detalle = this.$modal.find('[data-modal-detalle]');
            var orden = ['ranura', 'objetivo_tipo', 'descripcion', 'hora', 'actividad',
                'minutos_de_retraso', 'margen_minutos', 'hora_de_aviso'];
            var usados = {};

            $detalle.empty();

            function agregar(clave, valor) {
                var etiqueta = ETIQUETAS_META[clave] || clave.replace(/_/g, ' ');

                if ($.isArray(valor)) {
                    valor = valor.join(', ');
                }

                var $dato = $('<div class="tc-notificacion__dato"></div>');
                $dato.append($('<dt></dt>').text(etiqueta));
                $dato.append($('<dd></dd>').text(String(valor)));
                $detalle.append($dato);
            }

            $.each(orden, function (indice, clave) {
                var valor = meta[clave];

                if (valor !== undefined && valor !== null && valor !== '') {
                    agregar(clave, valor);
                    usados[clave] = true;
                }
            });

            $.each(meta, function (clave, valor) {
                if (! usados[clave] && clave !== 'fecha' && valor !== null && valor !== '') {
                    agregar(clave, valor);
                }
            });

            $detalle.prop('hidden', $detalle.children().length === 0);
        },

        /** Al cerrar el modal, si nadie la marcó, se marca como leída. */
        alCerrarModal: function () {
            var actual = this.estado.actual;

            this.estado.actual = null;

            if (! actual || actual.leida || actual.archivada) {
                return;
            }

            this.marcarLeidaSilenciosa(actual.uuid);
        },

        marcarLeidaSilenciosa: function (uuid) {
            var self = this;

            $.ajax({
                url: urlDe(this.config.leida, uuid),
                type: 'PATCH',
                dataType: 'json'
            }).done(function (respuesta) {
                if (respuesta && respuesta.ok) {
                    aplicarRespuesta(respuesta, self);
                    self.recargarTablas();
                }
            });
        },

        archivar: function (uuid) {
            var self = this;

            $.ajax({
                url: urlDe(this.config.descartar, uuid),
                type: 'DELETE',
                dataType: 'json'
            }).done(function (respuesta) {
                if (! respuesta || respuesta.ok === false) {
                    P.Alerta.error((respuesta && respuesta.message) || 'No se pudo archivar la notificación.');

                    return;
                }

                if (self.estado.actual) {
                    self.estado.actual.archivada = true;
                }

                self.mensaje(respuesta.message);
                aplicarRespuesta(respuesta, self);
                self.recargarTablas();

                if (self.estado.modal) {
                    self.estado.modal.hide();
                }
            }).fail(function (xhr) {
                P.Alerta.error(P.Ajax.mensajeDeError(xhr));
            });
        },

        /* ==================================================================
           Acciones generales
           ================================================================== */

        prepararAcciones: function () {
            var self = this;

            $(document).on('click.tcNotificaciones', '[data-buzon-marcar]', function () {
                self.marcarSeleccion($(this).attr('data-para'));
            });

            $(document).on('click.tcNotificaciones', '[data-buzon-archivar]', function () {
                self.archivarSeleccion($(this).attr('data-para'));
            });

            $(document).on('click.tcNotificaciones', '[data-buzon-sincronizar]', function () {
                self.sincronizar($(this));
            });
        },

        sincronizar: function ($boton) {
            var self = this;

            $boton.prop('disabled', true);

            $.ajax({
                url: this.config.sincronizar,
                type: 'POST',
                dataType: 'json'
            }).done(function (respuesta) {
                if (! respuesta || respuesta.ok === false) {
                    self.mensaje((respuesta && respuesta.message) || 'No se pudo sincronizar.');

                    return;
                }

                self.mensaje(respuesta.message);

                if (respuesta.data) {
                    if (respuesta.data.conteo) {
                        self.aplicarConteo(respuesta.data.conteo);
                    }
                }

                self.recargarTablas();
                self.sondear(false);
            }).fail(function (xhr) {
                P.Alerta.error(P.Ajax.mensajeDeError(xhr));
            }).always(function () {
                $boton.prop('disabled', false);
            });
        },

        /* ==================================================================
           Página completa
           ================================================================== */

        iniciarPagina: function () {
            var self = this;

            // La tabla de la página espera un instante por el mismo motivo
            // que la primera consulta del contador.
            window.setTimeout(function () {
                self.crearTabla('tabla-notificaciones-pagina', { pagina: true });
            }, 350);

            if (! $('[data-buzon-filtros]').length || this.estado.filtrosEnlazados) {
                return;
            }

            this.estado.filtrosEnlazados = true;

            $(document).on('input.tcNotificaciones change.tcNotificaciones', '[data-buzon-filtro]', function () {
                var clave = $(this).attr('data-buzon-filtro');

                self.estado.filtros[clave] = $.trim($(this).val() || '');
                window.clearTimeout(self.estado.esperaFiltro);

                self.estado.esperaFiltro = window.setTimeout(function () {
                    self.recargarPaginas();
                }, clave === 'q' ? 350 : 0);
            });

            $(document).on('click.tcNotificaciones', '[data-buzon-limpiar-filtros]', function () {
                $('[data-buzon-filtro]').each(function () {
                    if ($(this).is('select')) {
                        $(this).val($(this).attr('data-buzon-filtro') === 'estado' ? 'todas' : '');
                    } else {
                        $(this).val('');
                    }
                });

                self.estado.filtros = { estado: 'todas', tipo: '', q: '' };
                self.recargarPaginas();
            });
        },

        filtrosPagina: function () {
            return {
                estado: this.estado.filtros.estado || 'todas',
                tipo: this.estado.filtros.tipo || '',
                q: this.estado.filtros.q || ''
            };
        },

        /* ==================================================================
           Mensajes breves del panel
           ================================================================== */

        mensaje: function (texto) {
            var $mensajes = $('[data-buzon-mensaje]');

            window.clearTimeout(this.estado.esperaMensaje);

            $mensajes.each(function () {
                $(this).text(texto || '').prop('hidden', ! texto);
            });

            if (texto) {
                this.estado.esperaMensaje = window.setTimeout(function () {
                    $mensajes.prop('hidden', true).text('');
                }, 7000);
            }
        }
    };

    /**
     * Aplica la respuesta estándar del servidor { conteo } a la campanita.
     * Se usa desde acciones sueltas y por lote.
     */
    function aplicarRespuesta(respuesta, modulo) {
        if (! respuesta || ! respuesta.ok) {
            return;
        }

        var datos = respuesta.data || {};

        if (datos.conteo) {
            modulo.aplicarConteo(datos.conteo);
        }
    }

    // Se arranca solo. Va después de app.js, que ya dejó el CSRF en ajaxSetup
    // y definió Planificador.Util, Planificador.Ajax y Planificador.Tabla.
    $(function () {
        if (P.Notificaciones) {
            P.Notificaciones.iniciar();
        }
    });
})(jQuery, window.Planificador);
