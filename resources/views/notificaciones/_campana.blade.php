{{--
    ==========================================================================
    CAMPANITA · Notificaciones en la barra de título
    --------------------------------------------------------------------------
    Va después de la fecha y la hora. El botón abre el panel de la derecha
    (#buzonNotificaciones, en notificaciones/_panel.blade.php).

    Este bloque es también la configuración del módulo: public/js/
    notificaciones.js lee las direcciones y los valores de los data-* y no
    tiene ninguna ruta escrita a mano.
    ==========================================================================
--}}
@if (config('notificaciones.habilitadas', true))
    <div class="tc-campana" id="campanaNotificaciones"
         data-url-contador="{{ route('notificaciones.contador') }}"
         data-url-tabla="{{ route('notificaciones.tabla') }}"
         data-url-mostrar="{{ route('notificaciones.mostrar', ['notificacion' => '__UUID__']) }}"
         data-url-leida="{{ route('notificaciones.leida', ['notificacion' => '__UUID__']) }}"
         data-url-descartar="{{ route('notificaciones.descartar', ['notificacion' => '__UUID__']) }}"
         data-url-lote-leidas="{{ route('notificaciones.lote.leidas') }}"
         data-url-lote-descartar="{{ route('notificaciones.lote.descartar') }}"
         data-url-sincronizar="{{ route('notificaciones.sincronizar') }}"
         data-url-panel="{{ route('notificaciones.index') }}"
         data-intervalo="{{ (int) config('notificaciones.intervalo_consulta_ms', 10000) }}"
         data-sonido="{{ config('notificaciones.sonido_activo', true) ? '1' : '0' }}"
         data-escritorio="{{ config('notificaciones.escritorio_activo', false) ? '1' : '0' }}"
         data-icono="{{ asset('favicon.ico') }}">

        <button type="button" class="tc-campana__boton" id="campana-boton"
                data-bs-toggle="offcanvas" data-bs-target="#buzonNotificaciones"
                aria-controls="buzonNotificaciones" aria-expanded="false"
                aria-label="Abrir el buzón de notificaciones"
                title="Notificaciones">
            <i class="bi bi-bell" aria-hidden="true"></i>
            <span class="tc-campana__contador" data-campana-contador hidden>0</span>
            <span class="tc-campana__punto" data-campana-punto hidden aria-hidden="true"></span>
        </button>
    </div>
@endif
