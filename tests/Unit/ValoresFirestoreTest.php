<?php

namespace Tests\Unit;

use App\Notificaciones\Firestore\ValoresFirestore;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * El codificador de Firestore: los valores PHP tienen que volver iguales
 * después de pasar por el formato de la API REST.
 */
class ValoresFirestoreTest extends TestCase
{
    public function test_codifica_los_tipos_de_firestore(): void
    {
        $campos = ValoresFirestore::aCampos([
            'texto' => 'Hola',
            'entero' => 3,
            'decimal' => 1.5,
            'si' => true,
            'nulo' => null,
            'fecha' => CarbonImmutable::parse('2026-10-20 10:30:00', 'America/Caracas'),
            'meta' => ['ranura' => 1, 'activo' => true],
            'lista' => ['a', 'b'],
        ]);

        $this->assertSame(['stringValue' => 'Hola'], $campos['texto']);
        $this->assertSame(['integerValue' => '3'], $campos['entero']);
        $this->assertSame(['doubleValue' => 1.5], $campos['decimal']);
        $this->assertSame(['booleanValue' => true], $campos['si']);
        $this->assertArrayHasKey('nullValue', $campos['nulo']);
        $this->assertSame('2026-10-20T14:30:00.000000Z', $campos['fecha']['timestampValue']);
        $this->assertSame('1', $campos['meta']['mapValue']['fields']['ranura']['integerValue']);
        $this->assertSame('b', $campos['lista']['arrayValue']['values'][1]['stringValue']);
    }

    public function test_vuelve_a_php_con_la_misma_informacion(): void
    {
        $original = [
            'texto' => 'Hola',
            'entero' => 3,
            'decimal' => 1.5,
            'si' => true,
            'nulo' => null,
            'fecha' => CarbonImmutable::parse('2026-10-20 10:30:00', 'America/Caracas'),
            'meta' => ['ranura' => 1, 'activo' => true, 'lista' => [1, 2]],
            'lista' => ['a', 'b'],
            'vacia' => [],
        ];

        // Se pasa por JSON para imitar lo que devuelve la API.
        $campos = json_decode(json_encode(ValoresFirestore::aCampos($original)), true);
        $vuelta = ValoresFirestore::desdeCampos($campos);

        $this->assertSame('Hola', $vuelta['texto']);
        $this->assertSame(3, $vuelta['entero']);
        $this->assertSame(1.5, $vuelta['decimal']);
        $this->assertTrue($vuelta['si']);
        $this->assertNull($vuelta['nulo']);
        $this->assertSame('2026-10-20 10:30:00', $vuelta['fecha']->format('Y-m-d H:i:s'));
        $this->assertSame(['ranura' => 1, 'activo' => true, 'lista' => [1, 2]], $vuelta['meta']);
        $this->assertSame(['a', 'b'], $vuelta['lista']);
        $this->assertSame([], $vuelta['vacia']);
    }

    public function test_una_fecha_invalida_no_rompe_la_lectura(): void
    {
        $this->assertNull(ValoresFirestore::desdeValor(['timestampValue' => 'no es una fecha']));
    }
}
