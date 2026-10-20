{{--
    ==========================================================================
    TABLA DEL BUZÓN · listado de resúmenes con título y fecha
    --------------------------------------------------------------------------
    La usan el panel de la campanita y la bandeja completa. DataTables la llena
    por AJAX; la primera columna es el checkbox de selección y el título lleva
    adentro el tipo, el resumen y el estado.

    Variables: $id, $url y $compacta.
    ==========================================================================
--}}
@php
    $compacta = $compacta ?? false;
@endphp

<table class="table tc-buzon__tabla align-middle mb-0" id="{{ $id }}"
       data-url="{{ $url }}" data-compacta="{{ $compacta ? '1' : '0' }}"
       aria-label="Notificaciones del buzón">
    <thead>
        <tr>
            <th scope="col" class="tc-buzon__col-check">
                <input type="checkbox" class="form-check-input" data-buzon-todos
                       aria-label="Seleccionar todas las notificaciones de esta página">
            </th>
            <th scope="col">Notificación</th>
            <th scope="col" class="tc-buzon__col-fecha">Fecha</th>
        </tr>
    </thead>
    <tbody></tbody>
</table>
