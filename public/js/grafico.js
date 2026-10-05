/* ==========================================================================
   GRÁFICO · Mi día en el tiempo
   Programa de Desarrollo Personal "Transforma-Conecta"
   --------------------------------------------------------------------------
   Dibuja una línea de tiempo con las actividades del horario: cada una es una
   barra ubicada en su hora, con su propio color.

     · las actividades cumplidas se pintan nítidas;
     · las que todavía no se marcaron, claras;
     · donde dos actividades se pisan, ese pedazo se pinta transparente.

   No lleva leyenda: al pasar el puntero por una barra se muestra la actividad
   y su hora. Se rehace solo cada vez que se agrega, se quita o se cambia una
   franja del horario, sin ir al servidor: usa el mismo objeto JSON que se
   manda al guardar (start_time, activity, is_done).
   ========================================================================== */

window.Planificador = window.Planificador || {};

(function ($, P) {
    'use strict';

    P.Grafico = {

        opciones: {
            selector: '#grafico-agenda',
            selectorDatos: '#grafico-agenda-datos',
            selectorHorario: '#tabla-horario',
            selectorHora: 'input[type="time"]',
            selectorActividad: 'input[name$="[activity]"]',
            selectorHecho: 'input[name$="[is_done]"]'
        },

        /* Paleta institucional, igual que en el servidor. */
        paleta: ['#0080D0', '#F0600C', '#00AE9C', '#002060', '#009CE4', '#D90B0B', '#006C60', '#FC9000'],

        duracionUltima: 60,
        ancho: 1000,
        alto: 190,
        izquierda: 16,
        derecha: 984,
        arribaBarra: 42,
        altoBarra: 58,
        lineaEje: 144,
        yHora: 162,

        /** Arranca: dibuja con los datos que dejó el servidor y queda a la escucha. */
        iniciar: function () {
            var $lienzo = $(this.opciones.selector);

            if (! $lienzo.length) {
                return this;
            }

            this.$lienzo = $lienzo;
            this.dibujar(this.datosIniciales());
            this.escuchar();

            return this;
        },

        /** Los datos que dejó el servidor; si no hay, los arma del formulario. */
        datosIniciales: function () {
            var $datos = $(this.opciones.selectorDatos);

            if ($datos.length) {
                try {
                    var datos = JSON.parse($datos.text());

                    if (datos && datos.tramos) {
                        return datos;
                    }
                } catch (e) {
                    // Si el JSON viniera roto, se sigue con lo que hay en pantalla.
                }
            }

            return this.calcular(this.franjasDelFormulario());
        },

        /** Las franjas que hay ahora mismo en la tabla del horario. */
        franjasDelFormulario: function () {
            var self = this;
            var franjas = [];

            $(this.opciones.selectorHorario).find('tbody tr').each(function () {
                var $fila = $(this);
                var $hora = $fila.find(self.opciones.selectorHora);

                if (! $hora.length) {
                    return;   // la fila que dice "no hay franjas"
                }

                franjas.push({
                    start_time: $hora.val() || '',
                    activity: $fila.find(self.opciones.selectorActividad).val() || '',
                    is_done: $fila.find(self.opciones.selectorHecho).is(':checked')
                });
            });

            return franjas;
        },

        /**
         * Misma cuenta que hace el servidor: cada actividad dura hasta la
         * siguiente, la última una hora, y los empates se cruzan.
         */
        calcular: function (franjas) {
            var self = this;
            var items = [];

            $.each(franjas, function (indice, franja) {
                var inicio = self.aMinutos(franja.start_time);

                if (inicio === null) {
                    return;
                }

                items.push({
                    actividad: $.trim(String(franja.activity || '')),
                    inicio: inicio,
                    hecho: !! franja.is_done,
                    orden: indice
                });
            });

            items.sort(function (a, b) {
                return (a.inicio - b.inicio) || (a.orden - b.orden);
            });

            $.each(items, function (indice, item) {
                var siguiente = items[indice + 1] ? items[indice + 1].inicio : null;
                var fin = siguiente === null ? item.inicio + self.duracionUltima : siguiente;

                if (fin <= item.inicio) {
                    fin = item.inicio + self.duracionUltima;
                }

                item.fin = fin;
            });

            $.each(items, function (indice, item) {
                item.color = self.paleta[indice % self.paleta.length];
                item.hora_inicio = self.aHora(item.inicio);
                item.hora_fin = self.aHora(item.fin);
                item.cruce = self.seCruza(items, indice);
            });

            if (! items.length) {
                return { inicio: 7 * 60, fin: 19 * 60, tramos: [], piezas: [] };
            }

            var inicio = Math.floor(items[0].inicio / 60) * 60;
            var fin = Math.ceil(Math.max.apply(null, $.map(items, function (i) { return i.fin; })) / 60) * 60;

            if (fin - inicio < 120) {
                fin = inicio + 120;
            }

            return { inicio: inicio, fin: fin, tramos: items, piezas: self.piezas(items) };
        },

        seCruza: function (items, indice) {
            var mio = items[indice];
            var cruza = false;

            $.each(items, function (otroIndice, otro) {
                if (otroIndice !== indice && otro.inicio < mio.fin && mio.inicio < otro.fin) {
                    cruza = true;
                }
            });

            return cruza;
        },

        /** Parte los tramos donde empieza o termina otro, para el cruce. */
        piezas: function (tramos) {
            var cortes = [];

            $.each(tramos, function (i, t) {
                if ($.inArray(t.inicio, cortes) === -1) { cortes.push(t.inicio); }
                if ($.inArray(t.fin, cortes) === -1) { cortes.push(t.fin); }
            });

            cortes.sort(function (a, b) { return a - b; });

            var piezas = [];

            for (var i = 0; i < cortes.length - 1; i++) {
                var desde = cortes[i];
                var hasta = cortes[i + 1];
                var encima = [];

                $.each(tramos, function (indice, tramo) {
                    if (tramo.inicio <= desde && hasta <= tramo.fin) {
                        encima.push(indice);
                    }
                });

                $.each(encima, function (i2, indice) {
                    piezas.push({
                        tramo: indice,
                        inicio: desde,
                        fin: hasta,
                        cruce: encima.length > 1,
                        color: tramos[indice].color,
                        hecho: tramos[indice].hecho,
                        actividad: tramos[indice].actividad,
                        hora_inicio: tramos[indice].hora_inicio,
                        hora_fin: tramos[indice].hora_fin
                    });
                });
            }

            return piezas;
        },

        /* --- Dibujo ---------------------------------------------------- */

        dibujar: function (datos) {
            if (! this.$lienzo || ! this.$lienzo.length) {
                return this;
            }

            // El cuadro que sigue al puntero habla de barras que están por
            // desaparecer: se oculta antes de rehacer el dibujo, si no se
            // quedaría colgado cuando el horario queda vacío.
            this.callar();

            var svg = [];
            var rango = Math.max(1, datos.fin - datos.inicio);
            var anchoUtil = this.derecha - this.izquierda;
            var self = this;
            var x = function (minutos) {
                return self.izquierda + ((minutos - datos.inicio) / rango) * anchoUtil;
            };

            svg.push('<svg viewBox="0 0 ' + this.ancho + ' ' + this.alto + '" width="100%" ' +
                'role="img" aria-label="Línea de tiempo de las actividades del día" ' +
                'xmlns="http://www.w3.org/2000/svg">');

            // 1. Las horas.
            var paso = rango > 12 * 60 ? 120 : 60;

            for (var minuto = Math.ceil(datos.inicio / 60) * 60; minuto <= datos.fin; minuto += paso) {
                svg.push('<line x1="' + x(minuto) + '" y1="' + (this.arribaBarra - 14) + '" x2="' + x(minuto) +
                    '" y2="' + this.lineaEje + '" stroke="#CFD9E6" stroke-width="1"/>');
                svg.push('<text x="' + x(minuto) + '" y="' + this.yHora + '" text-anchor="middle" ' +
                    'font-size="11" fill="#6B7A90">' + this.aHora(minuto) + '</text>');
            }

            if (! datos.tramos.length) {
                svg.push('<text x="' + (this.ancho / 2) + '" y="' + (this.arribaBarra + 34) + '" ' +
                    'text-anchor="middle" font-size="13" fill="#6B7A90">' +
                    'Todavía no hay franjas de horario para graficar</text>');
                svg.push('</svg>');
                this.$lienzo.html(svg.join(''));

                return this;
            }

            // 2. El fondo: cada pedazo, transparente donde se cruzan.
            $.each(datos.piezas, function (i, pieza) {
                var opacidad = pieza.cruce ? 0.12 : (pieza.hecho ? 1 : 0.42);
                var x0 = x(pieza.inicio);
                var ancho = Math.max(1, x(pieza.fin) - x0);

                svg.push('<rect x="' + x0 + '" y="' + self.arribaBarra + '" width="' + ancho +
                    '" height="' + self.altoBarra + '" fill="' + pieza.color + '" fill-opacity="' + opacidad + '"/>');
            });

            // 3. El contorno de cada actividad y la zona que responde al puntero.
            //    Adentro de las barras no va texto: la actividad y la hora se
            //    ven en el cuadro que aparece al señalarlas.
            $.each(datos.tramos, function (indice, tramo) {
                var x0 = x(tramo.inicio);
                var x1 = x(tramo.fin);
                var anchoTramo = Math.max(1, x1 - x0);

                svg.push('<rect x="' + x0 + '" y="' + self.arribaBarra + '" width="' + anchoTramo +
                    '" height="' + self.altoBarra + '" fill="none" stroke="' + tramo.color + '" stroke-width="1"/>');

                svg.push('<rect class="tf-grafico__zona" x="' + x0 + '" y="' + self.arribaBarra + '" width="' +
                    anchoTramo + '" height="' + self.altoBarra + '" fill="transparent" ' +
                    'data-desde="' + self.escapar(tramo.hora_inicio) + '" ' +
                    'data-hasta="' + self.escapar(tramo.hora_fin) + '" ' +
                    'data-actividad="' + self.escapar(tramo.actividad || 'Actividad') + '"/>');
            });

            svg.push('</svg>');
            this.$lienzo.html(svg.join(''));

            return this;
        },

        /** Vuelve a dibujar con lo que hay en el formulario. */
        actualizar: function () {
            this.dibujar(this.calcular(this.franjasDelFormulario()));

            return this;
        },

        /** Queda atento a los cambios del horario. */
        escuchar: function () {
            var self = this;
            var $tabla = $(this.opciones.selectorHorario);

            if (! $tabla.length) {
                return this;
            }

            // Escribir una hora, una actividad o marcar la casilla.
            $tabla.on('input change', 'input', function () {
                self.actualizar();
            });

            // Agregar o quitar una franja: se redibuja apenas termina el cambio
            // que hace el formulario, por eso la pequeña espera.
            $(document).on('click', '#horario-agregar, .tf-horario__quitar', function () {
                window.setTimeout(function () {
                    self.actualizar();
                }, 0);
            });

            // El cuadro que muestra la franja horaria y la actividad.
            this.$lienzo
                .on('mouseenter focusin', '.tf-grafico__zona', function (evento) {
                    var $zona = $(this);

                    self.avisar($zona.attr('data-desde'), $zona.attr('data-hasta'),
                        $zona.attr('data-actividad'), evento);
                })
                .on('mouseleave focusout', '.tf-grafico__zona', function () {
                    self.callar();
                });

            return this;
        },

        avisar: function (desde, hasta, actividad, evento) {
            var $aviso = $('#grafico-aviso');

            if (! $aviso.length) {
                $aviso = $('<div id="grafico-aviso" class="tf-grafico__aviso" role="status"></div>').appendTo('body');
            }

            // En un cuadro de dos renglones: arriba la franja horaria y abajo la
            // actividad. No va todo en una línea.
            $aviso.html('<strong>' + this.escapar(desde + ' - ' + hasta) + '</strong>' +
                '<span>' + this.escapar(actividad) + '</span>');

            // Va fijo a la ventana: se ubica donde está el puntero.
            var x = evento && evento.clientX ? evento.clientX : window.innerWidth / 2;
            var y = evento && evento.clientY ? evento.clientY : 80;

            $aviso.css({ left: x + 'px', top: y + 'px' }).addClass('tf-grafico__aviso--visible');
        },

        callar: function () {
            $('#grafico-aviso').removeClass('tf-grafico__aviso--visible');
        },

        /* --- Ayudas ---------------------------------------------------- */

        aMinutos: function (hora) {
            var partes = /^\s*(\d{1,2}):(\d{2})/.exec(String(hora || ''));

            if (! partes) {
                return null;
            }

            var horas = parseInt(partes[1], 10);
            var minutos = parseInt(partes[2], 10);

            return (horas > 23 || minutos > 59) ? null : (horas * 60) + minutos;
        },

        aHora: function (minutos) {
            var horas = Math.floor(minutos / 60) % 24;
            var resto = minutos % 60;

            return (horas < 10 ? '0' : '') + horas + ':' + (resto < 10 ? '0' : '') + resto;
        },

        /** Blanco o azul oscuro, según lo que se lea mejor sobre ese color. */
        colorDeTexto: function (color) {
            var hex = String(color).replace('#', '');
            var luz = (0.299 * parseInt(hex.substr(0, 2), 16)) +
                (0.587 * parseInt(hex.substr(2, 2), 16)) +
                (0.114 * parseInt(hex.substr(4, 2), 16));

            return luz < 150 ? '#FFFFFF' : '#10233F';
        },

        escapar: function (texto) {
            return String(texto === null || texto === undefined ? '' : texto)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }
    };

})(jQuery, window.Planificador);
