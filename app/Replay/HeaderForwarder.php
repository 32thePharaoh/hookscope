<?php

namespace App\Replay;

final class HeaderForwarder
{
    /**
     * @var list<string>
     */
    private const ALWAYS_STRIP = [
        'host',
        'connection',
        'keep-alive',
        'proxy-authenticate',
        'proxy-authorization',
        'te',
        'trailer',
        'transfer-encoding',
        'upgrade',
        'content-length',
        'expect',
    ];

    /**
     * @var list<string>
     */
    private const SENSITIVE = [
        'authorization',
        'cookie',
        'proxy-authorization',
        'stripe-signature',
        'x-hub-signature',
        'x-hub-signature-256',
        'x-api-key',
        'api-key',
    ];

    /**
     * @param  array<string, mixed>  $headers
     */
    public static function forReplay(array $headers, bool $forwardSensitive): ForwardedHeaderSet
    {
        $outgoing = [];
        $forwarded = [];

        foreach ($headers as $name => $value) {
            $lower = strtolower((string) $name);

            if (in_array($lower, self::ALWAYS_STRIP, true)) {
                continue;
            }

            if (! $forwardSensitive && in_array($lower, self::SENSITIVE, true)) {
                continue;
            }

            $values = self::decodeValues($value);

            if ($values === []) {
                continue;
            }

            $outgoing[(string) $name] = $values;
            $forwarded[] = (string) $name;
        }

        return new ForwardedHeaderSet($outgoing, $forwarded);
    }

    /**
     * @return list<string>
     */
    private static function decodeValues(mixed $value): array
    {
        if (is_string($value)) {
            return [$value];
        }

        if (! is_array($value)) {
            return [(string) $value];
        }

        $decoded = [];

        foreach ($value as $item) {
            $decoded[] = self::decodeOne($item);
        }

        return $decoded;
    }

    private static function decodeOne(mixed $item): string
    {
        if (is_string($item)) {
            return $item;
        }

        if (
            is_array($item)
            && ($item['encoding'] ?? null) === 'base64'
            && is_string($item['value'] ?? null)
        ) {
            $bytes = base64_decode($item['value'], true);

            return $bytes === false ? '' : $bytes;
        }

        return (string) $item;
    }
}
