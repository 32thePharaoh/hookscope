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
     * Make one header value safe for a text column. The lossless original is
     * still kept, base64-marked, in the headers JSON.
     */
    public static function scalar(?string $value): ?string
    {
        if ($value === null || mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
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
