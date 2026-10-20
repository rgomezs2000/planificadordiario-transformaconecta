<?php

namespace Tests\Feature;

use App\Models\Notificacion;
use App\Notificaciones\BuzonDual;
use App\Notificaciones\BuzonFirestore;
use App\Notificaciones\BuzonMysql;
use App\Notificaciones\Firestore\ClienteFirestore;
use App\Notificaciones\Firestore\ValoresFirestore;
use App\Notificaciones\GeneradorDeNotificaciones;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * El espejo NoSQL sin salir a internet: se falsifica la API REST de Firestore
 * y se comprueba que el cliente y el buzón arman bien las llamadas y leen
 * bien las respuestas.
 */
class NotificacionesFirestoreTest extends TestCase
{
    use RefreshDatabase;

    private const UUID = '60c84b34-bae3-4b5c-8a34-10e870c91f66';

    /* ======================================================================
     |  Cliente y buzón
     ====================================================================== */

    public function test_el_buzon_firestore_lee_y_presenta_los_documentos(): void
    {
        Http::fake([
            'firestore.googleapis.com/*' => Http::response([
                'documents' => [$this->documento(self::UUID)],
            ], 200),
        ]);

        $buzon = new BuzonFirestore($this->clienteFalso());
        $notificaciones = $buzon->listar();

        $this->assertTrue($buzon->disponible());
        $this->assertCount(1, $notificaciones);
        $this->assertSame('Título de Firestore', $notificaciones[0]['titulo']);
        $this->assertSame('20 oct 2026', $notificaciones[0]['fecha_corta']);
        $this->assertSame('10:30', $notificaciones[0]['hora']);
        $this->assertFalse($notificaciones[0]['leida']);
        $this->assertSame(1, $notificaciones[0]['meta']['ranura']);
    }

    public function test_marcar_leida_actualiza_solo_los_campos_de_estado(): void
    {
        Http::fake(function ($request) {
            return Http::response(
                $this->documento(self::UUID, $request->method() === 'PATCH'),
                200
            );
        });

        $buzon = new BuzonFirestore($this->clienteFalso());

        $this->assertTrue($buzon->marcarLeida(self::UUID));

        Http::assertSent(function ($request) {
            return $request->method() === 'PATCH'
                && str_contains($request->url(), 'updateMask.fieldPaths=leida_en')
                && isset($request['fields']['leida_en']['timestampValue']);
        });
    }

    public function test_el_buzon_firestore_cuenta_y_filtra(): void
    {
        Http::fake([
            'firestore.googleapis.com/*' => Http::response([
                'documents' => [
                    $this->documento(self::UUID),
                    $this->documento('7b2c9d41-6e3f-4a58-9c72-2d1e0f3a4b65', true),
                ],
            ], 200),
        ]);

        $buzon = new BuzonFirestore($this->clienteFalso());

        $this->assertSame(['total' => 2, 'no_leidas' => 1, 'leidas' => 1], $buzon->contar());
        $this->assertCount(1, $buzon->listar(['estado' => 'no_leidas']));
        $this->assertCount(1, $buzon->listar(['estado' => 'leidas']));
        $this->assertCount(2, $buzon->listar(['q' => 'firestore']));
    }

    /* ======================================================================
     |  Escritura doble
     ====================================================================== */

    public function test_el_espejo_manda_las_notificaciones_en_un_solo_commit(): void
    {
        Http::fake([
            'firestore.googleapis.com/*' => Http::response([
                'writeResults' => [['updateTime' => '2026-10-20T14:30:00Z']],
            ], 200),
        ]);

        $notificacion = $this->notificacionDePrueba();
        $dual = $this->buzonDual($this->clienteFalso());

        $this->assertTrue($dual->espejar($notificacion));
        $this->assertNotNull($notificacion->fresh()->sincronizada_en);

        Http::assertSent(function ($request) use ($notificacion) {
            $escritura = ($request['writes'] ?? [])[0] ?? [];

            return str_ends_with($request->url(), ':commit')
                && ($escritura['update']['name'] ?? '') ===
                    'projects/planificador-prueba/databases/(default)/documents/notificaciones/'.$notificacion->uuid
                && ($escritura['update']['fields']['titulo']['stringValue'] ?? null) === 'Prueba de escritorio';
        });
    }

