{{--
    Modal para decidir la marca de agua antes de generar el resumen.

    Igual que el del diario: si se marca la casilla, el PDF sale con la marca
    SPECIMEN (documento de muestra); si no, sale real. La dirección se arma en
    el navegador con los filtros que están aplicados en el buscador, así el
    resumen cubre exactamente lo que se está viendo en la tabla.
--}}
<div class="modal fade" id="modalResumen" tabindex="-1"
     aria-labelledby="modalResumenTitulo" aria-hidden="true"
     data-url-resumen="{{ route('diario.resumen') }}">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="modalResumenTitulo">
                    <i class="bi bi-file-earmark-bar-graph" aria-hidden="true"></i>
                    Generar el resumen de desempeño
                </h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body">
                <p class="mb-3">
                    Se generará un PDF con el análisis de los diarios
                    <strong>según los filtros que tengas aplicados</strong> en el buscador:
                    el desempeño, qué se sostuvo y qué queda por mejorar, las metas alcanzadas,
                    la relación con la energía, el impacto de la procrastinación y la evolución
                    del período, con sus gráficos.
                </p>

                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="1" id="resumen-marca">
                    <label class="form-check-label" for="resumen-marca">
                        Incluir marca de agua <strong>SPECIMEN</strong> (sólo para pruebas)
                    </label>
                </div>

                <p class="tf-ayuda mt-2 mb-0">
                    Si no se marca, el resumen sale limpio, sin la marca.
                </p>
            </div>

            <div class="modal-footer">
                <button type="button" class="tc-boton tc-boton--contorno" data-bs-dismiss="modal">
                    Cancelar
                </button>
                <button type="button" class="tc-boton tc-boton--turquesa" id="resumen-generar">
                    <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i>
                    Generar PDF
                </button>
            </div>
        </div>
    </div>
</div>
