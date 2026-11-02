<?php

namespace App\Support;

/** Exact area conversion at the database's one-square-metre precision. */
final class LandArea
{
    public static function squareMetres(mixed $value, string $unit = 'ha'): ?int
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }
        $value = str_replace(',', '.', trim((string) $value));
        if (str_starts_with($value, '+')) {
            $value = substr($value, 1);
        }
        if (str_starts_with($value, '.')) {
            $value = '0'.$value;
        }
        if (! in_array($unit, ['ha', 'm2'], true)
            || ! preg_match('/^([0-9]{1,12})(?:\.([0-9]{1,4}))?$/D', $value, $parts)) {
            return null;
        }
        $fraction = str_pad($parts[2] ?? '', 4, '0');
        if ($unit === 'm2') {
            return isset($parts[2]) ? null : (int) $parts[1];
        }

        return (int) $parts[1] * 10000 + (int) $fraction;
    }

    public static function hectares(int $squareMetres): string
    {
        return intdiv($squareMetres, 10000).'.'.str_pad((string) ($squareMetres % 10000), 4, '0', STR_PAD_LEFT);
    }

    public static function label(int $squareMetres): string
    {
        [$whole, $fraction] = explode('.', self::hectares($squareMetres));
        $fraction = rtrim($fraction, '0');

        return number_format((int) $whole, 0, ',', '.').($fraction === '' ? '' : ','.$fraction).' ha';
    }
}
