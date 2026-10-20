<?php

namespace App\Notificaciones\Firestore;

use Carbon\CarbonImmutable;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Cliente mínimo de Firestore por la API REST.
 *
 * El proyecto no instala google/cloud-firestore: esa versión exige ext-grpc y
 * no es compatible con el PHP 8.2 del servidor. Como la aplicación ya tiene la
 * librería google/auth (la que usa el SDK de Firebase para autenticarse), el
 * cliente pide un token de servicio y llama directamente a
 * firestore.googleapis.com. Así el espejo NoSQL funciona sin tocar Composer.
 *
 * La colección usa el uuid de MySQL como id de documento, de modo que una
 * notificación es la misma en los dos motores.
 */
class ClienteFirestore
{
    /** Alcance de la API de Firestore. */
    public const ALCANCE = 'https://www.googleapis.com/auth/datastore';

    /** Base de la API REST. */
    public const BASE = 'https://firestore.googleapis.com/v1/projects/%s/databases/(default)/documents';

    protected ?string $tokenActual = null;

    protected ?CarbonImmutable $tokenVence = null;

    protected ?array $credencialesLeidas = null;

    public function __construct(
        protected ?string $rutaConfig = null,
        protected ?string $proyectoConfig = null,
        protected ?string $coleccionConfig = null,
        protected ?int $tiempoEsperaConfig = null,
    ) {}

    /* ======================================================================
     |  Configuración
     ====================================================================== */

    public function habilitado(): bool
    {
        return (bool) config('firebase.habilitado', true);
    }

    public function configurado(): bool
    {
        try {
            return is_file($this->rutaCredenciales())
                && $this->proyecto() !== ''
                && $this->coleccion() !== '';
        } catch (Throwable) {
            return false;
        }
    }

    public function proyecto(): string
    {
        $proyecto = $this->proyectoConfig ?? config('firebase.proyecto');

        if (filled($proyecto)) {
            return trim((string) $proyecto);
        }

        try {
            return trim((string) ($this->credenciales()['project_id'] ?? ''));
        } catch (Throwable) {
            return '';
        }
    }

    public function coleccion(): string
    {
        $coleccion = $this->coleccionConfig ?? config('firebase.coleccion_notificaciones', 'notificaciones');

        return trim((string) $coleccion) !== '' ? trim((string) $coleccion) : 'notificaciones';
    }

    /** Ruta absoluta del JSON de credenciales. */
    public function rutaCredenciales(): string
    {
        $ruta = (string) ($this->rutaConfig ?? config('firebase.credenciales', 'firebase-credentials.json'));
        $ruta = trim($ruta) !== '' ? trim($ruta) : 'firebase-credentials.json';

        // Una ruta absoluta (C:\... o /...) se respeta; una relativa se resuelve
        // desde la raíz del proyecto.
        if (str_starts_with($ruta, '/') || str_starts_with($ruta, '\\')
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $ruta) === 1) {
            return $ruta;
        }

