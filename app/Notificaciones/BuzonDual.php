<?php

namespace App\Notificaciones;

use App\Models\DailyPlan;
use App\Models\Notificacion;
use App\Notificaciones\Contratos\Buzon;
use App\Notificaciones\Firestore\ClienteFirestore;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * El buzón de la aplicación: escribe en los dos motores y lee desde MySQL.
 *
 * MySQL es la fuente principal (la tabla notificaciones, con sus migraciones y
 * su modelo); Firestore es el espejo NoSQL. Cada alta, lectura o archivado se
 * replica con el mismo uuid, así las dos bandejas cuentan la misma historia.
 *
 * Si Firestore falla, el buzón no se rompe: la fila SQL guarda el error en
 * error_firestore, la pantalla avisa que el espejo está caído y la próxima
 * sincronización vuelve a intentarlo.
 */
class BuzonDual implements Buzon
{
    /** Resultado de la última escritura en Firestore. */
    protected array $ultimoEspejo = [
        'intentado' => false,
        'conectado' => false,
        'actualizadas' => 0,
        'error' => null,
    ];

    public function __construct(
        protected BuzonMysql $mysql,
        protected BuzonFirestore $firestore,
        protected ClienteFirestore $cliente,
        protected GeneradorDeNotificaciones $generador,
    ) {}

    public function nombre(): string
    {
        return 'dual';
    }

    /* ======================================================================
     |  Generación y espejo
     ====================================================================== */

    /**
     * Evalúa las reglas del día, crea lo que falte, resuelve lo que ya se
     * cumplió y replica en Firestore.
     *
     * @return array{creadas: int, existentes: int, replicadas: int, resueltas: int, omitida: bool, firestore: array, errores: array}
     */
    public function sincronizar(?CarbonImmutable $ahora = null): array
    {
        $this->resetUltimoEspejo();

        if (! config('notificaciones.habilitadas', true)) {
            return [
                'creadas' => 0,
                'existentes' => 0,
                'replicadas' => 0,
                'resueltas' => 0,
                'omitida' => true,
                'motivo' => 'Las notificaciones están deshabilitadas.',
                'firestore' => $this->estadoFirestoreCacheado(),
                'errores' => [],
            ];
        }

        // Lo que ya se cumplió deja de contar como pendiente aunque su aviso
        // ya estuviera creado; se marca leído y se replica el cambio.
        $resueltas = $this->resolverObsoletas($ahora);

        $creadas = 0;
        $existentes = 0;
        $errores = [];
        $porEspejar = [];

        foreach ($this->generador->generar($ahora) as $candidata) {
            try {
                [$notificacion, $nueva] = Notificacion::desdeCandidata($candidata);
            } catch (Throwable $e) {
                report($e);
                $errores[] = [
                    'clave' => $candidata->clave,
                    'mensaje' => Str::limit($e->getMessage(), 200),
                ];

                continue;
            }

            if ($nueva) {
                $creadas++;
            } else {
                $existentes++;
            }

            // Una notificación ya replicada no se vuelve a escribir en cada
            // consulta del navegador; sólo se espeja lo nuevo o lo que falló.
            $pendiente = $nueva || $notificacion->sincronizada_en === null || $notificacion->error_firestore !== null;

            if ($pendiente) {
                $porEspejar[$notificacion->uuid] = $notificacion;
            }
        }

        $huboFallo = $porEspejar !== [] && ! $this->espejarLote(array_values($porEspejar));
        $replicadas = $huboFallo ? 0 : $this->replicarPendientes();

        if ($this->ultimoEspejo['conectado']) {
            Cache::put('notificaciones:ultima-sincronizacion', now()->toIso8601String(), 3600);
        }

        return [
            'creadas' => $creadas,
            'existentes' => $existentes,
            'replicadas' => $replicadas,
            'resueltas' => $resueltas,
            'omitida' => false,
            'firestore' => $this->estadoFirestoreCacheado(),
            'errores' => $errores,
        ];
    }

