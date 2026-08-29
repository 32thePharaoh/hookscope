<?php

namespace App\Capture;

final class HeaderSanitizer
{
    /**
     * @param  array<string, list<string|null>>  $headers
     * @return array<string, list<string|array{encoding: string, value: string}>>
     */
    public static function sanitize(array $headers): array
    {
        $sanitized = [];

        foreach ($headers as $name => $values) {
            $sanitized[$name] = array_map(self::sanitizeValue(...), $values);
        }

        return $sanitized;
    }

    /**
     * @return string|array{encoding: string, value: string}
     */
    private static function sanitizeValue(mixed $value): string|array
    {
        $string = (string) $value;

        if (mb_check_encoding($string, 'UTF-8')) {
            return $string;
        }

        return [
            'encoding' => 'base64',
            'value' => base64_encode($string),
        ];
    }
}