        return base_path($ruta);
    }

    /** El contenido del JSON, leído una sola vez por petición. */
    protected function credenciales(): array
    {
        if ($this->credencialesLeidas !== null) {
            return $this->credencialesLeidas;
        }

        $ruta = $this->rutaCredenciales();

        if (! is_file($ruta)) {
            throw new ErrorFirestore(
                'No se encontró el archivo de credenciales de Firebase (firebase-credentials.json) '
                .'en la raíz del proyecto.'
            );
        }

        $contenido = json_decode((string) file_get_contents($ruta), true);

        if (! is_array($contenido)
            || blank($contenido['private_key'] ?? null)
            || blank($contenido['client_email'] ?? null)) {
            throw new ErrorFirestore(
                'El archivo firebase-credentials.json no tiene una cuenta de servicio válida.'
            );
        }

        return $this->credencialesLeidas = $contenido;
    }

    /* ======================================================================
     |  Operaciones
     ====================================================================== */

    /**
     * Lista los documentos de la colección, del más nuevo al más viejo.
     *
     * @return array<int, array{id: string, datos: array<string, mixed>}>
     */
    public function listar(int $limite = 300): array
    {
        $limite = max(1, $limite);
        $documentos = [];
        $pagina = null;

        do {
            $consulta = [
                'pageSize' => min(300, max(1, $limite - count($documentos))),
                'orderBy' => 'fecha_hora desc',
            ];

            if (filled($pagina)) {
                $consulta['pageToken'] = $pagina;
            }

            $respuesta = $this->pedir(
                'GET',
                $this->rutaColeccion().'?'.http_build_query($consulta, '', '&', PHP_QUERY_RFC3986)
            );

            foreach ((array) ($respuesta['documents'] ?? []) as $documento) {
                if (! is_array($documento)) {
                    continue;
                }

                $documentos[] = $this->documento($documento);

                if (count($documentos) >= $limite) {
                    break;
                }
            }

            $pagina = $respuesta['nextPageToken'] ?? null;
        } while (filled($pagina) && count($documentos) < $limite);

        return $documentos;
    }

    /** @return array{id: string, datos: array<string, mixed>}|null */
    public function obtener(string $id): ?array
    {
        try {
            $respuesta = $this->pedir('GET', $this->rutaDocumento($id));
        } catch (ErrorFirestore $e) {
            if ($e->esNoEncontrado()) {
                return null;
            }

            throw $e;
        }

        return isset($respuesta['name']) ? $this->documento($respuesta) : null;
    }

    /**
     * Crea el documento con el id indicado. Si ya existe, lo actualiza.
     *
     * @return array{id: string, datos: array<string, mixed>}
     */
    public function crear(string $id, array $datos): array
    {
        $ruta = $this->rutaColeccion().'?'.http_build_query(
            ['documentId' => $id],
            '',
            '&',
            PHP_QUERY_RFC3986
        );

        try {
            $respuesta = $this->pedir('POST', $ruta, ['fields' => ValoresFirestore::aCampos($datos)]);
        } catch (ErrorFirestore $e) {
            if ($e->esConflicto()) {
                return $this->actualizar($id, $datos);
            }

            throw $e;
        }

        return $this->documento($respuesta);
    }

    /**
     * Actualiza sólo los campos enviados.
     *
     * @return array{id: string, datos: array<string, mixed>}
     */
    public function actualizar(string $id, array $datos): array
    {
        $campos = ValoresFirestore::aCampos($datos);
        $mascara = [];

        foreach (array_keys($campos) as $campo) {
            $mascara[] = 'updateMask.fieldPaths='.rawurlencode((string) $campo);
        }

        $ruta = $this->rutaDocumento($id).($mascara === [] ? '' : '?'.implode('&', $mascara));
        $respuesta = $this->pedir('PATCH', $ruta, ['fields' => $campos]);

        return $this->documento($respuesta);
    }

    public function eliminar(string $id): void
    {
        try {
            $this->pedir('DELETE', $this->rutaDocumento($id));
        } catch (ErrorFirestore $e) {
            if (! $e->esNoEncontrado()) {
                throw $e;
            }
        }
    }

    /**
     * Crea o actualiza varios documentos en una sola llamada (:commit).
     *
     * La sincronización puede producir una decena de avisos de golpe; hacer un
     * PATCH por cada uno volvería lento el sondeo de la campanita. Firestore
     * acepta hasta 500 escrituras por commit y acá se respeta ese límite.
     *
     * @param  array<int, array{id: string, datos: array<string, mixed>}>  $documentos
     */
    public function actualizarVarios(array $documentos): void
    {
        foreach (array_chunk($documentos, 500) as $lote) {
            $escrituras = [];

            foreach ($lote as $documento) {
                $id = (string) ($documento['id'] ?? '');
                $datos = $documento['datos'] ?? null;

                if ($id === '' || ! is_array($datos)) {
                    continue;
                }

                $campos = ValoresFirestore::aCampos($datos);

                $escrituras[] = [
                    'update' => [
                        'name' => $this->nombreDocumento($id),
                        'fields' => $campos,
                    ],
                    'updateMask' => ['fieldPaths' => array_keys($campos)],
                ];
            }

            if ($escrituras === []) {
                continue;
            }

            $this->pedir('POST', ':commit', ['writes' => $escrituras]);
        }
    }

    /**
     * Diagnóstico de conexión: lo que muestra el buzón para indicar si el
     * espejo NoSQL está en línea.
     *
     * @return array{habilitado: bool, configurado: bool, conectado: bool, proyecto: string, coleccion: string, latencia_ms: ?int, error: ?string}
     */
    public function probar(): array
    {
        $estado = [
            'habilitado' => $this->habilitado(),
            'configurado' => $this->configurado(),
            'conectado' => false,
            'proyecto' => $this->proyecto(),
            'coleccion' => $this->coleccion(),
            'latencia_ms' => null,
            'error' => null,
        ];

        if (! $estado['habilitado']) {
            $estado['error'] = 'El espejo de Firestore está deshabilitado (FIREBASE_HABILITADO=false).';

            return $estado;
        }

        if (! $estado['configurado']) {
            $estado['error'] = 'No se encontró el archivo de credenciales de Firebase en la raíz del proyecto.';

            return $estado;
        }

        $inicio = microtime(true);

        try {
            $this->pedir(
                'GET',
                $this->rutaColeccion().'?'.http_build_query(
                    ['pageSize' => 1],
                    '',
                    '&',
                    PHP_QUERY_RFC3986
                )
            );

            $estado['conectado'] = true;
            $estado['latencia_ms'] = (int) round((microtime(true) - $inicio) * 1000);
        } catch (Throwable $e) {
            $estado['error'] = Str::limit($e->getMessage(), 300);
        }

        return $estado;
    }

    /* ======================================================================
     |  Transporte
     ====================================================================== */

    /**
     * Una llamada a la API. Reintenta una vez si el token venció.
     *
     * @return array<string, mixed>
     */
    protected function pedir(string $metodo, string $ruta, ?array $cuerpo = null): array
    {
        if (! $this->habilitado()) {
            throw new ErrorFirestore('El espejo de Firestore está deshabilitado.');
        }

        if (! $this->configurado()) {
            throw new ErrorFirestore(
                'Firestore no está configurado: falta firebase-credentials.json o el proyecto.'
            );
        }

        $url = $this->base().'/'.ltrim($ruta, '/');
        $respuesta = $this->enviar($metodo, $url, $cuerpo, $this->token());

        if ($respuesta->status() === 401) {
            $respuesta = $this->enviar($metodo, $url, $cuerpo, $this->token(true));
        }

        if (! $respuesta->successful()) {
            throw ErrorFirestore::deRespuesta($metodo, $url, $respuesta);
        }

        $json = $respuesta->json();

        return is_array($json) ? $json : [];
    }

    protected function enviar(string $metodo, string $url, ?array $cuerpo, string $token)
    {
        $peticion = Http::withToken($token)
            ->acceptJson()
            ->timeout(max(5, (int) ($this->tiempoEsperaConfig ?? config('firebase.tiempo_espera', 15))));

        return $peticion->send($metodo, $url, $cuerpo === null ? [] : ['json' => $cuerpo]);
    }

    /** Token de la cuenta de servicio, cacheado hasta poco antes de vencer. */
    protected function token(bool $forzar = false): string
    {
        if (! $forzar && filled($this->tokenActual) && $this->tokenVence?->isFuture()) {
            return (string) $this->tokenActual;
        }

        try {
            $credenciales = new ServiceAccountCredentials(self::ALCANCE, $this->credenciales());
            $token = $credenciales->fetchAuthToken();
        } catch (Throwable $e) {
            throw new ErrorFirestore(
                'No se pudo autenticar con Firebase: '.Str::limit($e->getMessage(), 200),
                0,
                $e
            );
        }

        if (! is_array($token) || blank($token['access_token'] ?? null)) {
            throw new ErrorFirestore('Firebase no devolvió un token de acceso válido.');
        }

        $this->tokenActual = (string) $token['access_token'];
        $this->tokenVence = CarbonImmutable::now()->addSeconds(
            max(60, (int) ($token['expires_in'] ?? 3600) - 60)
        );

        return $this->tokenActual;
    }

    /* ======================================================================
     |  Rutas y documentos
     ====================================================================== */

    protected function base(): string
    {
        return sprintf(self::BASE, rawurlencode($this->proyecto()));
    }

    protected function rutaColeccion(): string
    {
        return rawurlencode($this->coleccion());
    }

    protected function rutaDocumento(string $id): string
    {
        return $this->rutaColeccion().'/'.rawurlencode($id);
    }

    /** El nombre completo que espera :commit (sin codificar como URL). */
    protected function nombreDocumento(string $id): string
    {
        return 'projects/'.$this->proyecto().'/databases/(default)/documents/'
            .$this->coleccion().'/'.$id;
    }

    /**
     * @param  array<string, mixed>  $documento
     * @return array{id: string, datos: array<string, mixed>}
     */
    protected function documento(array $documento): array
    {
        $nombre = (string) ($documento['name'] ?? '');

        return [
            'id' => $nombre !== '' ? Str::afterLast($nombre, '/') : '',
            'datos' => ValoresFirestore::desdeCampos($documento['fields'] ?? []),
        ];
    }
}
