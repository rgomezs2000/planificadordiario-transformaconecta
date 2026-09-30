<?php

namespace App\Helpers;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Str;
use Throwable;

/**
 * Helpers básicos de fechas, cifras y textos.
 *
 * Todos los métodos son estáticos para poder invocarlos desde modelos,
 * controladores y vistas sin inyectar nada:
 *
 *     Helper::date($plan->plan_date);              // 29/09/2026
 *     Helper::longDate($plan->plan_date, true);    // martes 29 de septiembre de 2026
 *     Helper::minutesToHuman(90);                  // 1 h 30 min
 *
 * En las vistas Blade se importa una sola vez con la directiva @use:
 *
 *     @use('App\Helpers\Helper')
 *     {{ Helper::timeLabel($entry->scheduleSlot->start_time) }}
 *
 * Los meses y días están escritos en español directamente en las constantes,
 * así que no dependen del locale de la aplicación ni de la extensión intl
 * (que no está cargada en este servidor).
 */
class Helper
{
    /* ======================================================================
     |  Constantes en español
     ====================================================================== */

    /** Meses indexados 1-12. */
    public const MONTHS = [
        1 => 'enero',
        2 => 'febrero',
        3 => 'marzo',
        4 => 'abril',
        5 => 'mayo',
        6 => 'junio',
        7 => 'julio',
        8 => 'agosto',
        9 => 'septiembre',
        10 => 'octubre',
        11 => 'noviembre',
        12 => 'diciembre',
    ];

    /** Meses abreviados indexados 1-12. */
    public const MONTHS_SHORT = [
        1 => 'ene',
        2 => 'feb',
        3 => 'mar',
        4 => 'abr',
        5 => 'may',
        6 => 'jun',
        7 => 'jul',
        8 => 'ago',
        9 => 'sep',
        10 => 'oct',
        11 => 'nov',
        12 => 'dic',
    ];

    /** Días indexados 0 (domingo) - 6 (sábado), como Carbon::dayOfWeek. */
    public const DAYS = [
        0 => 'domingo',
        1 => 'lunes',
        2 => 'martes',
        3 => 'miércoles',
        4 => 'jueves',
        5 => 'viernes',
        6 => 'sábado',
    ];

    /** Días abreviados indexados 0-6. */
    public const DAYS_SHORT = [
        0 => 'dom',
        1 => 'lun',
        2 => 'mar',
        3 => 'mié',
        4 => 'jue',
        5 => 'vie',
        6 => 'sáb',
    ];

    /** Las letras que usa el formulario: L M M J V S D (domingo primero como Carbon). */
    public const DAYS_LETTER = [
        0 => 'D',
        1 => 'L',
        2 => 'M',
        3 => 'M',
        4 => 'J',
        5 => 'V',
        6 => 'S',
    ];

    /* ======================================================================
     |  Fechas
     ====================================================================== */