    public function test_marcar_en_firestore_tambien_marca_en_mysql(): void
    {
        Http::fake(function ($request) {
            return Http::response(
                $this->documento(self::UUID, $request->method() === 'PATCH'),
                200
            );
        });

        $notificacion = $this->notificacionDePrueba(self::UUID);
        $dual = $this->buzonDual($this->clienteFalso());

        $this->assertTrue($dual->marcarLeidaDesdeFirestore(self::UUID));
        $this->assertNotNull($notificacion->fresh()->leida_en);
    }

    /* ======================================================================
     |  Auxiliares
     ====================================================================== */

    /**
     * El cliente real no se puede usar en pruebas porque google/auth saldría a
     * pedir el token. Este doble responde con un token fijo y se salta el
     * archivo de credenciales; el resto de la lógica (URL, commit, PATCH) es
     * la misma que en producción.
     */
    private function clienteFalso(): ClienteFirestore
    {
        return new class extends ClienteFirestore
        {
            public function __construct()
            {
                parent::__construct(null, 'planificador-prueba', 'notificaciones', 5);
            }

            public function habilitado(): bool
            {
                return true;
            }

            public function configurado(): bool
            {
                return true;
            }

            public function proyecto(): string
            {
                return 'planificador-prueba';
            }

            protected function token(bool $forzar = false): string
            {
                return 'token-de-prueba';
            }
        };
    }

    private function buzonDual(ClienteFirestore $cliente): BuzonDual
    {
        return new BuzonDual(
            new BuzonMysql,
            new BuzonFirestore($cliente),
            $cliente,
            app(GeneradorDeNotificaciones::class),
        );
    }

    private function notificacionDePrueba(string $uuid = self::UUID): Notificacion
    {
        return Notificacion::query()->create([
            'uuid' => $uuid,
            'clave' => 'prueba:'.$uuid,
            'tipo' => 'objetivo',
            'titulo' => 'Prueba de escritorio',
            'mensaje' => 'Mensaje de la prueba.',
            'prioridad' => 'media',
            'fecha_referencia' => now()->toDateString(),
            'fecha_hora' => now(),
            'meta' => ['ranura' => 1],
        ]);
    }

    /** Un documento de Firestore con los mismos campos que guarda el espejo. */
    private function documento(string $uuid, bool $leida = false): array
    {
        return [
            'name' => 'projects/planificador-prueba/databases/(default)/documents/notificaciones/'.$uuid,
            'fields' => ValoresFirestore::aCampos([
                'uuid' => $uuid,
                'clave' => 'prueba:'.$uuid,
                'tipo' => 'objetivo',
                'titulo' => 'Título de Firestore',
                'mensaje' => 'Mensaje de Firestore',
                'prioridad' => 'alta',
                'fecha_referencia' => '2026-10-20',
                'fecha_hora' => CarbonImmutable::parse('2026-10-20 10:30:00', 'America/Caracas'),
                'leida_en' => $leida
                    ? CarbonImmutable::parse('2026-10-20 11:00:00', 'America/Caracas')
                    : null,
                'descartada_en' => null,
                'daily_plan_id' => 12,
                'plan_goal_id' => null,
                'schedule_entry_id' => null,
                'meta' => ['ranura' => 1],
                'creado_en' => CarbonImmutable::parse('2026-10-20 10:35:00', 'America/Caracas'),
                'actualizado_en' => CarbonImmutable::parse('2026-10-20 10:35:00', 'America/Caracas'),
            ]),
        ];
    }
}
