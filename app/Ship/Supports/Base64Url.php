<?php

namespace App\Ship\Supports;

final class Base64Url
{
    public static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    public static function decode(string $value): string|null
    {
        $padded = strtr($value, '-_', '+/');
        $padLength = strlen($padded) % 4;

        if ($padLength > 0) {
            $padded .= str_repeat('=', 4 - $padLength);
        }

        $decoded = base64_decode($padded, true);

        return $decoded === false ? null : $decoded;
    }
}