    /**
     * Convierte cualquier valor (Carbon, DateTime, string, null) a Carbon.
     * Devuelve null si el valor está vacío o no se puede interpretar.
     */
    public static function toCarbon(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->copy();
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value);
        }

        try {
            return Carbon::parse((string) $value);
        } catch (Throwable) {
            return null;
        }
    }

    /** Momento actual. */
    public static function now(): Carbon
    {
        return Carbon::now();
    }

    /** Hoy a las 00:00. */
    public static function today(): Carbon
    {
        return Carbon::today();
    }

    /** 29/09/2026 */
    public static function date(mixed $value, string $format = 'd/m/Y'): ?string
    {
        return self::toCarbon($value)?->format($format);
    }

    /** 29/09/2026 14:35 */
    public static function dateTime(mixed $value, string $format = 'd/m/Y H:i'): ?string
    {
        return self::toCarbon($value)?->format($format);
    }

    /** 14:35 */
    public static function time(mixed $value, string $format = 'H:i'): ?string
    {
        return self::toCarbon($value)?->format($format);
    }

    /** "07:00:00" -> "7:00" (hora del horario, sin segundos ni cero inicial). */
    public static function timeLabel(mixed $value): ?string
    {
        $carbon = self::toCarbon($value);

        return $carbon ? ltrim($carbon->format('H:i'), '0') : null;
    }

    /** Índice del día: 0 = domingo ... 6 = sábado. */
    public static function dayIndex(mixed $value): ?int
    {
        return self::toCarbon($value)?->dayOfWeek;
    }

    /** "martes", "mar" o "Martes" según los modificadores. */
    public static function dayName(mixed $value, bool $short = false, bool $capitalize = false): ?string
    {
        $index = self::dayIndex($value);

        if ($index === null) {
            return null;
        }

        $name = $short ? self::DAYS_SHORT[$index] : self::DAYS[$index];

        return $capitalize ? self::sentence($name) : $name;
    }

    /** La letra del formulario (L M M J V S D). */
    public static function dayLetter(mixed $value): ?string
    {
        $index = self::dayIndex($value);

        return $index === null ? null : self::DAYS_LETTER[$index];
    }

    /** Índice del mes: 1-12. */
    public static function monthIndex(mixed $value): ?int
    {
        $carbon = self::toCarbon($value);

        return $carbon ? (int) $carbon->format('n') : null;
    }

    /** "septiembre", "sep" o "Septiembre". */
    public static function monthName(mixed $value, bool $short = false, bool $capitalize = false): ?string
    {
        $index = self::monthIndex($value);

        if ($index === null) {
            return null;
        }

        $name = $short ? self::MONTHS_SHORT[$index] : self::MONTHS[$index];

        return $capitalize ? self::sentence($name) : $name;
    }

    /**
     * Fecha larga en español: "29 de septiembre de 2026".
     * Con $withWeekday: "martes 29 de septiembre de 2026".
     */
    public static function longDate(mixed $value, bool $withWeekday = false): ?string
    {
        $carbon = self::toCarbon($value);

        if (! $carbon) {
            return null;
        }

        $text = $carbon->format('j').' de '.self::MONTHS[(int) $carbon->format('n')].' de '.$carbon->format('Y');

        return $withWeekday ? self::dayName($carbon).' '.$text : $text;
    }

    /** Fecha media: "29 sep 2026". */
    public static function shortDate(mixed $value): ?string
    {
        $carbon = self::toCarbon($value);

        if (! $carbon) {
            return null;
        }

        return $carbon->format('j').' '.self::MONTHS_SHORT[(int) $carbon->format('n')].' '.$carbon->format('Y');
    }

    public static function isToday(mixed $value): bool
    {
        return self::toCarbon($value)?->isToday() ?? false;
    }

    public static function isPast(mixed $value): bool
    {
        return self::toCarbon($value)?->isPast() ?? false;
    }

    public static function isFuture(mixed $value): bool
    {
        return self::toCarbon($value)?->isFuture() ?? false;
    }

    public static function isWeekend(mixed $value): bool
    {
        return self::toCarbon($value)?->isWeekend() ?? false;
    }

    /**
     * Días enteros entre dos fechas, sin horas de por medio.
     * Positivo si $to es posterior a $from.
     */
    public static function diffInDays(mixed $from, mixed $to = null): ?int
    {
        $start = self::toCarbon($from);
        $end = $to === null ? self::today() : self::toCarbon($to);

        if (! $start || ! $end) {
            return null;
        }

        $seconds = $end->copy()->startOfDay()->getTimestamp() - $start->copy()->startOfDay()->getTimestamp();

        return (int) round($seconds / 86400);
    }

    /** "hace 3 días", "en 2 horas", "hace unos segundos". */
    public static function humanDiff(mixed $value): ?string
    {
        $carbon = self::toCarbon($value);

        if (! $carbon) {
            return null;
        }

        $seconds = $carbon->getTimestamp() - self::now()->getTimestamp();
        $future = $seconds > 0;
        $seconds = abs($seconds);

        $text = match (true) {
            $seconds < 45 => 'unos segundos',
            $seconds < 3600 => self::pluralize((int) round($seconds / 60), 'minuto', 'minutos'),
            $seconds < 86400 => self::pluralize((int) round($seconds / 3600), 'hora', 'horas'),
            $seconds < 2592000 => self::pluralize((int) round($seconds / 86400), 'día', 'días'),
            $seconds < 31536000 => self::pluralize((int) round($seconds / 2592000), 'mes', 'meses'),
            default => self::pluralize((int) round($seconds / 31536000), 'año', 'años'),
        };

        return $future ? 'en '.$text : 'hace '.$text;
    }

    /** Etiquetas de la semana en orden de lunes a domingo, para la cabecera del formulario. */
    public static function weekLabels(): array
    {
        return ['L', 'M', 'M', 'J', 'V', 'S', 'D'];
    }

    /** Lunes de la semana a la que pertenece la fecha. */
    public static function startOfWeek(mixed $value = null): ?Carbon
    {
        return self::toCarbon($value ?? now())?->startOfWeek(Carbon::MONDAY);
    }

    /** "1 al 30 de septiembre de 2026", "1 de septiembre al 15 de octubre de 2026". */
    public static function rangeLabel(mixed $from, mixed $to, string $separator = ' al '): ?string
    {
        $start = self::toCarbon($from);
        $end = self::toCarbon($to);

        if (! $start && ! $end) {
            return null;
        }

        if (! $start) {
            return 'Hasta el '.self::longDate($end);
        }

        if (! $end) {
            return 'Desde el '.self::longDate($start);
        }

        if ($start->isSameDay($end)) {
            return self::longDate($start);
        }

        $startMonth = self::MONTHS[(int) $start->format('n')];
        $endMonth = self::MONTHS[(int) $end->format('n')];

        if ($start->isSameYear($end) && $start->isSameMonth($end)) {
            return $start->format('j').$separator.$end->format('j').' de '.$endMonth.' de '.$end->format('Y');
        }

        if ($start->isSameYear($end)) {
            return $start->format('j').' de '.$startMonth.$separator
                .$end->format('j').' de '.$endMonth.' de '.$end->format('Y');
        }

        return self::longDate($start).$separator.self::longDate($end);
    }

    /* ======================================================================
     |  Cifras
     ====================================================================== */

    /** 1234.5 -> "1.234,5" (separador español: miles punto, decimales coma). */
    public static function number(mixed $value, int $decimals = 0): string
    {
        return number_format((float) $value, $decimals, ',', '.');
    }

    /** 1234.5 -> "$ 1.234,50". */
    public static function currency(mixed $value, string $symbol = '$', int $decimals = 2): string
    {
        return $symbol.' '.self::number($value, $decimals);
    }

    /** 25 -> "25 %". */
    public static function percent(mixed $value, int $decimals = 0): string
    {
        return self::number($value, $decimals).' %';
    }

    /** Porcentaje de una parte sobre un total. Si el total es 0 devuelve "0 %". */
    public static function percentageOf(mixed $part, mixed $total, int $decimals = 0): string
    {
        $total = (float) $total;

        if ($total <= 0.0) {
            return self::percent(0, $decimals);
        }

        return self::percent(((float) $part / $total) * 100, $decimals);
    }

    /** 90 -> "1 h 30 min"; 45 -> "45 min"; 120 -> "2 h". */
    public static function minutesToHuman(mixed $minutes): string
    {
        $total = (int) round((float) $minutes);

        if ($total <= 0) {
            return '0 min';
        }

        $parts = [];

        if ($hours = intdiv($total, 60)) {
            $parts[] = $hours.' h';
        }

        if ($rest = $total % 60) {
            $parts[] = $rest.' min';
        }

        return implode(' ', $parts);
    }

    /** 1 -> "1º". */
    public static function ordinal(int $value): string
    {
        return $value.'º';
    }

    /** 2048 -> "2 KB"; 1536 -> "1,5 KB". */
    public static function fileSize(int|float $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = max((float) $bytes, 0.0);
        $index = 0;

        while ($size >= 1024 && $index < count($units) - 1) {
            $size /= 1024;
            $index++;
        }

        if ($index === 0) {
            return self::number($size).' '.$units[$index];
        }

        // Se quitan los ceros que sobran: "2,00 KB" -> "2 KB".
        $formatted = rtrim(rtrim(self::number($size, $precision), '0'), ',');

        return $formatted.' '.$units[$index];
    }

    /** Promedio de los valores numéricos; 0 si no hay ninguno. */
    public static function average(array $values): float
    {
        $clean = array_filter($values, is_numeric(...));

        return $clean === [] ? 0.0 : array_sum($clean) / count($clean);
    }

    /** Suma de los valores numéricos. */
    public static function sum(array $values): float
    {
        return (float) array_sum(array_filter($values, is_numeric(...)));
    }

    /* ======================================================================
     |  Textos
     ====================================================================== */

    /** Recorta a $chars caracteres. */
    public static function limit(?string $value, int $chars = 60, string $end = '…'): string
    {
        return Str::limit((string) $value, $chars, $end);
    }

    /** Recorta a $words palabras. */
    public static function words(?string $value, int $words = 10, string $end = '…'): string
    {
        return Str::words((string) $value, $words, $end);
    }

    /** "Mi plan diario" -> "mi-plan-diario". */
    public static function slug(?string $value): string
    {
        return Str::slug((string) $value);
    }

    /** Cada palabra con mayúscula inicial. */
    public static function title(?string $value): string
    {
        return Str::title((string) $value);
    }

    /** Solo la primera letra en mayúscula, el resto intacto. */
    public static function sentence(?string $value): string
    {
        $text = self::strip($value);

        if ($text === '') {
            return '';
        }

        return mb_strtoupper(mb_substr($text, 0, 1)).mb_substr($text, 1);
    }

    public static function upper(?string $value): string
    {
        return mb_strtoupper((string) $value);
    }

    public static function lower(?string $value): string
    {
        return mb_strtolower((string) $value);
    }

    /** "Ana María López" -> "AM". */
    public static function initials(?string $value, int $limit = 2): string
    {
        $words = preg_split('/\s+/u', self::strip($value)) ?: [];
        $letters = '';

        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }

            $letters .= mb_strtoupper(mb_substr($word, 0, 1));

            if (mb_strlen($letters) >= $limit) {
                break;
            }
        }

        return $letters;
    }

    /** "ana.lopez@correo.com" -> "an*******@correo.com". */
    public static function maskEmail(?string $email): string
    {
        $email = trim((string) $email);

        if (! str_contains($email, '@')) {
            return $email;
        }

        [$user, $domain] = explode('@', $email, 2);

        return mb_substr($user, 0, 2).str_repeat('*', max(mb_strlen($user) - 2, 1)).'@'.$domain;
    }

    /** true -> "Sí", false -> "No". */
    public static function yesNo(mixed $value): string
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Sí' : 'No';
    }

    /** ['a', 'b', 'c'] -> "a, b y c". */
    public static function listToText(array $items, string $conjunction = 'y'): string
    {
        $items = array_values(array_filter(
            array_map(fn ($item) => trim((string) $item), $items),
            fn (string $item) => $item !== ''
        ));

        if ($items === []) {
            return '';
        }

        if (count($items) === 1) {
            return $items[0];
        }

        $last = array_pop($items);

        return implode(', ', $items).' '.$conjunction.' '.$last;
    }

    /** pluralize(1, 'día', 'días') -> "1 día"; pluralize(3, 'día', 'días') -> "3 días". */
    public static function pluralize(int $count, string $singular, string $plural): string
    {
        return self::number($count).' '.($count === 1 ? $singular : $plural);
    }

    /** Quita espacios sobrantes y colapsa los intermedios. */
    public static function strip(?string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');
    }

    /** ¿El valor está vacío o solo tiene espacios? */
    public static function isBlank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }

    /** Devuelve el valor o un guion largo si está vacío (cómodo en vistas). */
    public static function fallback(mixed $value, string $default = '—'): string
    {
        return self::isBlank($value) ? $default : (string) $value;
    }
}
