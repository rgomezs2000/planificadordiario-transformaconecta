{{--
    ==========================================================================
    PDF · Mi Planificador Diario
    --------------------------------------------------------------------------
    Formato imprimible que reproduce la hoja institucional, ordenado por
    secciones y con los colores de Transforma-Conecta.

    Lo genera dompdf (barryvdh/laravel-dompdf). dompdf no entiende flexbox ni
    variables CSS, así que aquí el maquetado va con tablas y colores en
    hexadecimal. La marca de agua SPECIMEN no se dibuja aquí: la pinta el
    controlador sobre el lienzo del PDF, porque el texto rotado no se puede
    hacer con CSS.
    ==========================================================================
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Planificador diario {{ \App\Helpers\Helper::date($plan->plan_date) }}</title>

    <style>
        @page {
            margin: 24px 24px 42px 24px;
        }

        body {
            /* Helvetica es una de las fuentes base del PDF: no se incrusta,
               así el archivo pesa unos pocos KB en vez de casi 1 MB, y el texto
               queda seleccionable. Cubre acentos, ñ, ¿ y ¡. */
            font-family: Helvetica, Arial, sans-serif;
            font-size: 8.5px;
            color: #10233f;
            margin: 0;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        /* ---------- Cabecera ---------- */
        .cabecera td {
            vertical-align: middle;
            padding-bottom: 6px;
        }

        .marca {
            width: 90px;
            line-height: 1.05;
        }

        .marca .t1 {
            color: #f0600c;
            font-size: 12px;
            font-weight: bold;
        }

        .marca .t2 {
            color: #00ae9c;
            font-size: 15px;
            font-weight: bold;
        }

        .programa {
            color: #006c60;
            font-size: 7.5px;
            font-weight: bold;
            letter-spacing: 0.4px;
        }

        .titulo {
            color: #002060;
            font-size: 21px;
            font-weight: bold;
            line-height: 1.05;
        }

        .subtitulo {
            color: #0080d0;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 0.6px;
        }

        .banda {
            background-color: #00ae9c;
            color: #ffffff;
            font-size: 8px;
            font-weight: bold;
            letter-spacing: 2px;
            padding: 4px 0;
            text-align: center;
        }

        /* ---------- Datos del día ---------- */
        .datos {
            margin-top: 8px;
            margin-bottom: 8px;
        }

        .datos td {
            border: 1px solid #cfd9e6;
            background-color: #f4f7fb;
            padding: 4px 6px;
        }

        .datos .etiqueta {
            color: #004090;
            font-size: 7px;
            font-weight: bold;
            letter-spacing: 0.5px;
        }

        .datos .valor {
            font-size: 9.5px;
            font-weight: bold;
        }

        /* ---------- Secciones ---------- */
        .seccion {
            margin-bottom: 7px;
            page-break-inside: avoid;
        }

        .seccion .titulo-seccion {
            color: #ffffff;
            font-size: 8.5px;
            font-weight: bold;
            letter-spacing: 0.6px;
            padding: 4px 6px;
        }

        .seccion .cuerpo {
            border: 1px solid #cfd9e6;
            border-top: none;
            padding: 5px 6px;
        }

        .azul {
            background-color: #0080d0;
        }

        .naranja {
            background-color: #f0600c;
        }

        .turquesa {
            background-color: #00ae9c;
        }

        .rojo {
            background-color: #d90b0b;
        }

        .navy {
            background-color: #002060;
        }

        /* ---------- Objetivos ---------- */
        .objetivo td {
            border-bottom: 1px dashed #cfd9e6;
            padding: 3px 2px;
            vertical-align: top;
        }

        .objetivo:last-child td {
            border-bottom: none;
        }

        .numero {
            width: 16px;
            color: #ffffff;
            font-size: 10px;
            font-weight: bold;
            text-align: center;
        }

        .n1 {
            background-color: #009ce4;
        }

        .n2 {
            background-color: #f0600c;
        }

        .n3 {
            background-color: #00ae9c;
        }

        .obj-etiqueta {
            width: 92px;
        }

        .obj-etiqueta strong {
            color: #002060;
            font-size: 8px;
            text-transform: uppercase;
        }

        .obj-etiqueta span {
            color: #6b7a90;
            font-size: 7px;
            display: block;
        }

        .obj-descripcion {
            font-size: 9px;
        }

        .cumplido {
            width: 46px;
            color: #006c60;
            font-size: 7.5px;
            text-align: right;
        }

        /* ---------- Horario ---------- */
        .horario th {
            color: #ffffff;
            font-size: 7.5px;
            font-weight: bold;
            padding: 3px 4px;
            text-align: left;
        }

        .horario td {
            border-bottom: 1px solid #e4eaf2;
            font-size: 8.5px;
            padding: 3px 4px;
        }

        .horario .hora {
            color: #002060;
            font-weight: bold;
            width: 48px;
        }

        .horario .check {
            width: 22px;
            text-align: center;
        }

        /* ---------- Listas con casilla ---------- */
        .item {
            padding: 2.5px 0;
            border-bottom: 1px dotted #e4eaf2;
        }

        .item:last-child {
            border-bottom: none;
        }

        .casilla {
            color: #006c60;
            font-weight: bold;
        }

        .item .detalle {
            color: #6b7a90;
            font-size: 8px;
        }

        .etiqueta-campo {
            color: #004090;
            font-size: 7px;
            font-weight: bold;
            letter-spacing: 0.4px;
            display: block;
            margin-top: 4px;
        }

        .caja {
            border: 1px solid #cfd9e6;
            background-color: #f4f7fb;
            padding: 3px 5px;
            font-size: 8.5px;
            min-height: 12px;
        }

        .sin-dato {
            color: #9aa8ba;
        }

        /* ---------- Pie ---------- */
        .pie {
            margin-top: 10px;
            border-top: 2px solid #f0600c;
            padding-top: 5px;
            color: #6b7a90;
            font-size: 7px;
            text-align: center;
        }

        .pie strong {
            color: #002060;
        }

        /* Sólo cuando se pidió la marca de prueba */
        .aviso-muestra {
            margin-top: 4px;
            color: #d90b0b;
            font-size: 7px;
            font-weight: bold;
            text-align: center;
            letter-spacing: 1px;
        }
    </style>
</head>

<body>

{{-- ============================ CABECERA ============================ --}}
<table class="cabecera">
    <tr>
        <td class="marca">
            <div class="t1">TRANSFORMA</div>
            <div class="t2">Conecta</div>
        </td>
        <td>
            <div class="programa">PROGRAMA DE DESARROLLO PERSONAL "TRANSFORMA-CONECTA"</div>
            <div class="titulo">MI PLANIFICADOR DIARIO</div>
            <div class="subtitulo">SISTEMA DE PLANIFICACIÓN PERSONAL</div>
        </td>
    </tr>
</table>

<div class="banda">ORGÁNIZATE - ACTÚA - AVANZA</div>

{{-- ======================== DATOS DEL DÍA ========================== --}}
<table class="datos">
    <tr>
        <td width="28%">
            <span class="etiqueta">FECHA</span><br>
            <span class="valor">{{ \App\Helpers\Helper::date($plan->plan_date) }}</span>
        </td>
        <td width="30%">
            <span class="etiqueta">DÍA</span><br>
            <span class="valor">
                {{ $plan->weekday_letter }} ·
                {{ \App\Helpers\Helper::dayName($plan->plan_date, capitalize: true) }}
            </span>
        </td>
        <td width="42%">
            <span class="etiqueta">MI ENERGÍA HOY</span><br>
            <span class="valor">{{ $plan->energyLevel?->name ?? 'Sin registrar' }}</span>
        </td>
    </tr>
</table>

{{-- ==================== DOS COLUMNAS DE SECCIONES =================== --}}
<table>
    <tr>
        {{-- ------------------------- IZQUIERDA ------------------------- --}}
        <td width="55%" valign="top" style="padding-right: 8px;">

            {{-- Mis 3 objetivos --}}
            <table class="seccion">
                <tr>
                    <td class="titulo-seccion naranja">MIS 3 OBJETIVOS PRINCIPALES DE HOY</td>
                </tr>
                <tr>
                    <td class="cuerpo">
                        <table>
                            @forelse ($plan->goals as $objetivo)
                                <tr class="objetivo">
                                    <td class="numero n{{ $objetivo->slot }}">{{ $objetivo->slot }}</td>
                                    <td class="obj-etiqueta">
                                        <strong>{{ $objetivo->goalType?->name ?? 'Objetivo' }}</strong>
                                        <span>{{ $objetivo->goalType?->subtitle }}</span>
                                    </td>
                                    <td class="obj-descripcion">{{ $objetivo->description }}</td>
                                    <td class="cumplido">
                                        {{ $objetivo->is_done ? '[X] Cumplido' : '[ ]' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="sin-dato">Sin objetivos registrados.</td>
                                </tr>
                            @endforelse
                        </table>
                    </td>
                </tr>
            </table>

            {{-- Mi horario de hoy --}}
            <table class="seccion">
                <tr>
                    <td class="titulo-seccion azul">MI HORARIO DE HOY</td>
                </tr>
                <tr>
                    <td class="cuerpo">
                        <table class="horario">
                            <thead>
                                <tr>
                                    <th class="hora" style="background-color: #f0600c;">HORA</th>
                                    <th style="background-color: #0080d0;">¿QUÉ VOY A HACER?</th>
                                    <th class="check" style="background-color: #00ae9c;">OK</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($plan->scheduleEntries as $franja)
                                    <tr>
                                        <td class="hora">{{ \App\Helpers\Helper::timeLabel($franja->start_time) }}</td>
                                        <td>{{ $franja->activity }}</td>
                                        <td class="check">{{ $franja->is_done ? '[X]' : '[ ]' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="sin-dato">Sin franjas registradas.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </td>
                </tr>
            </table>

            {{-- Notas --}}
            <table class="seccion">
                <tr>
                    <td class="titulo-seccion navy">NOTAS / RECORDATORIOS</td>
                </tr>
                <tr>
                    <td class="cuerpo">
                        @php $notas = $plan->notes->pluck('content')->filter()->implode("\n"); @endphp

                        @if ($notas !== '')
                            {!! nl2br(e($notas)) !!}
                        @else
                            <span class="sin-dato">Sin notas.</span>
                        @endif
                    </td>
                </tr>
            </table>

        </td>

        {{-- -------------------------- DERECHA -------------------------- --}}
        <td width="45%" valign="top">

            {{-- Antes de empezar --}}
            <table class="seccion">
                <tr>
                    <td class="titulo-seccion turquesa">ANTES DE EMPEZAR</td>
                </tr>
                <tr>
                    <td class="cuerpo">
                        @forelse ($plan->preparationItems as $preparado)
                            <div class="item">
                                <span class="casilla">{{ $preparado->pivot->is_checked ? '[X]' : '[ ]' }}</span>
                                {{ $preparado->name }}
                                @if ($preparado->pivot->preparation_items_description)
                                    <span class="detalle">
                                        — {{ $preparado->pivot->preparation_items_description }}
                                    </span>
                                @endif
                            </div>
                        @empty
                            <span class="sin-dato">Sin checklist registrado.</span>
                        @endforelse
                    </td>
                </tr>
            </table>

            {{-- Si estoy procrastinando --}}
            <table class="seccion">
                <tr>
                    <td class="titulo-seccion naranja">SI ESTOY PROCRASTINANDO ME PREGUNTO</td>
                </tr>
                <tr>
                    <td class="cuerpo">
                        @forelse ($plan->reflectionAnswers as $respuesta)
                            <div class="item">
                                <span class="casilla">{{ $respuesta->is_checked ? '[X]' : '[ ]' }}</span>
                                {{ $respuesta->question?->question }}
                                @if ($respuesta->answer)
                                    <span class="detalle">— {{ $respuesta->answer }}</span>
                                @endif
                            </div>
                        @empty
                            <span class="sin-dato">Sin respuestas registradas.</span>
                        @endforelse
                    </td>
                </tr>
            </table>

            {{-- Bloque de acción --}}
            @php $bloque = $plan->actionBlocks->first(); @endphp

            <table class="seccion">
                <tr>
                    <td class="titulo-seccion turquesa">BLOQUE DE ACCIÓN</td>
                </tr>
                <tr>
                    <td class="cuerpo">
                        <span class="etiqueta-campo">VOY A TRABAJAR DURANTE</span>
                        <div class="caja">
                            {{ $bloque?->duration?->label ?? 'Sin registrar' }}
                        </div>

                        <span class="etiqueta-campo">CUANDO TERMINE ESTE BLOQUE</span>
                        <div class="caja">
                            {{ $bloque?->outcome?->name ?? 'Sin registrar' }}
                        </div>

                        <span class="etiqueta-campo">¿EN QUÉ VOY A TRABAJAR?</span>
                        <div class="caja">
                            {{ $bloque?->task ?: 'Sin registrar' }}
                        </div>
                    </td>
                </tr>
            </table>

            {{-- Cierre del día --}}
            <table class="seccion">
                <tr>
                    <td class="titulo-seccion rojo">CIERRE DEL DÍA</td>
                </tr>
                <tr>
                    <td class="cuerpo">
                        <span class="etiqueta-campo">LO QUE LOGRÉ HOY</span>
                        <div class="caja">{{ $plan->achievements ?: 'Sin registrar' }}</div>

                        <span class="etiqueta-campo">LO QUE QUEDÓ PENDIENTE</span>
                        <div class="caja">{{ $plan->pending ?: 'Sin registrar' }}</div>

                        <span class="etiqueta-campo">¿CUÁNDO LO HARÉ?</span>
                        <div class="caja">{{ $plan->pending_when ?: 'Sin registrar' }}</div>

                        <span class="etiqueta-campo">HOY ESTOY ORGULLOSO/A DE MÍ PORQUE</span>
                        <div class="caja">{{ $plan->proud_of ?: 'Sin registrar' }}</div>
                    </td>
                </tr>
            </table>

        </td>
    </tr>
</table>

{{-- ============================== PIE =============================== --}}
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
