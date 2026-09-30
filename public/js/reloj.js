/* ==========================================================================
   RELOJ · Fecha y hora dinámicas
   Programa de Desarrollo Personal "Transforma-Conecta"
   --------------------------------------------------------------------------
   Construye la fecha y la hora en español dentro de la cabecera, sin depender
   del idioma del navegador ni de ninguna librería de fechas externa.

   Uso:
       Planificador.Reloj.iniciar();                 // busca #reloj-fecha y #reloj-hora
       Planificador.Reloj.iniciar({ conSegundos: false });
       Planificador.Reloj.detener();
   ========================================================================== */

window.Planificador = window.Planificador || {};

(function ($, P) {
    'use strict';

    var DIAS = [
        'domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'
    ];

    var DIAS_CORTOS = [
        'dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'
    ];

    var MESES = [
        'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
        'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'
    ];

    var MESES_CORTOS = [
        'ene', 'feb', 'mar', 'abr', 'may', 'jun',
        'jul', 'ago', 'sep', 'oct', 'nov', 'dic'
    ];

    /** Añade el cero a la izquierda cuando hace falta. */
    function dosDigitos(valor) {
        return valor < 10 ? '0' + valor : String(valor);
    }

    /** Convierte "miércoles" en "Miércoles". */
    function capitalizar(texto) {
        return texto.charAt(0).toUpperCase() + texto.slice(1);
    }

    P.Reloj = {

        /** Valores por defecto; se pueden cambiar al llamar a iniciar(). */
        opciones: {
            selectorFecha: '#reloj-fecha',
            selectorHora: '#reloj-hora',
            conSegundos: true,
            intervalo: 1000,
            capitalizarDia: true
        },

        /** Identificador del setInterval en curso. */
        temporizador: null,

        /** "miércoles 30 de septiembre de 2026" */
        fechaLarga: function (fecha) {
            var texto = DIAS[fecha.getDay()] + ' ' + fecha.getDate() + ' de ' +
                MESES[fecha.getMonth()] + ' de ' + fecha.getFullYear();

            return this.opciones.capitalizarDia ? capitalizar(texto) : texto;
        },

        /** "mié 30 sep 2026" */
        fechaCorta: function (fecha) {
            return DIAS_CORTOS[fecha.getDay()] + ' ' + fecha.getDate() + ' ' +
                MESES_CORTOS[fecha.getMonth()] + ' ' + fecha.getFullYear();
        },

        /** "30/09/2026" */
        fechaNumerica: function (fecha) {
            return dosDigitos(fecha.getDate()) + '/' +
                dosDigitos(fecha.getMonth() + 1) + '/' + fecha.getFullYear();
        },

        /** "14:35:07" o "14:35" según conSegundos. */
        hora: function (fecha, conSegundos) {
            var texto = dosDigitos(fecha.getHours()) + ':' + dosDigitos(fecha.getMinutes());

            if (conSegundos !== false) {
                texto += ':' + dosDigitos(fecha.getSeconds());
            }

            return texto;
        },

        /** Escribe la fecha y la hora actuales en los elementos indicados. */
        pintar: function () {
            var ahora = new Date();
            var $fecha = $(this.opciones.selectorFecha);
            var $hora = $(this.opciones.selectorHora);

            if ($fecha.length) {
                $fecha.text(this.fechaLarga(ahora));
                $fecha.attr('datetime', ahora.toISOString());
            }

            if ($hora.length) {
                $hora.text(this.hora(ahora, this.opciones.conSegundos));
                $hora.attr('datetime', ahora.toISOString());
            }
        },

        /** Pinta de inmediato y luego refresca sola. */
        iniciar: function (opciones) {
            if (opciones) {
                $.extend(this.opciones, opciones);
            }

            this.detener();
            this.pintar();
            this.temporizador = window.setInterval(
                $.proxy(this.pintar, this),
                this.opciones.intervalo
            );

            return this;
        },

        /** Detiene el refresco automático. */
        detener: function () {
            if (this.temporizador !== null) {
                window.clearInterval(this.temporizador);
                this.temporizador = null;
            }

            return this;
        }
    };
})(jQuery, window.Planificador);
