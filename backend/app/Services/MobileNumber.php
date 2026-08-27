<?php

namespace App\Services;

use InvalidArgumentException;

final class MobileNumber
{
    public static function normalize(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value);
        if (str_starts_with($digits, '880')) {
            $digits = substr($digits, 3);
        } if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        } if (! preg_match('/^1[3-9]\d{8}$/', $digits)) {
            throw new InvalidArgumentException('Enter a valid Bangladeshi mobile number.');
        }

        return '+880'.$digits;
    }
}
