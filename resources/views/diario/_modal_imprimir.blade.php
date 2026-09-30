{{--
    Modal para decidir la marca de agua antes de generar el PDF.
    Se incluye tanto en el formulario (modo ver) como en el listado.

    Las direcciones se pasan como plantillas con __ID__, que el JavaScript
    sustituye por el id del diario elegido.
--}}
<div class="modal fade" id="modalImprimir" tabindex="-1"
     aria-labelledby="modalImprimirTitulo" aria-hidden="true"
     data-url-detalle="{{ route('diario.detail', ['dailyPlan' => '__ID__']) }}"
     data-url-imprimir="{{ route('diario.print', ['dailyPlan' => '__ID__']) }}">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="modalImprimirTitulo">
                    <i class="bi bi-printer" aria-hidden="true"></i>
                    Imprimir el diario
                </h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body">
                <p class="mb-3">Se generará el PDF del diario seleccionado.</p>

                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="1" id="imprimir-marca">
                    <label class="form-check-label" for="imprimir-marca">
                        Incluir marca de agua <strong>SPECIMEN</strong> (sólo para pruebas)
                    </label>
                </div>

                <p class="tf-ayuda mt-2 mb-0">
                    Si no se marca, el PDF se genera limpio, sin la marca.
                </p>
            </div>

            <div class="modal-footer">
                <button type="button" class="tc-boton tc-boton--contorno" data-bs-dismiss="modal">
                    Cancelar
                </button>
                <button type="button" class="tc-boton tc-boton--naranja" id="imprimir-generar">
                    <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i>
                    Generar PDF
                </button>
            </div>
        </div>
    </div>
</div>
