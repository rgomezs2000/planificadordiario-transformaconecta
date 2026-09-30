/* ==========================================================================
   MENÚ · Botón hamburguesa y menú lateral
   Programa de Desarrollo Personal "Transforma-Conecta"
   --------------------------------------------------------------------------
   Usa el componente Offcanvas de Bootstrap 5 y le añade el comportamiento
   propio: cerrar al elegir una opción, marcar la opción activa y devolver el
   foco al botón hamburguesa cuando el menú se cierra.

   Uso:
       Planificador.Menu.iniciar();
   ========================================================================== */

window.Planificador = window.Planificador || {};

(function ($, P) {
    'use strict';

    P.Menu = {

        opciones: {
            selectorMenu: '#menuLateral',
            selectorBoton: '.tc-hamburguesa',
            selectorEnlaces: '.tc-menu__enlace'
        },

        /** Instancia de Bootstrap Offcanvas. */
        instancia: null,

        /** Prepara el menú lateral y sus eventos. */
        iniciar: function (opciones) {
            if (opciones) {
                $.extend(this.opciones, opciones);
            }

            var elemento = document.querySelector(this.opciones.selectorMenu);

            if (!elemento || !window.bootstrap) {
                return this;
            }

            this.instancia = window.bootstrap.Offcanvas.getOrCreateInstance(elemento);
            this.marcarActivo();

            // Al elegir una opción el menú se cierra solo.
            $(document).off('click.pfMenu', this.opciones.selectorEnlaces)
                .on('click.pfMenu', this.opciones.selectorEnlaces, $.proxy(function () {
                    this.cerrar();
                }, this));

            // Al ocultarse, el foco vuelve al botón hamburguesa.
            $(elemento).off('hidden.bs.offcanvas.pfMenu')
                .on('hidden.bs.offcanvas.pfMenu', $.proxy(function () {
                    var boton = document.querySelector(this.opciones.selectorBoton);

                    if (boton) {
                        boton.focus();
                    }
                }, this));

            // Si la ventana pasa a escritorio con el menú abierto, se cierra.
            $(window).off('resize.pfMenu').on('resize.pfMenu', $.proxy(function () {
                if (this.estaAbierto() && window.innerWidth >= 992) {
                    this.cerrar();
                }
            }, this));

            return this;
        },

        abrir: function () {
            if (this.instancia) {
                this.instancia.show();
            }

            return this;
        },

        cerrar: function () {
            if (this.instancia) {
                this.instancia.hide();
            }

            return this;
        },

        alternar: function () {
            return this.estaAbierto() ? this.cerrar() : this.abrir();
        },

        estaAbierto: function () {
            var elemento = document.querySelector(this.opciones.selectorMenu);

            return elemento ? elemento.classList.contains('show') : false;
        },

        /**
         * Marca la opción que corresponde a la página actual.
         *
         * Los enlaces que genera Laravel son absolutos
         * (http://host/carpeta/diario), así que hay que quedarse sólo con la
         * ruta para poder compararla con la del navegador.
         */
        marcarActivo: function () {
            var rutaActual = window.location.pathname.replace(/\/+$/, '');
            var origen = window.location.origin;

            $(this.opciones.selectorEnlaces).each(function () {
                var $enlace = $(this);

                var destino = ($enlace.attr('href') || '').split('?')[0].split('#')[0];

                // Se le quita el dominio para quedarnos con la ruta.
                if (origen && destino.indexOf(origen) === 0) {
                    destino = destino.slice(origen.length);
                }

                destino = destino.replace(/\/+$/, '');

                if (destino !== '' && destino === rutaActual) {
                    $enlace.addClass('activo').attr('aria-current', 'page');
                } else {
                    $enlace.removeClass('activo').removeAttr('aria-current');
                }
            });

            return this;
        }
    };
})(jQuery, window.Planificador);
