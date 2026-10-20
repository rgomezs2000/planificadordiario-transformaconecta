{{--
    Gráfico del día: línea de tiempo con las actividades del horario.

    Los datos viajan en un bloque JSON aparte con la misma forma que se usa al
    guardar (start_time, activity, is_done), así el gráfico y el servidor hablan
    del mismo objeto. El dibujo lo hace public/js/grafico.js y se rehace solo
    cada vez que se agrega, se quita o se cambia una franja.

    No lleva leyenda: al pasar el puntero por una barra se ve la actividad y su
    hora. Las actividades cumplidas se resaltan con una palomita; las barras
    claras son las que todavía no se marcaron.
--}}
<section class="tc-tarjeta tf-seccion tf-seccion--turquesa mb-3" data-tf-seccion="grafico">
    <h3 class="tf-titulo">
        <i class="bi bi-bar-chart-steps" aria-hidden="true"></i>
        Mi día en el tiempo
    </h3>

    <div class="tf-grafico" id="grafico-agenda"></div>

    <p class="tf-grafico__ayuda">
        @if ($soloLectura)
            Cada barra es una actividad, ubicada en su hora. Las cumplidas llevan una palomita;
            las claras quedaron sin cumplir.
        @else
            Se va dibujando solo, a medida que cargás las franjas del horario. Las cumplidas
            se resaltan con una palomita; las claras son las que todavía no marcaste.
        @endif
    </p>

    <script type="application/json" id="grafico-agenda-datos">@json($grafico)</script>
</section>
