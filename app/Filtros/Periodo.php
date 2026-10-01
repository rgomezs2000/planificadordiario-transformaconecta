<?php

namespace App\Filtros;

use App\Helpers\Helper;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;

/**
 * Filtro por período del listado de diarios.
 *
 * Convierte lo que se elige en pantalla —una semana, una quincena, un mes, un
 * trimestre, un semestre, un año o un rango de fechas— en un par de fechas para
 * consultar. Las semanas van de lunes a domingo, como la hoja impresa.
 */
class Periodo
{
    /** Los períodos que se pueden elegir. */
    public const TIPOS = ['semana', 'quincena', 'mes', 'trimestre', 'semestre', 'anio', 'rango'];

    /** El primer o el segundo tramo de un mes o de un año. */
    public const MITADES = [1 => 'Primera', 2 => 'Segunda'];

    /** Los trimestres. */
    public const TRIMESTRES = [1 => 'Primero', 2 => 'Segundo', 3 => 'Tercero', 4 => 'Cuarto'];

    /** Los semestres. */
    public const SEMESTRES = [1 => 'Primero', 2 => 'Segundo'];

    /**
     * El rango de fechas del período elegido, o null si no hay nada válido.
     *
     * @param  array<string, mixed>  $datos
     * @return array{inicio: Carbon, fin: Carbon, etiqueta: string}|null
     */
    public static function rango(?string $tipo, array $datos): ?array
    {
        return match ($tipo) {
            'semana' => self::semana($datos['semana'] ?? null),
            'quincena' => self::quincena($datos['mes'] ?? null, (int) ($datos['mitad'] ?? 1)),
            'mes' => self::mes($datos['mes'] ?? null),
            'trimestre' => self::trimestre((int) ($datos['anio'] ?? 0), (int) ($datos['trimestre'] ?? 0)),
            'semestre' => self::semestre((int) ($datos['anio'] ?? 0), (int) ($datos['semestre'] ?? 0)),
            'anio' => self::anio((int) ($datos['anio'] ?? 0)),
            'rango' => self::entreFechas($datos['desde'] ?? null, $datos['hasta'] ?? null),
            default => null,
        };
    }

    /** Etiqueta corta para mostrar qué período está filtrado. */
    public static function etiqueta(?array $rango): string
    {
        if (! $rango) {
            return '';
        }

        $inicio = $rango['inicio'];
        $fin = $rango['fin'];

        // Si es un solo día, se muestra esa fecha; si no, el par de fechas.
        if ($inicio->isSameDay($fin)) {
            return Helper::longDate($inicio, withWeekday: true);
        }

        return Helper::date($inicio).' al '.Helper::date($fin);
    }

    /* ------------------------------------------------------------------ */

    /** Una semana del calendario, de lunes a domingo. "2026-W40" */
    private static function semana(mixed $semana): ?array
    {
        if (! is_string($semana) || ! preg_match('/^(\d{4})-W(\d{1,2})$/', trim($semana), $partes)) {
            return null;
        }

        $anio = (int) $partes[1];
        $numero = (int) $partes[2];

        if ($numero < 1 || $numero > 53) {
            return null;
        }

        $inicio = Carbon::now()->setISODate($anio, $numero)->startOfDay();

        // setISODate ya devuelve el lunes de esa semana.
        return self::armar($inicio, $inicio->copy()->addDays(6));
    }

    /** Una quincena: la primera mitad de un mes o la segunda. */
    private static function quincena(mixed $mes, int $mitad): ?array
    {
        $rango = self::mes($mes);

        if (! $rango) {
            return null;
        }

        $primero = $rango['inicio'];
        $ultimo = $rango['fin'];

        return $mitad === 2
            ? self::armar($primero->copy()->day(16), $ultimo, 'Segunda quincena')
            : self::armar($primero, $primero->copy()->day(15), 'Primera quincena');
    }

    /** Un mes completo. "2026-09" */
    private static function mes(mixed $mes): ?array
    {
        if (! is_string($mes) || ! preg_match('/^(\d{4})-(\d{2})$/', trim($mes), $partes)) {
            return null;
        }

        $anio = (int) $partes[1];
        $numero = (int) $partes[2];

        if ($numero < 1 || $numero > 12) {
            return null;
        }

        $inicio = Carbon::create($anio, $numero, 1)->startOfDay();

        return self::armar($inicio, $inicio->copy()->endOfMonth());
    }

    /** Un trimestre de un año. */
    private static function trimestre(int $anio, int $trimestre): ?array
    {
        if (! self::anioValido($anio) || ! isset(self::TRIMESTRES[$trimestre])) {
            return null;
        }

        $inicio = Carbon::create($anio, (($trimestre - 1) * 3) + 1, 1)->startOfDay();

        return self::armar($inicio, $inicio->copy()->addMonths(3)->subDay(),
            self::TRIMESTRES[$trimestre].' trimestre');
    }

    /** Un semestre de un año. */
    private static function semestre(int $anio, int $semestre): ?array
    {
        if (! self::anioValido($anio) || ! isset(self::SEMESTRES[$semestre])) {
            return null;
        }

        $inicio = Carbon::create($anio, $semestre === 1 ? 1 : 7, 1)->startOfDay();

        return self::armar($inicio, $inicio->copy()->addMonths(6)->subDay(),
            self::SEMESTRES[$semestre].' semestre');
    }

    /** Un año completo. */
    private static function anio(int $anio): ?array
    {
        if (! self::anioValido($anio)) {
            return null;
        }

        return self::armar(Carbon::create($anio, 1, 1)->startOfDay(), Carbon::create($anio, 12, 31)->startOfDay());
    }

    /** Un rango elegido a mano, con las dos fechas en formato d/m/Y. */
    private static function entreFechas(mixed $desde, mixed $hasta): ?array
    {
        $inicio = self::aFecha($desde);
        $fin = self::aFecha($hasta);

        // Si falta una punta, se usa la otra para las dos.
        $inicio ??= $fin;
        $fin ??= $inicio;

        if (! $inicio || ! $fin) {
            return null;
        }

        // Si vienen dadas vuelta, se acomodan.
        if ($fin->lessThan($inicio)) {
            [$inicio, $fin] = [$fin, $inicio];
        }

        return self::armar($inicio, $fin);
    }

    /* ------------------------------------------------------------------ */

    /** @return array{inicio: Carbon, fin: Carbon, etiqueta: string} */
    private static function armar(Carbon $inicio, Carbon $fin, string $etiqueta = ''): array
    {
        $inicio = $inicio->copy()->startOfDay();
        $fin = $fin->copy()->startOfDay();

        return [
            'inicio' => $inicio,
            'fin' => $fin,
            'etiqueta' => $etiqueta !== '' ? $etiqueta : self::etiqueta(['inicio' => $inicio, 'fin' => $fin]),
        ];
    }

    private static function aFecha(mixed $valor): ?Carbon
    {
        if (! is_string($valor) || trim($valor) === '') {
            return null;
        }

        try {
            return Helper::toCarbon($valor);
        } catch (InvalidFormatException) {
            return null;
        }
    }

    private static function anioValido(int $anio): bool
    {
        return $anio >= 1900 && $anio <= 2200;
    }
}
