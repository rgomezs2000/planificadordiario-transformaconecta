{{--
    Resumen de desempeño en PDF.

    Se arma con lo que devuelve el motor de análisis: los números, los textos
    (que ya vienen redactados) y los gráficos, que llegan dibujados como
    imágenes porque el PDF no ejecuta JavaScript.

    Va día por día: primero la lectura de cada diario y después los cruces del
    alcance elegido. El tono es descriptivo, sobre los propios registros, sin
    diagnóstico ni valoración personal.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Resumen de desempeño</title>
    <style>
        @page { margin: 26px 30px 42px 30px; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 8.5px;
            color: #22303F;
            line-height: 1.45;
        }

        table { width: 100%; border-collapse: collapse; }

        .cabecera td { vertical-align: middle; }
        .marca { width: 128px; }
        .marca .t1 { font-size: 14px; font-weight: bold; color: #F0600C; letter-spacing: 0.5px; }
        .marca .t2 { font-size: 14px; font-weight: bold; color: #00AE9C; margin-top: -2px; }
        .centro { text-align: center; }
        .programa { font-size: 6.6px; font-weight: bold; color: #00AE9C; letter-spacing: 0.4px; }
        .titulo { font-size: 19px; font-weight: bold; color: #002060; }
        .subtitulo { font-size: 8px; color: #0080D0; letter-spacing: 1px; }
        .sello { width: 150px; text-align: right; font-size: 7px; color: #6B7A90; }

        .banda {
            margin: 6px 0 10px;
            padding: 5px 0;
            background: #00AE9C;
            color: #FFFFFF;
            font-size: 8.5px;
            font-weight: bold;
            letter-spacing: 1.5px;
            text-align: center;
        }

        .seccion { margin-bottom: 9px; page-break-inside: avoid; }
        .titulo-seccion {
            padding: 4px 8px;
            background: #002060;
            color: #FFFFFF;
            font-size: 8px;
            font-weight: bold;
            letter-spacing: 0.8px;
        }
        .titulo-seccion.turquesa { background: #00AE9C; }
        .titulo-seccion.naranja { background: #F0600C; }
        .titulo-seccion.rojo { background: #D90B0B; }
        .titulo-seccion.azul { background: #0080D0; }
        .cuerpo { padding: 6px 8px; border: 1px solid #CFD9E6; border-top: 0; }

        .alcance { font-size: 9.5px; font-weight: bold; color: #002060; }
        .chico { font-size: 7.4px; color: #6B7A90; }

        /* Los cuatro números grandes del período. */
        .numeros td {
            width: 25%;
            padding: 7px 4px;
            border: 1px solid #CFD9E6;
            text-align: center;
        }
        .numeros .valor { font-size: 17px; font-weight: bold; color: #002060; }
        .numeros .rotulo { font-size: 6.8px; color: #6B7A90; letter-spacing: 0.4px; }

        /* Día por día. */
        .dias th {
            padding: 4px 5px;
            background: #002060;
            color: #FFFFFF;
            font-size: 6.8px;
            letter-spacing: 0.4px;
            text-align: center;
        }
        .dias td {
            padding: 4px 5px;
            border-bottom: 1px solid #E4EAF2;
            font-size: 7.6px;
            text-align: center;
        }
        .dias td.izq { text-align: left; }
        .dias .fuerte { font-weight: bold; color: #002060; }

        .texto p { margin: 0 0 3px; }
        .texto p:last-child { margin-bottom: 0; }

        .grafico { margin-top: 4px; }
        .grafico img { width: 100%; }

        .nota {
            margin-top: 8px;
            padding: 6px 8px;
            border-left: 3px solid #00AE9C;
            background: #F4F7FB;
            font-size: 7.2px;
            color: #3B4A5A;
        }

        .avisos { margin-top: 6px; font-size: 7.2px; color: #6B7A90; }
        .avisos li { margin-bottom: 2px; }

        .pie {
            margin-top: 12px;
            padding-top: 6px;
            border-top: 2px solid #F0600C;
            font-size: 6.8px;
            color: #6B7A90;
            text-align: center;
        }
        .pie strong { color: #002060; }

        .aviso-muestra {
            margin-top: 8px;
            padding: 5px 0;
            border: 1px dashed #D90B0B;
            color: #D90B0B;
            font-size: 8px;
            font-weight: bold;
            letter-spacing: 1px;
            text-align: center;
        }
    </style>
</head>
<body>

{{-- ============================ ENCABEZADO ============================ --}}
<table class="cabecera">
    <tr>
        <td class="marca">
            <div class="t1">TRANSFORMA</div>
            <div class="t2">Conecta</div>
        </td>
        <td class="centro">
            <div class="programa">PROGRAMA DE DESARROLLO PERSONAL "TRANSFORMA-CONECTA"</div>
            <div class="titulo">MI PLANIFICADOR DIARIO</div>
            <div class="subtitulo">RESUMEN DE DESEMPEÑO</div>
        </td>
        <td class="sello">
            Generado el<br>{{ \App\Helpers\Helper::dateTime(now()) }}
        </td>
    </tr>
</table>

<div class="banda">ORGÁNIZATE - ACTÚA - AVANZA</div>

{{-- ============================== ALCANCE ============================= --}}
<table class="seccion">
    <tr>
        <td class="titulo-seccion azul">QUÉ CUBRE ESTE RESUMEN</td>
    </tr>
    <tr>
        <td class="cuerpo">
            <div class="alcance">{{ $analisis['alcance'] }}</div>
            <div class="chico">
                {{ $analisis['dias'] }} {{ $analisis['dias'] === 1 ? 'día registrado' : 'días registrados' }}
                @if ($analisis['desde'] && $analisis['hasta'])
                    · del {{ \App\Helpers\Helper::date(\App\Helpers\Helper::toCarbon($analisis['desde'])) }}
                    al {{ \App\Helpers\Helper::date(\App\Helpers\Helper::toCarbon($analisis['hasta'])) }}
                @endif
                @if ($marca)
                    · DOCUMENTO DE MUESTRA
                @endif
            </div>
        </td>
    </tr>
</table>

{{-- ============================== NÚMEROS ============================= --}}
<table class="seccion numeros">
    <tr>
        <td>
            <div class="valor">{{ $analisis['global']['rendimiento'] }}</div>
            <div class="rotulo">RENDIMIENTO PROMEDIO</div>
        </td>
        <td>
            <div class="valor">{{ $analisis['global']['horario_pct'] }}%</div>
            <div class="rotulo">DEL HORARIO ({{ $analisis['global']['horario_hechas'] }}/{{ $analisis['global']['horario_total'] }})</div>
        </td>
        <td>
            <div class="valor">{{ $analisis['global']['objetivos_pct'] }}%</div>
            <div class="rotulo">DE LOS OBJETIVOS ({{ $analisis['global']['objetivos_hechos'] }}/{{ $analisis['global']['objetivos_total'] }})</div>
        </td>
        <td>
            <div class="valor">{{ $analisis['global']['senales_promedio'] }}</div>
            <div class="rotulo">SEÑALES POR DÍA ({{ $analisis['global']['senales'] }} EN TOTAL)</div>
        </td>
    </tr>
</table>

{{-- =========================== LOS TEXTOS ============================= --}}
@foreach ($textos as $clave => $frases)
    <table class="seccion">
        <tr>
            <td class="titulo-seccion {{ $clave === 'mal' ? 'naranja' : ($clave === 'procrastinacion' ? 'rojo' : 'turquesa') }}">
                {{ mb_strtoupper($titulos[$clave] ?? $clave) }}
            </td>
        </tr>
        <tr>
            <td class="cuerpo texto">
                @foreach ($frases as $frase)
                    <p>{{ $frase }}</p>
                @endforeach
            </td>
        </tr>
    </table>
@endforeach

{{-- ================ PERFORMANCE VS PROCRASTINACIÓN ==================== --}}
<table class="seccion">
    <tr>
        <td class="titulo-seccion rojo">PERFORMANCE VS PROCRASTINACIÓN</td>
    </tr>
    <tr>
        <td class="cuerpo">
            <div class="grafico"><img src="{{ $graficos['procrastinacion'] }}" alt="Performance contra procrastinación"></div>
            <div class="chico">
                Cada punto es el rendimiento promedio de los días con esa cantidad de señales marcadas.
                El cuadrado turquesa es hasta dónde no cuesta; el rojo, desde dónde el rendimiento cae.
            </div>
        </td>
    </tr>
</table>

{{-- =========================== DÍA POR DÍA ============================ --}}
<table class="seccion">
    <tr>
        <td class="titulo-seccion turquesa">DÍA POR DÍA</td>
    </tr>
    <tr>
        <td class="cuerpo">
            <table class="dias">
                <thead>
                    <tr>
                        <th style="text-align: left;">DÍA</th>
                        <th>ENERGÍA</th>
                        <th>RENDIMIENTO</th>
                        <th>HORARIO</th>
                        <th>OBJETIVOS</th>
                        <th>PREPARATIVOS</th>
                        <th>SEÑALES</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($analisis['detalle'] as $dia)
                        <tr>
                            <td class="izq">{{ $dia['etiqueta'] }}</td>
                            <td>{{ $dia['energia'] }}</td>
                            <td class="fuerte">{{ $dia['rendimiento'] }}</td>
                            <td>{{ $dia['horario_hechas'] }}/{{ $dia['horario_total'] }}
                                ({{ $dia['horario_pct'] }}%)</td>
                            <td>{{ $dia['objetivos_hechos'] }}/{{ $dia['objetivos_total'] }}
                                ({{ $dia['objetivos_pct'] }}%)</td>
                            <td>{{ $dia['preparativos_hechos'] }}/{{ $dia['preparativos_total'] }}
                                ({{ $dia['preparativos_pct'] }}%)</td>
                            <td>{{ $dia['senales'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </td>
    </tr>
</table>

{{-- ====================== EVOLUCIÓN Y ENERGÍA ========================= --}}
<table class="seccion">
    <tr>
        <td class="titulo-seccion azul">RENDIMIENTO DE CADA DÍA</td>
    </tr>
    <tr>
        <td class="cuerpo"><div class="grafico"><img src="{{ $graficos['dias'] }}" alt="Rendimiento de cada día"></div></td>
    </tr>
</table>

@if (count($analisis['porEnergia']) > 1)
    <table class="seccion">
        <tr>
            <td class="titulo-seccion turquesa">RENDIMIENTO SEGÚN LA ENERGÍA</td>
        </tr>
        <tr>
            <td class="cuerpo"><div class="grafico"><img src="{{ $graficos['energia'] }}" alt="Rendimiento según la energía"></div></td>
        </tr>
    </table>
@endif

{{-- ============== LÍNEA DE TIEMPO CUANDO ES UN SOLO DÍA =============== --}}
@if ($agenda)
    <table class="seccion">
        <tr>
            <td class="titulo-seccion naranja">CÓMO SE REPARTIÓ EL DÍA</td>
        </tr>
        <tr>
            <td class="cuerpo"><div class="grafico"><img src="{{ $agenda }}" alt="Línea de tiempo del día"></div></td>
        </tr>
    </table>
@endif

{{-- ============================== NOTA =============================== --}}
<div class="nota">
    <strong>Qué es este documento.</strong> Es la lectura de lo que vos mismo registraste en tus
    planificadores diarios: los números describen hábitos que cumpliste y señales que marcaste.
    No es un diagnóstico ni una valoración sobre tu persona, y no pretende serlo. Una valoración
    de la salud —física o emocional— corresponde a un profesional.
</div>

@php
    // El motor avisa cuando la muestra es chica. Acá no va: el resumen muestra
    // los cruces con los días que haya y los explica en su lugar.
    $avisos = array_filter(
        $analisis['avisos'],
        fn (string $aviso) => ! str_contains(mb_strtolower($aviso), 'muestra es de')
    );
@endphp

@if ($avisos !== [])
    <div class="avisos">
        @foreach ($avisos as $aviso)
            · {{ $aviso }}<br>
        @endforeach
    </div>
@endif

{{-- =============================== PIE =============================== --}}
<div class="pie">
    <strong>MI PLANIFICADOR DIARIO</strong> · ORGÁNIZATE · ACTÚA · AVANZA<br>
    Programa de Desarrollo Personal "Transforma-Conecta" ·
    Generado el {{ \App\Helpers\Helper::dateTime(now()) }}
</div>

@if ($marca)
    <div class="aviso-muestra">DOCUMENTO DE MUESTRA · SPECIMEN</div>
@endif

</body>
</html>
