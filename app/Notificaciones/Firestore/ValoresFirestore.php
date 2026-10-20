<?php

namespace App\Notificaciones\Firestore;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Traduce valores PHP al formato de la API REST de Firestore y de vuelta.
 *
 * Firestore no guarda JSON plano: cada campo es un objeto con su tipo
 * ("stringValue", "timestampValue", "mapValue"...). Esta clase concentra esa
 * traducción para que el cliente y los modelos trabajen con arreglos normales.
 *
 * Las fechas se guardan como timestamp en UTC y se devuelven en la zona de la
 * aplicación, así el navegador siempre recibe la hora local del planificador.
 */
final class ValoresFirestore
{
    /** Convierte un arreglo asociativo en el mapa de campos de Firestore. */
    public static function aCampos(array $datos): array
    {
        $campos = [];

        foreach ($datos as $nombre => $valor) {
            $campos[(string) $nombre] = self::aValor($valor);
        }

        return $campos;
    }

    /** Convierte el mapa de campos de Firestore en un arreglo asociativo. */
    public static function desdeCampos(mixed $campos): array
    {
        if (! is_array($campos)) {
            return [];
        }

        $datos = [];

        foreach ($campos as $nombre => $valor) {
            $datos[(string) $nombre] = self::desdeValor($valor);
        }

        return $datos;
    }

    /** Un valor PHP suelto en su objeto Value de Firestore. */
    public static function aValor(mixed $valor): array
    {
        // El orden importa: un Carbon también es DateTimeInterface y un entero
        // también es escalar.
        return match (true) {
            $valor === null => ['nullValue' => null],
            is_bool($valor) => ['booleanValue' => $valor],
            is_int($valor) => ['integerValue' => (string) $valor],
            is_float($valor) => ['doubleValue' => $valor],
            $valor instanceof DateTimeInterface => [
                'timestampValue' => CarbonImmutable::instance($valor)
                    ->setTimezone('UTC')
                    ->format('Y-m-d\TH:i:s.u\Z'),
            ],
            is_string($valor) => ['stringValue' => $valor],
            is_array($valor) => array_is_list($valor)
                ? ['arrayValue' => $valor === []
                    ? new \stdClass
                    : ['values' => array_map(self::aValor(...), $valor)]]
                : ['mapValue' => $valor === []
                    ? new \stdClass
                    : ['fields' => self::aCampos($valor)]],
            $valor instanceof \Stringable => ['stringValue' => (string) $valor],
            default => throw new InvalidArgumentException(
                'No se puede guardar en Firestore un valor de tipo '.get_debug_type($valor).'.'
            ),
        };
    }

    /** Un objeto Value de Firestore en su valor PHP. */
    public static function desdeValor(mixed $valor): mixed
    {
        if (! is_array($valor)) {
            return null;
        }

        return match (true) {
            array_key_exists('nullValue', $valor) => null,
            array_key_exists('booleanValue', $valor) => (bool) $valor['booleanValue'],
            array_key_exists('integerValue', $valor) => (int) $valor['integerValue'],
            array_key_exists('doubleValue', $valor) => (float) $valor['doubleValue'],
            array_key_exists('stringValue', $valor) => (string) $valor['stringValue'],
            array_key_exists('timestampValue', $valor) => self::fecha($valor['timestampValue']),
            array_key_exists('mapValue', $valor) => self::mapa($valor['mapValue']),
            array_key_exists('arrayValue', $valor) => self::lista($valor['arrayValue']),
            array_key_exists('referenceValue', $valor) => (string) $valor['referenceValue'],
            default => null,
        };
    }

    /** Una fecha de Firestore (RFC 3339 en UTC) en la zona de la aplicación. */
    private static function fecha(mixed $valor): ?CarbonImmutable
    {
        try {
            return CarbonImmutable::parse((string) $valor)->setTimezone(config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    /** Un mapValue vacío llega como objeto; uno con datos, como arreglo. */
    private static function mapa(mixed $mapValue): array
    {
        if ($mapValue instanceof \stdClass) {
            return [];
        }

        if (! is_array($mapValue)) {
            return [];
        }

        return self::desdeCampos($mapValue['fields'] ?? []);
    }

    /** Un arrayValue vacío llega como objeto; uno con datos, como arreglo. */
    private static function lista(mixed $arrayValue): array
    {
        if ($arrayValue instanceof \stdClass) {
            return [];
        }

        if (! is_array($arrayValue)) {
            return [];
        }

        return array_map(self::desdeValor(...), (array) ($arrayValue['values'] ?? []));
    }
}