    /**
     * La misma sincronización, pero como máximo una vez cada N segundos.
     *
     * La usa el sondeo de la campanita: aunque el navegador pregunte cada 15
     * segundos, las reglas se evalúan cada minuto. El comando programado no
     * pasa por aquí: todas sus corridas generan.
     */
    public function sincronizarSiToca(?CarbonImmutable $ahora = null): array
    {
        if (! config('notificaciones.habilitadas', true)) {
            return [
                'creadas' => 0,
                'existentes' => 0,
                'replicadas' => 0,
                'resueltas' => 0,
                'omitida' => true,
                'motivo' => 'Las notificaciones están deshabilitadas.',
                'firestore' => $this->estadoFirestoreCacheado(),
                'errores' => [],
            ];
        }

        $segundos = max(5, (int) config('notificaciones.segundos_entre_generaciones', 55));

        try {
            $primera = Cache::add('notificaciones:sincronizacion', now()->timestamp, $segundos);
        } catch (Throwable $e) {
            // Si la caché falla, se prefiere generar de más a no avisar.
            report($e);
            $primera = true;
        }

        if (! $primera) {
            return [
                'creadas' => 0,
                'existentes' => 0,
                'replicadas' => 0,
                'resueltas' => 0,
                'omitida' => true,
                'motivo' => 'Todavía no toca volver a evaluar las reglas.',
                'firestore' => $this->estadoFirestoreCacheado(),
                'errores' => [],
            ];
        }

        return $this->sincronizar($ahora);
    }

    /**
     * Marca como leídos los avisos que dejaron de tener sentido: el diario
     * ausente cuando el diario ya existe, y los objetivos o franjas que se
     * marcaron después de haber sido recordados.
     *
     * Devuelve cuántos avisos se resolvieron.
     */
    protected function resolverObsoletas(?CarbonImmutable $ahora = null): int
    {
        $hoy = ($ahora ?? CarbonImmutable::now(config('app.timezone')))->startOfDay();

        $plan = DailyPlan::query()
            ->whereDate('plan_date', $hoy->toDateString())
            ->with(['goals', 'scheduleEntries'])
            ->first();

        if (! $plan) {
            return 0;
        }

        $ids = Notificacion::query()
            ->vigentes()
            ->noLeidas()
            ->where('tipo', Candidata::TIPO_DIARIO_AUSENTE)
            ->whereDate('fecha_referencia', $hoy->toDateString())
            ->pluck('id')
            ->all();

        $objetivosHechos = $plan->goals->where('is_done', true)->pluck('id')->all();

        if ($objetivosHechos !== []) {
            $ids = array_merge($ids, Notificacion::query()
                ->vigentes()
                ->noLeidas()
                ->whereIn('plan_goal_id', $objetivosHechos)
                ->pluck('id')
                ->all());
        }

        $franjasHechas = $plan->scheduleEntries->where('is_done', true)->pluck('id')->all();

        if ($franjasHechas !== []) {
            $ids = array_merge($ids, Notificacion::query()
                ->vigentes()
                ->noLeidas()
                ->whereIn('schedule_entry_id', $franjasHechas)
                ->pluck('id')
                ->all());
        }

        $ids = array_values(array_unique($ids));

        if ($ids === []) {
            return 0;
        }

        $ahora = now();

        Notificacion::query()->whereIn('id', $ids)->update([
            'sincronizada_en' => null,
            'updated_at' => $ahora,
        ]);

        Notificacion::query()
            ->whereIn('id', $ids)
            ->whereNull('leida_en')
            ->update(['leida_en' => $ahora]);

        // Se releen para espejar el estado final (con leida_en ya escrito).
        $resueltas = Notificacion::query()->whereIn('id', $ids)->get();

        $this->espejarLote($resueltas->all());

        return $resueltas->count();
    }

    /** Replica las filas que quedaron pendientes (sin Firestore o con error). */
    public function replicarPendientes(int $limite = 100): int
    {
        if (! $this->firestore->disponible()) {
            return 0;
        }

        $pendientes = Notificacion::pendientesDeEspejo($limite)->all();

        if ($pendientes === []) {
            return 0;
        }

        return $this->espejarLote($pendientes) ? count($pendientes) : 0;
    }

