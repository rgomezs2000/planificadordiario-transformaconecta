@extends('layouts.app')

{{--
    Página de errores.

    La usan la ruta /error/{codigo} y el manejador de excepciones
    (bootstrap/app.php), así que este es el único diseño de error del sistema.
    Los datos vienen de App\Errores\CatalogoDeErrores: el código, su familia
    (300, 400 o 500), el título, la explicación, las salidas sugeridas, el icono
    y el color institucional que le toca.
--}}

@section('titulo', $error['codigo'].' · '.$error['titulo'].' · Mi Planificador Diario')

@section('contenido')

    <section class="tc-tarjeta tf-error tf-error--{{ $error['color'] }}">

        <span class="tf-error__familia">
            <i class="bi {{ $error['icono'] }}" aria-hidden="true"></i>
            {{ $error['familia'] }} · {{ $error['familia_titulo'] }}
        </span>

        <p class="tf-error__codigo">{{ $error['codigo'] }}</p>

        <h1 class="tf-error__titulo">{{ $error['titulo'] }}</h1>

        <p class="tf-error__mensaje">{{ $error['mensaje'] }}</p>

        <p class="tf-error__familia-texto">{{ $error['familia_explicacion'] }}</p>

        @if ($error['ajustado'])
            <p class="tf-error__nota">
                <i class="bi bi-info-circle" aria-hidden="true"></i>
                Se pidió el código
                <strong>{{ $error['codigo_solicitado'] ?? '—' }}</strong>, que no es un error HTTP
                de los que el sistema explica, así que se muestra el aviso general del servidor.
            </p>
        @endif

        @if (! empty($error['incidente']))
            <p class="tf-error__incidente">
                <i class="bi bi-hash" aria-hidden="true"></i>
                Código de incidente <strong>{{ $error['incidente'] }}</strong>
                <span>· el detalle quedó registrado en el log de errores del sistema</span>
            </p>
        @endif

        @if (! empty($error['sugerencias']))
            <h2 class="tf-error__subtitulo">Qué puedes hacer</h2>

            <ul class="tf-error__lista">
                @foreach ($error['sugerencias'] as $sugerencia)
                    <li>{{ $sugerencia }}</li>
                @endforeach
            </ul>
        @endif

        <div class="tf-error__acciones">
            <a class="tc-boton tc-boton--azul" href="{{ route('home') }}">
                <i class="bi bi-house-door" aria-hidden="true"></i> Volver al inicio
            </a>

            <a class="tc-boton tc-boton--contorno" href="{{ route('diario.listado') }}">
                <i class="bi bi-journal-text" aria-hidden="true"></i> Consultar mis diarios
            </a>

            <a class="tc-boton tc-boton--contorno" href="{{ route('diario.index') }}">
                <i class="bi bi-calendar-plus" aria-hidden="true"></i> Crear un diario
            </a>

            <button type="button" class="tc-boton tc-boton--turquesa" data-tf-reintentar>
                <i class="bi bi-arrow-clockwise" aria-hidden="true"></i> Reintentar
            </button>
        </div>

        <p class="tf-error__pie">
            Error HTTP {{ $error['codigo'] }} · {{ mb_strtoupper($error['familia_titulo']) }} ·
            Mi Planificador Diario
        </p>

    </section>

@endsection

@push('scripts')
    <script>
        // Reintentar vuelve a pedir la misma dirección; si el fallo fue pasajero,
        // la página se abre normalmente.
        document.querySelector('[data-tf-reintentar]')?.addEventListener('click', function () {
            window.location.reload();
        });
    </script>
@endpush
