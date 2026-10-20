{{--
    ==========================================================================
    MODAL DE LA NOTIFICACIÓN
    --------------------------------------------------------------------------
    Se abre al tocar una fila del buzón. Al cerrarlo, public/js/
    notificaciones.js marca la notificación como leída y actualiza el globo de
    la campanita y la bandeja.

    El contenido lo llena el JavaScript con textContent (no innerHTML), así un
    título o un mensaje nunca puede inyectar HTML.
    ==========================================================================
--}}
<div class="modal fade tc-notificacion-modal" id="modalNotificacion" tabindex="-1"
     aria-labelledby="modalNotificacionTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content tc-notificacion">

            <div class="modal-header tc-notificacion__cabecera">
                <div class="tc-notificacion__encabezado">
                    <span class="tc-notificacion__tipo" data-modal-tipo>Notificación</span>
                    <h2 class="modal-title tc-notificacion__titulo" id="modalNotificacionTitulo"
                        data-modal-titulo>Cargando…</h2>
                </div>

                <button type="button" class="btn-close" data-bs-dismiss="modal"
                        aria-label="Cerrar la notificación"></button>
            </div>

            <div class="modal-body">
                <div class="tc-notificacion__meta">
                    <span class="tc-notificacion__chip" data-modal-prioridad>Prioridad</span>
                    <span class="tc-notificacion__dato-meta">
                        <i class="bi bi-calendar-event" aria-hidden="true"></i>
                        <span data-modal-fecha></span>
                    </span>
                </div>

                <p class="tc-notificacion__mensaje" data-modal-mensaje></p>

                <dl class="tc-notificacion__detalle" data-modal-detalle hidden></dl>

                <div class="tc-notificacion__plan" data-modal-plan-caja hidden>
                    <a class="tc-boton tc-boton--azul" data-modal-plan target="_blank" rel="noopener">
                        <i class="bi bi-journal-text" aria-hidden="true"></i>
                        Abrir el diario de ese día
                    </a>
                </div>
            </div>

            <div class="modal-footer tc-notificacion__pie">
                <button type="button" class="tc-boton tc-boton--contorno" data-modal-archivar>
                    <i class="bi bi-archive" aria-hidden="true"></i>
                    Archivar
                </button>

                <button type="button" class="tc-boton tc-boton--naranja" data-bs-dismiss="modal">
                    Cerrar y marcar como leída
                </button>
            </div>
        </div>
    </div>
</div>