    /**
     * Vuelve a copiar todas las notificaciones vigentes a Firestore. Es lo que
     * hace el botón "Sincronizar" de la bandeja NoSQL cuando alguien borró la
     * colección o necesita reconstruirla.
     */
    public function replicarTodo(int $limite = 500): int
    {
        if (! $this->firestore->disponible()) {
            return 0;
        }

        $replicadas = 0;

        $notificaciones = Notificacion::query()
            ->vigentes()
            ->orderByDesc('fecha_hora')
            ->limit(max(1, $limite))
            ->get();

        foreach ($notificaciones->chunk(200) as $lote) {
            if ($this->espejarLote($lote->values()->all())) {
                $replicadas += $lote->count();
            }
        }

        return $replicadas;
    }

    /** Copia una notificación a Firestore y anota el resultado en la fila. */
    public function espejar(Notificacion $notificacion): bool
    {
        return $this->espejarLote([$notificacion]);
    }

    /**
     * Copia un lote de notificaciones en una sola llamada a Firestore y deja
     * anotado en MySQL el resultado (fecha de sincronización o error).
     *
     * @param  array<int, Notificacion>  $notificaciones
     */
    public function espejarLote(array $notificaciones): bool
    {
        $notificaciones = array_values(array_filter(
            $notificaciones,
            fn (mixed $n) => $n instanceof Notificacion
        ));

        if ($notificaciones === []) {
            return true;
        }

        $uuids = array_map(fn (Notificacion $n) => $n->uuid, $notificaciones);

        if (! $this->firestore->disponible()) {
            $this->ultimoEspejo = [
                'intentado' => true,
                'conectado' => false,
                'actualizadas' => 0,
                'error' => 'Firestore no está disponible: revisa firebase-credentials.json y FIREBASE_HABILITADO.',
            ];

            return false;
        }

        try {
            $this->cliente->actualizarVarios(array_map(
                fn (Notificacion $n) => ['id' => $n->uuid, 'datos' => $n->aDatosFirestore()],
                $notificaciones
            ));

            Notificacion::query()->whereIn('uuid', $uuids)->update([
                'sincronizada_en' => now(),
                'error_firestore' => null,
                'updated_at' => now(),
            ]);

            $this->ultimoEspejo = [
                'intentado' => true,
                'conectado' => true,
                'actualizadas' => count($notificaciones),
                'error' => null,
            ];

            return true;
        } catch (Throwable $e) {
            report($e);

            $mensaje = Str::limit($e->getMessage(), 900);

            Notificacion::query()->whereIn('uuid', $uuids)->update([
                'sincronizada_en' => null,
                'error_firestore' => $mensaje,
                'updated_at' => now(),
            ]);

            $this->ultimoEspejo = [
                'intentado' => true,
                'conectado' => false,
                'actualizadas' => 0,
                'error' => $mensaje,
            ];

            return false;
        }
    }

    /* ======================================================================
     |  Lectura (MySQL manda)
     ====================================================================== */

    public function listar(array $filtros = []): array
    {
        return $this->mysql->listar($filtros);
    }

    public function contar(): array
    {
        return $this->mysql->contar();
    }

    public function buscar(string $uuid): ?array
    {
        return $this->mysql->buscar($uuid);
    }

    /* ======================================================================
     |  Cambios de estado (se replican en Firestore)
     ====================================================================== */

    public function marcarLeida(string $uuid): bool
    {
        $ok = $this->mysql->marcarLeida($uuid);

        if ($ok) {
            $this->espejarPorUuid($uuid);
        }

        return $ok;
    }

    public function marcarVarias(array $uuids): int
    {
        $marcadas = $this->mysql->marcarVarias($uuids);

        if ($marcadas > 0) {
            $this->espejarVarias($uuids);
        }

        return $marcadas;
    }

    public function descartar(string $uuid): bool
    {
        $ok = $this->mysql->descartar($uuid);

        if ($ok) {
            $this->espejarPorUuid($uuid);
        }

        return $ok;
    }

    public function descartarVarias(array $uuids): int
    {
        $descartadas = $this->mysql->descartarVarias($uuids);

        if ($descartadas > 0) {
            $this->espejarVarias($uuids);
        }

        return $descartadas;
    }

    /**
     * Marca como leída desde la bandeja de Firestore y refleja el cambio en
     * MySQL. Así el contador de la campanita (que lee MySQL) queda al día.
     */
    public function marcarLeidaDesdeFirestore(string $uuid): bool
    {
        $ok = $this->firestore->marcarLeida($uuid);

        if ($ok) {
            Notificacion::marcarVarias([$uuid]);
        }

        return $ok;
    }

