{{--
    ==========================================================================
    PANEL DEL BUZÓN · se despliega de derecha a izquierda
    --------------------------------------------------------------------------
    Un solo buzón y una sola lista. Las notificaciones no se separan por motor:
    MySQL guarda el historial y Firestore recibe el espejo interno para el
    push, pero la persona ve siempre la misma bandeja.

    Al tocar una fila, la notificación se abre en el modal de
    notificaciones/_modal.blade.php; al cerrarlo se marca como leída.
    ==========================================================================
--}}
<div class="offcanvas offcanvas-end tc-buzon" tabindex="-1" id="buzonNotificaciones"
     aria-labelledby="buzonTitulo">

    <div class="tc-buzon__cabecera">
        <div class="tc-buzon__identidad">
            <p class="tc-buzon__sobre">Mi planificador</p>
            <h2 class="tc-buzon__titulo-panel" id="buzonTitulo">Notificaciones</h2>
            <p class="tc-buzon__fuente-texto">Tus recordatorios del diario, en un solo lugar.</p>
        </div>

        <button type="button" class="tc-menu__cerrar" data-bs-dismiss="offcanvas"
                aria-label="Cerrar el buzón de notificaciones">
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
    </div>

    <div class="tc-buzon__cuerpo">
        <p class="tc-buzon__resumen" data-buzon-resumen>Sin novedades por ahora.</p>

        <div data-buzon-caja="buzon">
            <div class="tc-buzon__acciones">
                <span class="tc-buzon__seleccion" data-buzon-seleccion>0 seleccionadas</span>

                <button type="button" class="tc-buzon__accion" data-buzon-marcar
                        data-para="tabla-notificaciones-panel" disabled>
                    <i class="bi bi-check2-all" aria-hidden="true"></i>
                    Marcar leídas
                </button>

                <button type="button" class="tc-buzon__accion" data-buzon-archivar
                        data-para="tabla-notificaciones-panel" disabled>
                    <i class="bi bi-archive" aria-hidden="true"></i>
                    Archivar
                </button>
            </div>

            @include('notificaciones._tabla', [
                'id' => 'tabla-notificaciones-panel',
                'url' => route('notificaciones.tabla'),
                'compacta' => true,
            ])
        </div>
    </div>

    <div class="tc-buzon__pie">
        <div class="tc-buzon__preferencias">
            <button type="button" class="tc-buzon__preferencia" data-buzon-sonido
                    aria-pressed="true" title="Activar o silenciar el sonido de los avisos">
                <i class="bi bi-volume-up-fill" aria-hidden="true" data-icono-sonido></i>
                <span data-texto-sonido>Sonido</span>
            </button>

            <button type="button" class="tc-buzon__preferencia" data-buzon-escritorio
                    aria-pressed="false" title="Permitir avisos del escritorio del navegador">
                <i class="bi bi-display" aria-hidden="true" data-icono-escritorio></i>
                <span data-texto-escritorio>Escritorio</span>
            </button>
        </div>

        <div class="tc-buzon__pie-acciones">
            <button type="button" class="tc-buzon__boton-pie" data-buzon-sincronizar>
                <i class="bi bi-arrow-repeat" aria-hidden="true"></i>
                Revisar ahora
            </button>

            <a class="tc-buzon__boton-pie tc-buzon__boton-pie--contorno"
               href="{{ route('notificaciones.index') }}">
                <i class="bi bi-inbox" aria-hidden="true"></i>
                Bandeja completa
            </a>
        </div>

        <p class="tc-buzon__mensaje" data-buzon-mensaje role="status" hidden></p>
    </div>
</div>
