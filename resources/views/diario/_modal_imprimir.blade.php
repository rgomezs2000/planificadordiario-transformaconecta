{{--
    Modal para sacar los documentos del diario: el PDF y su versión en imagen.

    Se incluye tanto en el formulario (modo ver) como en el listado.

    Las direcciones se pasan como plantillas con __ID__, que el JavaScript
    sustituye por el id del diario elegido. Cada botón del pie declara su
    formato en data-formato ("pdf" o "imagen") y su dirección en data-url, así
    el JavaScript no necesita saber qué botón es cuál: uno genera el PDF y el
    otro la imagen.

    La casilla de la marca de agua vale para los dos formatos: el JPG se saca
    del mismo PDF, así que la imagen lleva la marca cuando el PDF la lleva. Con
    la casilla marcada y sin marcar son dos archivos distintos, guardados por
    separado en el almacenamiento.
--}}
<div class="modal fade" id="modalImprimir" tabindex="-1"
     aria-labelledby="modalImprimirTitulo" aria-hidden="true"
     data-url-detalle="{{ route('diario.detail', ['dailyPlan' => '__ID__']) }}">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="modalImprimirTitulo">
                    <i class="bi bi-printer" aria-hidden="true"></i>
                    Documentos del diario
                </h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body">
                <p class="mb-3">
                    Elige el formato: el <strong>PDF</strong> para imprimir o la
                    <strong>imagen</strong> para compartir. Los dos se guardan en el servidor, así que
                    la próxima vez se entregan al instante, sin volver a generarlos.
                </p>

                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="1" id="imprimir-marca">
                    <label class="form-check-label" for="imprimir-marca">
                        Incluir marca de agua <strong>SPECIMEN</strong> (sólo para pruebas)
                    </label>
                </div>

                <p class="tf-ayuda mt-2 mb-0">
                    Si no se marca, el PDF y su imagen se generan limpios, sin la marca.
                </p>
            </div>

            <div class="modal-footer">
                <button type="button" class="tc-boton tc-boton--contorno" data-bs-dismiss="modal">
                    Cancelar
                </button>

                <button type="button" class="tc-boton tc-boton--naranja"
                        data-formato="pdf"
                        data-url="{{ route('diario.print', ['dailyPlan' => '__ID__']) }}">
                    <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i>
                    Generar PDF
                </button>

                {{-- El JPG se saca del PDF: si el PDF de una página ya está
                     guardado, la imagen sale de ahí; si no, se convierte. --}}
                <button type="button" class="tc-boton tc-boton--turquesa"
                        data-formato="imagen"
                        data-url="{{ route('diario.image', ['dailyPlan' => '__ID__']) }}">
                    <i class="bi bi-file-earmark-image" aria-hidden="true"></i>
                    Generar imagen
                </button>
            </div>
        </div>
    </div>
</div>