    /** Archiva desde la bandeja de Firestore y refleja el cambio en MySQL. */
    public function descartarDesdeFirestore(string $uuid): bool
    {
        $ok = $this->firestore->descartar($uuid);

        if ($ok) {
            Notificacion::descartarVarias([$uuid]);
        }

        return $ok;
    }

    /** Marca un lote desde la bandeja de Firestore y lo refleja en MySQL. */
    public function marcarVariasDesdeFirestore(array $uuids): int
    {
        $uuids = Notificacion::limpiarUuids($uuids);
        $marcadas = 0;

        foreach ($uuids as $uuid) {
            if ($this->firestore->marcarLeida($uuid)) {
                $marcadas++;
            }
        }

        if ($uuids !== []) {
            Notificacion::marcarVarias($uuids);
        }

        return $marcadas;
    }

    /** Archiva un lote desde la bandeja de Firestore y lo refleja en MySQL. */
    public function descartarVariasDesdeFirestore(array $uuids): int
    {
        $uuids = Notificacion::limpiarUuids($uuids);
        $descartadas = 0;

        foreach ($uuids as $uuid) {
            if ($this->firestore->descartar($uuid)) {
                $descartadas++;
            }
        }

        if ($uuids !== []) {
            Notificacion::descartarVarias($uuids);
        }

        return $descartadas;
    }

    /* ======================================================================
     |  Diagnóstico
     ====================================================================== */

    public function estado(): array
    {
        return [
            'mysql' => $this->mysql->estado(),
            'firestore' => $this->estadoFirestore(),
            'ultima_sincronizacion' => Cache::get('notificaciones:ultima-sincronizacion'),
        ];
    }

    /**
     * Estado de Firestore, con caché para que el sondeo de la campanita no
     * golpee la API cada 15 segundos.
     */
    public function estadoFirestore(bool $forzar = false): array
    {
        $llave = 'notificaciones:firestore:estado';

        if ($forzar) {
            Cache::forget($llave);
        }

        try {
            return Cache::remember(
                $llave,
                max(30, (int) config('firebase.segundos_diagnostico', 300)),
                fn () => $this->firestore->estado()
            );
        } catch (Throwable $e) {
            return [
                'fuente' => 'firestore',
                'etiqueta' => 'Firestore',
                'motor' => 'NoSQL',
                'habilitado' => false,
                'configurado' => false,
                'conectado' => false,
                'error' => Str::limit($e->getMessage(), 300),
            ];
        }
    }

    /**
     * Estado de Firestore sólo si ya se comprobó antes. Lo usa el sondeo de la
     * campanita para no esperar una llamada a la API en cada consulta.
     *
     * @return array<string, mixed>
     */
    public function estadoFirestoreCacheado(): array
    {
        $estado = Cache::get('notificaciones:firestore:estado');

        return is_array($estado) ? $estado : [];
    }

    /** Lo que pasó con la última escritura NoSQL, para responder al navegador. */
    public function ultimoEspejo(): array
    {
        return $this->ultimoEspejo;
    }

    /* ======================================================================
     |  Internos
     ====================================================================== */

    /** Copia el estado actual de una fila SQL (aunque ya esté archivada). */
    protected function espejarPorUuid(string $uuid): void
    {
        $notificacion = Notificacion::query()->where('uuid', $uuid)->first();

        if ($notificacion) {
            $this->espejar($notificacion);
        }
    }

    /** Copia el estado actual de un lote de filas SQL. */
    protected function espejarVarias(array $uuids): void
    {
        $notificaciones = Notificacion::query()
            ->whereIn('uuid', Notificacion::limpiarUuids($uuids))
            ->get()
            ->all();

        $this->espejarLote($notificaciones);

        if ($notificaciones === []) {
            $this->resetUltimoEspejo();
        }
    }

    protected function resetUltimoEspejo(): void
    {
        $this->ultimoEspejo = [
            'intentado' => false,
            'conectado' => false,
            'actualizadas' => 0,
            'error' => null,
        ];
    }
}
