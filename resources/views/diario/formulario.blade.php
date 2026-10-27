@extends('layouts.app')

@php
    // El mismo formulario sirve para crear, ver y modificar.
    $soloLectura = $modo === 'ver';
    $esEdicion = $modo === 'editar';

    // readonly para los campos de texto, disabled para casillas, radios y
    // selectores (esos no admiten readonly).
    $ro = $soloLectura ? 'readonly' : '';
    $dis = $soloLectura ? 'disabled' : '';

    $fecha = $plan?->plan_date;

    $tituloPagina = match ($modo) {
        'ver' => 'Diario del '.$fecha?->format('d/m/Y'),
        'editar' => 'Modificar el diario del '.$fecha?->format('d/m/Y'),
        default => 'Nuevo diario',
    };
@endphp

@section('titulo', $tituloPagina.' · Mi Planificador Diario')

@push('estilos')
    {{-- Datepicker de Bootstrap por CDN (nada de NPM) --}}
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-datepicker@1.10.1/dist/css/bootstrap-datepicker3.min.css">
@endpush

@section('contenido')

    <form id="formulario-diario" method="POST" action="{{ $accion }}"
          data-modo="{{ $modo }}" data-metodo="{{ $metodo }}"
          data-indice-horario="{{ $indiceHorario }}"
          data-url-meta-semanal="{{ route('diario.meta-semanal') }}"
          data-url-exito="{{ $esEdicion ? route('diario.listado') : route('home') }}" novalidate>
        @csrf
        @if ($metodo !== 'POST')
            @method($metodo)
        @endif

        {{-- ==================================================================
             1. CABECERA DEL DÍA: fecha, día y energía
             ================================================================== --}}
        <section class="tc-tarjeta tf-seccion mb-3">
            <div class="row g-3 align-items-end">

                <div class="col-12 col-sm-6 col-xl-3">
                    <label class="tf-etiqueta tf-obligatorio" for="plan_date">Fecha</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-calendar3" aria-hidden="true"></i></span>
                        <input type="text" class="form-control" id="plan_date" name="plan_date"
                               value="{{ $planDate }}" placeholder="aaaa-mm-dd" autocomplete="off"
                               @if ($dateLocked) readonly @endif required>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-xl-4">
                    <span class="tf-etiqueta tf-obligatorio">Día</span>
                    <div class="tf-dias" role="group" aria-label="Día de la semana">
                        @foreach ($weekDays as $dia)
                            <input type="radio" class="btn-check tf-dia" name="dia_semana"
                                   id="dia-{{ $dia['value'] }}" value="{{ $dia['value'] }}"
                                   data-dia="{{ $dia['value'] }}" disabled>
                            <label class="tf-dia__letra" for="dia-{{ $dia['value'] }}">{{ $dia['label'] }}</label>
                        @endforeach
                    </div>
                </div>

                <div class="col-12 col-xl-5">
                    <span class="tf-etiqueta tf-obligatorio">Mi energía hoy</span>
                    <div class="tf-energia" role="group" aria-label="Nivel de energía de hoy"
                         data-tf-requerido="energia">
                        @foreach ($energyLevels as $nivel)
                            <input type="radio" class="btn-check" name="energy_level_id"
                                   id="energia-{{ $nivel->id }}" value="{{ $nivel->id }}"
                                   autocomplete="off" @checked((int) $plan?->energy_level_id === $nivel->id) {!! $dis !!}>
                            <label class="tf-energia__boton tf-energia__boton--{{ $nivel->slug }}"
                                   for="energia-{{ $nivel->id }}">
                                <span class="tf-energia__cara" aria-hidden="true">{{ $nivel->emoji }}</span>
                                <span class="tf-energia__nombre">{{ $nivel->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

            </div>
        </section>

        <div class="row g-3">

            {{-- ==============================================================
                 COLUMNA IZQUIERDA
                 ============================================================== --}}
            <div class="col-12 col-lg-7">

                {{-- 2. MI META SEMANAL (va encima de los 3 objetivos) --}}
                @php $metaRegistrada = (bool) $metaSemanal; @endphp

                <section class="tc-tarjeta tf-seccion tf-seccion--naranja mb-3" data-tf-seccion="meta-semanal">
                    <h3 class="tf-titulo">
                        <i class="bi bi-flag" aria-hidden="true"></i>
                        Mi meta semanal
                        @unless ($soloLectura)
                            <span class="tf-obligatorio-tag {{ $metaRegistrada ? 'tf-obligatorio-tag--suave' : '' }}"
                                  id="meta-semanal-tag">
                                {{ $metaRegistrada ? 'Registrada' : 'Obligatorio' }}
                            </span>
                        @endunless
                    </h3>

                    <div class="tf-meta__cabecera">
                        <span class="tf-meta__semana" id="meta-semanal-etiqueta">
                            {{ $metaSemanal?->etiqueta() ?? 'Aún no hay meta para esta semana' }}
                        </span>
                    </div>

                    @if ($soloLectura && ! $metaSemanal)
                        <p class="tf-ayuda mb-0">Este día no tiene una meta semanal registrada.</p>
                    @else
                        {{-- El botón de cambiar pone este campo en 1. Sin él, la
                             meta registrada no se vuelve a tocar al guardar. --}}
                        @unless ($soloLectura)
                            <input type="hidden" name="weekly_goal[actualizar]" id="weekly_goal_actualizar" value="0">
                        @endunless

                        <p class="tf-ayuda" id="meta-semanal-nota">
                            @if ($metaRegistrada)
                                Ya está registrada para esta semana: se usa en todos sus días.
                                Si necesitas cambiarla, pulsa «Cambiar meta de la semana».
                            @else
                                Aún no hay meta para esta semana: escríbela y se compartirá con todos sus días.
                            @endif
                        </p>

                        <label class="tf-etiqueta {{ $metaRegistrada || $soloLectura ? '' : 'tf-obligatorio' }}"
                               id="weekly_goal_goal_label"
                               for="weekly_goal_goal">
                            Escribe una meta semanal de lo que más te cueste
                        </label>
                        <p class="tf-ayuda">
                            Debe ser específica, realista y posible de lograr.
                        </p>
                        <textarea class="form-control tf-meta__campo" id="weekly_goal_goal"
                                  name="weekly_goal[goal]" rows="3" maxlength="2000"
                                  data-tf-requerido="meta-semanal"
                                  @if ($metaRegistrada) readonly @endif {!! $ro !!}
                                  placeholder="Esta semana me propongo…">{{ old('weekly_goal.goal', $metaSemanal?->goal) }}</textarea>

                        <label class="tf-etiqueta mt-2" for="weekly_goal_why">
                            ¿Por qué es importante para mí?
                        </label>
                        <textarea class="form-control tf-meta__campo" id="weekly_goal_why"
                                  name="weekly_goal[why_important]" rows="2" maxlength="2000"
                                  @if ($metaRegistrada) readonly @endif {!! $ro !!}
                                  placeholder="Porque…">{{ old('weekly_goal.why_important', $metaSemanal?->why_important) }}</textarea>

                        @unless ($soloLectura)
                            <button type="button" class="tc-boton tc-boton--contorno tf-meta__editar"
                                    id="meta-semanal-editar"
                                    @if (! $metaRegistrada) hidden @endif>
                                <i class="bi bi-pencil" aria-hidden="true"></i>
                                Cambiar meta de la semana
                            </button>
                        @endunless
                    @endif
                </section>

                {{-- 3. MIS 3 OBJETIVOS PRINCIPALES DE HOY --}}
                <section class="tc-tarjeta tf-seccion tf-seccion--naranja mb-3" data-tf-seccion="objetivos">
                    <h3 class="tf-titulo">
                        <i class="bi bi-bullseye" aria-hidden="true"></i>
                        Mis 3 objetivos principales de hoy
                        {{-- El aviso de obligatorio sólo sirve mientras se completa el
                             formulario: en modo lectura no se muestra. --}}
                        @unless ($soloLectura)
                            <span class="tf-obligatorio-tag">Obligatorio</span>
                        @endunless
                    </h3>

                    @foreach ($goalTypes as $indice => $tipo)
                        @php
                            $slot = $indice + 1;
                            $objetivo = $plan?->goals->firstWhere('slot', $slot);
                        @endphp

                        <div class="tf-objetivo">
                            <span class="tf-objetivo__numero tf-objetivo__numero--{{ $slot }}">{{ $slot }}</span>

                            <div class="tf-objetivo__etiqueta">
                                <strong>{{ $tipo->name }}</strong>
                                <small>{{ $tipo->subtitle }}</small>
                            </div>

                            <input type="hidden" name="goals[{{ $indice }}][slot]" value="{{ $slot }}">
                            <input type="hidden" name="goals[{{ $indice }}][goal_type_id]" value="{{ $tipo->id }}">

                            <input type="text" class="form-control form-control-sm tf-objetivo__campo"
                                   name="goals[{{ $indice }}][description]" maxlength="255"
                                   value="{{ $objetivo?->description }}"
                                   data-tf-requerido="objetivo"
                                   aria-label="Objetivo {{ $slot }}: {{ $tipo->name }}" {!! $ro !!}>

                            <div class="form-check tf-objetivo__cumplido">
                                <input class="form-check-input" type="checkbox"
                                       name="goals[{{ $indice }}][is_done]" value="1"
                                       id="objetivo-cumplido-{{ $slot }}" @checked($objetivo?->is_done) {!! $dis !!}>
                                <label class="form-check-label" for="objetivo-cumplido-{{ $slot }}">
                                    Cumplido
                                </label>
                            </div>
                        </div>
                    @endforeach
                </section>

                {{-- 4. MI HORARIO DE HOY --}}
                <section class="tc-tarjeta tf-seccion tf-seccion--azul mb-3" data-tf-seccion="horario">
                    <h3 class="tf-titulo">
                        <i class="bi bi-clock-history" aria-hidden="true"></i>
                        Mi horario de hoy
                        @unless ($soloLectura)
                            <span class="tf-obligatorio-tag">Al menos una franja</span>
                        @endunless
                    </h3>

                    <table class="table tf-horario mb-2" id="tabla-horario">
                        <thead>
                            <tr>
                                <th class="tf-horario__col-hora" scope="col">Hora</th>
                                <th scope="col">¿Qué vas a hacer hoy?</th>
                                <th class="tf-horario__col-check text-center" scope="col">
                                    {{-- En la vista de sólo lectura también se
                                         puede marcar el cumplimiento de todas
                                         las franjas de una vez. --}}
                                    <input class="form-check-input" type="checkbox" id="horario-todos"
                                           title="Marcar o desmarcar todas las franjas">
                                    <label class="visually-hidden" for="horario-todos">Marcar todas</label>
                                </th>
                                <th class="tf-horario__col-accion" scope="col">
                                    <span class="visually-hidden">Quitar</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody id="horario-filas">
                            @foreach ($plan?->scheduleEntries ?? [] as $i => $franja)
                                <tr class="tf-horario__fila">
                                    <td>
                                        <input type="hidden" name="schedule[{{ $i }}][id]" value="{{ $franja->id }}">
                                        <input type="time" class="form-control form-control-sm"
                                               name="schedule[{{ $i }}][start_time]"
                                               value="{{ \App\Helpers\Helper::time($franja->start_time, 'H:i') }}"
                                               data-tf-requerido="hora"
                                               aria-label="Hora de la franja" required {!! $ro !!}>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control form-control-sm" maxlength="255"
                                               name="schedule[{{ $i }}][activity]" value="{{ $franja->activity }}"
                                               data-tf-requerido="actividad"
                                               placeholder="¿Qué vas a hacer?"
                                               aria-label="Actividad de la franja" {!! $ro !!}>
                                    </td>
                                    <td class="text-center">
                                        {{-- La casilla queda activa también en
                                             modo ver: el botón del final de la
                                             sección guarda el cumplimiento. --}}
                                        <input class="form-check-input tf-horario__check" type="checkbox"
                                               name="schedule[{{ $i }}][is_done]" value="1"
                                               @checked($franja->is_done)
                                               aria-label="Franja cumplida">
                                    </td>
                                    <td class="text-center">
                                        @unless ($soloLectura)
                                            <button type="button" class="tf-horario__quitar" title="Quitar franja"
                                                    aria-label="Quitar franja">
                                                <i class="bi bi-dash-lg" aria-hidden="true"></i>
                                            </button>
                                        @endunless
                                    </td>
                                </tr>
                            @endforeach

                            <tr id="horario-vacio">
                                <td colspan="4" class="text-center tf-horario__vacio">
                                    Presiona <strong>+</strong> para agregar una franja del horario.
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    @if ($soloLectura && $plan)
                        {{-- El botón va al final de la sección. Confirma con
                             bootbox y guarda el cumplimiento por AJAX, sin
                             abrir el formulario completo. --}}
                        <div class="tf-horario__pie">
                            <p class="tf-ayuda mb-0">
                                Marca las franjas que cumpliste y guarda el cambio.
                            </p>
                            <button type="button" class="tc-boton tc-boton--turquesa" id="horario-guardar"
                                    data-url="{{ route('diario.horario', $plan) }}">
                                <i class="bi bi-check2-square" aria-hidden="true"></i> Guardar cumplimiento
                            </button>
                        </div>
                    @endif

                    @unless ($soloLectura)
                        <button type="button" class="tc-boton tc-boton--turquesa" id="horario-agregar">
                            <i class="bi bi-plus-lg" aria-hidden="true"></i> Agregar franja
                        </button>
                    @endunless
                </section>

                {{-- 4. ACTIVIDADES DIARIAS (los hábitos de la hoja impresa) --}}
                <section class="tc-tarjeta tf-seccion tf-seccion--turquesa mb-3" data-tf-seccion="actividades">
                    <h3 class="tf-titulo">
                        <i class="bi bi-check2-circle" aria-hidden="true"></i>
                        Actividades diarias
                        @unless ($soloLectura)
                            <span class="tf-obligatorio-tag tf-obligatorio-tag--suave">Marca lo que hiciste</span>
                        @endunless
                    </h3>
                    <p class="tf-ayuda">
                        Pequeñas acciones que sostienen el día. Las de los minutos piden cuánto tiempo les dedicaste.
                        El sábado se suman las del cambio de semana.
                    </p>

                    <table class="table tf-actividades mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Actividad</th>
                                <th scope="col" class="tf-actividades__col-minutos">Minutos</th>
                                <th scope="col" class="tf-actividades__col-hecho text-center">Hecho</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($dailyActivities as $i => $actividad)
                                @php
                                    $fila = $plan?->activities->firstWhere('id', $actividad->id);
                                    $visible = $actividad->weekday === null || (int) $actividad->weekday === $diaSemana;
                                    $minutosDeshabilitados = ! $visible
                                        || (! $soloLectura && ! $fila?->pivot->is_done);
                                @endphp

                                <tr class="tf-actividad{{ $actividad->weekday !== null ? ' tf-actividad--dia' : '' }}"
                                    data-solo-dia="{{ $actividad->weekday ?? '' }}"
                                    @if (! $visible) hidden @endif>
                                    <td>
                                        <input type="hidden" name="activities[{{ $i }}][daily_activity_id]"
                                               value="{{ $actividad->id }}"
                                               @if (! $visible) disabled @endif>

                                        <label class="tf-actividad__nombre" for="actividad-{{ $actividad->id }}">
                                            <i class="bi {{ $actividad->icon }}" aria-hidden="true"></i>
                                            {{ $actividad->name }}
                                            @if ($actividad->weekday !== null)
                                                <span class="tf-actividad__dia">Solo sábado</span>
                                            @endif
                                        </label>
                                    </td>
                                    <td>
                                        @if ($actividad->requires_minutes)
                                            <div class="input-group input-group-sm tf-actividad__minutos">
                                                <input type="number" class="form-control" min="0" max="1440" step="1"
                                                       id="minutos-{{ $actividad->id }}"
                                                       name="activities[{{ $i }}][minutes]"
                                                       value="{{ old('activities.'.$i.'.minutes', $fila?->pivot->minutes) }}"
                                                       aria-label="{{ $actividad->minutes_label ?? 'Minutos' }}"
                                                       @disabled($minutosDeshabilitados)>
                                                <span class="input-group-text">min</span>
                                            </div>
                                        @else
                                            <span class="tf-actividad__sin-minutos" aria-hidden="true">—</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <input class="form-check-input tf-actividad__check" type="checkbox"
                                               name="activities[{{ $i }}][is_done]" value="1"
                                               id="actividad-{{ $actividad->id }}"
                                               data-tf-habilita="minutos-{{ $actividad->id }}"
                                               @checked($fila?->pivot->is_done)
                                               @if (! $visible) disabled @endif>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    @if ($soloLectura && $plan)
                        <div class="tf-horario__pie">
                            <p class="tf-ayuda mb-0">
                                Marca las actividades que hiciste (y sus minutos) y guarda el cambio.
                            </p>
                            <button type="button" class="tc-boton tc-boton--turquesa" id="actividades-guardar"
                                    data-url="{{ route('diario.actividades', $plan) }}">
                                <i class="bi bi-check2-square" aria-hidden="true"></i> Guardar actividades
                            </button>
                        </div>
                    @endif
                </section>

                {{-- 5. MI META PERSONAL (seguimiento del día a día) --}}
                <section class="tc-tarjeta tf-seccion tf-seccion--naranja mb-3" data-tf-seccion="meta-personal"
                         id="seccion-meta-personal" @if ($soloLectura && ! $metaSemanal) hidden @endif>
                    <h3 class="tf-titulo">
                        <i class="bi bi-person-heart" aria-hidden="true"></i>
                        Mi meta personal
                        <span class="tf-meta__dia" id="meta-personal-dia" title="Día de la semana">
                            {{ $diaLetra }}
                        </span>
                        <span class="tf-meta__dia-nombre" id="meta-personal-dia-nombre">{{ $diaNombre }}</span>
                    </h3>

                    <p class="tf-ayuda" id="meta-personal-meta">
                        @if ($metaSemanal)
                            Tu meta de la semana: <strong>{{ \Illuminate\Support\Str::limit($metaSemanal->goal, 140) }}</strong>
                        @else
                            La meta de la semana se muestra aquí en cuanto la escribas.
                        @endif
                    </p>

                    <label class="tf-etiqueta" for="personal_done_today">
                        ¿Qué hice hoy para acercarme a mi meta?
                    </label>
                    <textarea class="form-control form-control-sm mb-2" id="personal_done_today"
                              name="personal_done_today" rows="2" maxlength="2000"
                              {!! $ro !!}>{{ old('personal_done_today', $plan?->personal_done_today) }}</textarea>

                    <label class="tf-etiqueta" for="personal_helped">¿Qué me ayudó?</label>
                    <textarea class="form-control form-control-sm mb-2" id="personal_helped"
                              name="personal_helped" rows="2" maxlength="2000"
                              {!! $ro !!}>{{ old('personal_helped', $plan?->personal_helped) }}</textarea>

                    <label class="tf-etiqueta" for="personal_hard">¿Qué me dificultó?</label>
                    <textarea class="form-control form-control-sm" id="personal_hard"
                              name="personal_hard" rows="2" maxlength="2000"
                              {!! $ro !!}>{{ old('personal_hard', $plan?->personal_hard) }}</textarea>
                </section>

                {{-- 8. NOTAS DEL DÍA --}}
                <section class="tc-tarjeta tf-seccion mb-3">
                    <h3 class="tf-titulo">
                        <i class="bi bi-journal-check" aria-hidden="true"></i>
                        Notas del día
                    </h3>
                    <p class="tf-ayuda">Tus notas y recordatorios, en un solo campo de texto.</p>

                    <textarea class="form-control tf-nota" id="notes" name="notes[0][content]"
                              rows="6" maxlength="1000" {!! $ro !!}
                              placeholder="Escribe aquí tus notas y recordatorios">{{ $plan?->notes->pluck('content')->implode("\n") }}</textarea>
                </section>

                {{-- 9. REFLEXIÓN DE LA SEMANA (sólo domingo) --}}
                <section class="tc-tarjeta tf-seccion tf-seccion--rojo mb-3" data-tf-seccion="reflexion-semanal"
                         id="seccion-reflexion-semanal" data-solo-dia="0"
                         @if (! $esDomingo) hidden @endif>
                    <h3 class="tf-titulo">
                        <i class="bi bi-journal-heart" aria-hidden="true"></i>
                        Reflexión de la semana
                        <span class="tf-obligatorio-tag tf-obligatorio-tag--suave">Domingo</span>
                    </h3>
                    <p class="tf-ayuda">
                        Cierra la semana con calma: lo que pasó, lo que aprendiste y lo que viene.
                    </p>

                    <span class="tf-meta__semana" id="reflexion-semanal-etiqueta">
                        {{ $reflexionSemanal?->etiqueta() ?? 'Esta semana' }}
                    </span>

                    @foreach (\App\Models\WeeklyReflection::PREGUNTAS as $clave => $pregunta)
                        <label class="tf-etiqueta mt-2" for="reflexion-{{ $clave }}">
                            {{ $loop->iteration }}. {{ $pregunta }}
                        </label>
                        <textarea class="form-control form-control-sm" id="reflexion-{{ $clave }}"
                                  name="weekly_reflection[{{ $clave }}]" rows="2" maxlength="2000"
                                  @disabled($soloLectura || ! $esDomingo)
                                  placeholder="Tu respuesta">{{ old('weekly_reflection.'.$clave, $reflexionSemanal?->{$clave}) }}</textarea>
                    @endforeach
                </section>

            </div>

            {{-- ==============================================================
                 COLUMNA DERECHA
                 ============================================================== --}}
            <div class="col-12 col-lg-5">

                {{-- 3. ANTES DE EMPEZAR --}}
                <section class="tc-tarjeta tf-seccion tf-seccion--turquesa mb-3">
                    <h3 class="tf-titulo">
                        <i class="bi bi-check2-square" aria-hidden="true"></i>
                        Antes de empezar
                    </h3>
                    <p class="tf-ayuda">¿Qué necesito tener listo?</p>

                    @foreach ($preparationItems as $indice => $item)
                        @php $preparado = $plan?->preparationItems->firstWhere('id', $item->id); @endphp

                        <div class="tf-check">
                            <div class="form-check">
                                <input class="form-check-input tf-check__caja" type="checkbox"
                                       name="preparation[{{ $indice }}][is_checked]" value="1"
                                       id="preparacion-{{ $item->id }}"
                                       data-tf-habilita="preparacion-texto-{{ $item->id }}"
                                       @checked($preparado?->pivot->is_checked) {!! $dis !!}>
                                <label class="form-check-label" for="preparacion-{{ $item->id }}">
                                    {{ $item->name }}
                                </label>
                            </div>

                            <input type="hidden" name="preparation[{{ $indice }}][preparation_item_id]"
                                   value="{{ $item->id }}">

                            <textarea class="form-control form-control-sm tf-check__detalle"
                                      id="preparacion-texto-{{ $item->id }}"
                                      name="preparation[{{ $indice }}][preparation_items_description]"
                                      rows="2" maxlength="2000"
                                      @disabled($soloLectura || ! $preparado?->pivot->is_checked)
                                      placeholder="¿Qué incluye? Por ejemplo: PC, cuaderno, calculadora"
                                      @if ($soloLectura) readonly @endif
                            >{{ $preparado?->pivot->preparation_items_description }}</textarea>
                        </div>
                    @endforeach
                </section>

                {{-- 5. SI ESTOY PROCRASTINANDO --}}
                <section class="tc-tarjeta tf-seccion tf-seccion--naranja mb-3">
                    <h3 class="tf-titulo">
                        <i class="bi bi-lightbulb" aria-hidden="true"></i>
                        Si estoy procrastinando me pregunto
                    </h3>

                    @foreach ($reflectionQuestions as $indice => $pregunta)
                        @php $respuesta = $plan?->reflectionAnswers->firstWhere('reflection_question_id', $pregunta->id); @endphp

                        <div class="tf-check">
                            <div class="form-check">
                                <input class="form-check-input tf-check__caja" type="checkbox"
                                       name="reflections[{{ $indice }}][is_checked]" value="1"
                                       id="reflexion-{{ $pregunta->id }}"
                                       data-tf-habilita="reflexion-texto-{{ $pregunta->id }}"
                                       @checked($respuesta?->is_checked) {!! $dis !!}>
                                <label class="form-check-label" for="reflexion-{{ $pregunta->id }}">
                                    {{ $pregunta->question }}
                                </label>
                            </div>

                            <input type="hidden" name="reflections[{{ $indice }}][reflection_question_id]"
                                   value="{{ $pregunta->id }}">

                            <textarea class="form-control form-control-sm tf-check__detalle"
                                      id="reflexion-texto-{{ $pregunta->id }}"
                                      name="reflections[{{ $indice }}][answer]"
                                      rows="2" maxlength="2000"
                                      @disabled($soloLectura || ! $respuesta?->is_checked)
                                      placeholder="Tu respuesta"
                                      @if ($soloLectura) readonly @endif
                            >{{ $respuesta?->answer }}</textarea>
                        </div>
                    @endforeach
                </section>

                {{-- 6. BLOQUE DE ACCIÓN --}}
                @php $bloque = $plan?->actionBlocks->first(); @endphp

                <section class="tc-tarjeta tf-seccion tf-seccion--turquesa mb-3">
                    <h3 class="tf-titulo">
                        <i class="bi bi-stopwatch" aria-hidden="true"></i>
                        Bloque de acción
                    </h3>

                    <p class="tf-ayuda">Voy a trabajar durante:</p>
                    {{-- Conserva la identidad del bloque al editar para que el
                         PUT lo actualice en lugar de crear uno nuevo. --}}
                    <input type="hidden" name="action_blocks[0][id]" value="{{ $bloque?->id }}">
                    <div class="tf-opciones">
                        @foreach ($actionBlockDurations as $duracion)
                            <div class="form-check">
                                <input class="form-check-input" type="radio"
                                       name="action_blocks[0][action_block_duration_id]"
                                       id="duracion-{{ $duracion->id }}" value="{{ $duracion->id }}"
                                       @checked((int) $bloque?->action_block_duration_id === $duracion->id) {!! $dis !!}>
                                <label class="form-check-label" for="duracion-{{ $duracion->id }}">
                                    {{ $duracion->label }}
                                </label>
                            </div>
                        @endforeach
                    </div>

                    <p class="tf-ayuda">Cuando termine este bloque:</p>
                    <div class="tf-opciones">
                        @foreach ($actionBlockOutcomes as $resultado)
                            <div class="form-check">
                                <input class="form-check-input" type="radio"
                                       name="action_blocks[0][action_block_outcome_id]"
                                       id="resultado-{{ $resultado->id }}" value="{{ $resultado->id }}"
                                       @checked((int) $bloque?->action_block_outcome_id === $resultado->id) {!! $dis !!}>
                                <label class="form-check-label" for="resultado-{{ $resultado->id }}">
                                    {{ $resultado->name }}
                                </label>
                            </div>
                        @endforeach
                    </div>

                    <label class="tf-etiqueta mt-2" for="bloque-tarea">¿En qué vas a trabajar?</label>
                    <input type="text" class="form-control form-control-sm" id="bloque-tarea"
                           name="action_blocks[0][task]" maxlength="255"
                           value="{{ $bloque?->task }}" {!! $ro !!}>
                </section>

                {{-- 7. CIERRE DEL DÍA --}}
                <section class="tc-tarjeta tf-seccion tf-seccion--rojo mb-3" data-tf-seccion="cierre">
                    <h3 class="tf-titulo">
                        <i class="bi bi-trophy" aria-hidden="true"></i>
                        Cierre del día
                        @unless ($soloLectura)
                            <span class="tf-obligatorio-tag">Obligatorio</span>
                        @endunless
                    </h3>

                    <label class="tf-etiqueta tf-obligatorio" for="achievements">Lo que logré hoy</label>
                    <textarea class="form-control form-control-sm mb-2" id="achievements"
                              name="achievements" rows="2" maxlength="2000"
                              data-tf-requerido="cierre" {!! $ro !!}>{{ $plan?->achievements }}</textarea>

                    <label class="tf-etiqueta tf-obligatorio" for="pending">Lo que quedó pendiente</label>
                    <textarea class="form-control form-control-sm mb-2" id="pending"
                              name="pending" rows="2" maxlength="2000"
                              data-tf-requerido="cierre" {!! $ro !!}>{{ $plan?->pending }}</textarea>

                    <label class="tf-etiqueta tf-obligatorio" for="pending_when">¿Cuándo lo haré?</label>
                    <input type="text" class="form-control form-control-sm mb-2" id="pending_when"
                           name="pending_when" maxlength="255" value="{{ $plan?->pending_when }}"
                           data-tf-requerido="cierre" {!! $ro !!}>

                    <label class="tf-etiqueta tf-obligatorio" for="proud_of">Hoy estoy orgulloso/a de mí porque</label>
                    <textarea class="form-control form-control-sm" id="proud_of"
                              name="proud_of" rows="2" maxlength="2000"
                              data-tf-requerido="cierre" {!! $ro !!}>{{ $plan?->proud_of }}</textarea>
                </section>

            </div>
        </div>

        {{-- Línea de tiempo del día: se dibuja y se rehace sola con el horario --}}
        @include('diario._grafico')

        {{-- ==================================================================
             ACCIONES
             ================================================================== --}}
        <div class="tf-acciones">
            @if ($soloLectura)
                {{-- Un solo botón de documentos, igual que en el listado: abre el
                     modal, donde se elige entre PDF e imagen y si lleva la marca
                     de agua. --}}
                <button type="button" class="tc-boton tc-boton--naranja" id="imprimir-diario"
                        data-accion="pdf" data-id="{{ $plan?->id }}">
                    <i class="bi bi-printer" aria-hidden="true"></i> Imprimir PDF/imagen
                </button>
                <a class="tc-boton tc-boton--contorno" href="{{ route('diario.listado') }}">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i> Volver al listado
                </a>
            @else
                <button type="submit" class="tc-boton tc-boton--naranja" id="formulario-guardar">
                    <i class="bi bi-save" aria-hidden="true"></i> Guardar
                </button>

                <button type="button" class="tc-boton tc-boton--turquesa" id="formulario-limpiar">
                    <i class="bi bi-eraser" aria-hidden="true"></i> Limpiar
                </button>

                <a class="tc-boton tc-boton--contorno"
                   href="{{ $esEdicion ? route('diario.listado') : route('home') }}">
                    <i class="bi bi-x-lg" aria-hidden="true"></i> Cancelar
                </a>
            @endif
        </div>

        {{-- Mensajes de validación, debajo del formulario --}}
        @unless ($soloLectura)
            <div id="formulario-mensajes" class="tf-mensajes" role="alert" aria-live="polite" hidden></div>
        @endunless

        {{-- Plantilla de una fila del horario. Los guiones bajos los sustituye
             public/js/script.js por el número de fila. --}}
        @unless ($soloLectura)
            <template id="plantilla-horario">
                <tr class="tf-horario__fila">
                    <td>
                        <input type="time" class="form-control form-control-sm"
                               name="schedule[__INDICE__][start_time]" data-tf-requerido="hora"
                               aria-label="Hora de la franja" required>
                    </td>
                    <td>
                        <input type="text" class="form-control form-control-sm" maxlength="255"
                               name="schedule[__INDICE__][activity]" data-tf-requerido="actividad"
                               placeholder="¿Qué vas a hacer?" aria-label="Actividad de la franja">
                    </td>
                    <td class="text-center">
                        <input class="form-check-input tf-horario__check" type="checkbox"
                               name="schedule[__INDICE__][is_done]" value="1"
                               aria-label="Franja cumplida">
                    </td>
                    <td class="text-center">
                        <button type="button" class="tf-horario__quitar" title="Quitar franja"
                                aria-label="Quitar franja">
                            <i class="bi bi-dash-lg" aria-hidden="true"></i>
                        </button>
                    </td>
                </tr>
            </template>
        @endunless

    </form>

    @include('diario._modal_imprimir')

@endsection

@push('scripts')
    {{-- Datepicker de Bootstrap por CDN, con su traducción al español --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap-datepicker@1.10.1/dist/js/bootstrap-datepicker.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap-datepicker@1.10.1/dist/locales/bootstrap-datepicker.es.min.js"></script>

    {{-- Funciones de este formulario: fecha/día, filas, checks y guardado --}}
    <script src="{{ asset('js/script.js') }}?v={{ filemtime(public_path('js/script.js')) }}"></script>

    {{-- El gráfico del día: se dibuja solo y se rehace con cada cambio del horario --}}
    <script src="{{ asset('js/grafico.js') }}"></script>
@endpush
