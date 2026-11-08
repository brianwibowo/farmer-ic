<?php

namespace App\Support;

final class PhoneNumber
{
    public static function normalize(#[\SensitiveParameter] mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }
        $number = preg_replace('/[\s+()-]/', '', $value);
        if (str_starts_with($number, '620')) {
            $number = '62'.substr($number, 3);
        } elseif (str_starts_with($number, '0')) {
            $number = '62'.substr($number, 1);
        }

        return $number === '' ? null : $number;
    }
}
