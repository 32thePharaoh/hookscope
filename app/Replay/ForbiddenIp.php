<?php

namespace App\Replay;

final class ForbiddenIp
{
    public static function isForbidden(string $ip): bool
    {
        if (str_contains($ip, '%')) {
            $ip = strstr($ip, '%', true) ?: $ip;
        }

        $binary = inet_pton($ip);

        if ($binary === false) {
            return true;
        }

        if (strlen($binary) === 16 && str_starts_with($binary, str_repeat("\x00", 10)."\xff\xff")) {
            $mapped = inet_ntop(substr($binary, 12));

            return $mapped === false || self::isForbidden($mapped);
        }

        if (strlen($binary) === 4) {
            $unpacked = unpack('N', $binary);

            if ($unpacked === false) {
                return true;
            }

            return self::ipv4IsForbidden($unpacked[1]);
        }

        return self::ipv6IsForbidden($binary);
    }

    public static function ipv4FromEncodedHost(string $host): ?string
    {
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return $host;
        }

        if (ctype_digit($host)) {
            $value = (int) $host;

            return $value <= 0xFFFFFFFF ? self::dotted($value) : null;
        }

        if (preg_match('/^0x[0-9a-f]+$/i', $host) === 1) {
            $value = hexdec($host);

            if (! is_int($value) || $value > 0xFFFFFFFF) {
                return null;
            }

            return self::dotted($value);
        }

        if (! str_contains($host, '.') || preg_match('/^[0-9a-fx.]+$/i', $host) !== 1) {
            return null;
        }

        $parts = explode('.', $host);

        if (count($parts) > 4) {
            return null;
        }

        $octets = [];

        foreach ($parts as $part) {
            if ($part === '') {
                return null;
            }

            if (preg_match('/^0x[0-9a-f]+$/i', $part) === 1) {
                $octets[] = (int) hexdec($part);
            } elseif (preg_match('/^0[0-7]+$/', $part) === 1) {
                $octets[] = (int) octdec($part);
            } elseif (ctype_digit($part)) {
                $octets[] = (int) $part;
            } else {
                return null;
            }
        }

        $last = array_pop($octets);

        while (count($octets) < 3) {
            $octets[] = 0;
        }

        $octets[] = $last;

        foreach ($octets as $octet) {
            if ($octet > 255) {
                return null;
            }
        }

        return implode('.', $octets);
    }

    private static function dotted(int $value): string
    {
        return sprintf(
            '%d.%d.%d.%d',
            ($value >> 24) & 255,
            ($value >> 16) & 255,
            ($value >> 8) & 255,
            $value & 255,
        );
    }

    private static function ipv4IsForbidden(int $long): bool
    {
        if (($long & 0xFF000000) === 0x00000000) {
            return true;
        }

        if (($long & 0xFF000000) === 0x0A000000) {
            return true;
        }

        if (($long & 0xFF000000) === 0x7F000000) {
            return true;
        }

        if (($long & 0xFFFF0000) === 0xA9FE0000) {
            return true;
        }

        // 100.64.0.0/10, carrier-grade NAT (RFC 6598)
        if (($long & 0xFFC00000) === 0x64400000) {
            return true;
        }

        if (($long & 0xFFF00000) === 0xAC100000) {
            return true;
        }

        if (($long & 0xFFFF0000) === 0xC0A80000) {
            return true;
        }

        if (($long & 0xF0000000) === 0xE0000000) {
            return true;
        }

        return ($long & 0xF0000000) === 0xF0000000;
    }

    private static function ipv6IsForbidden(string $binary): bool
    {
        // ::a.b.c.d covers ::, ::1 and the deprecated IPv4-compatible form. The
        // ::ffff: mapped form is unwrapped before this method is reached.
        if (str_starts_with($binary, str_repeat("\x00", 12))) {
            return self::embeddedIpv4IsForbidden($binary, 12);
        }

        // 64:ff9b::/96, the NAT64 well-known prefix
        if (str_starts_with($binary, "\x00\x64\xff\x9b".str_repeat("\x00", 8))) {
            return self::embeddedIpv4IsForbidden($binary, 12);
        }

        // 2002::/16, 6to4, carries its IPv4 in the next four bytes
        if (str_starts_with($binary, "\x20\x02")) {
            return self::embeddedIpv4IsForbidden($binary, 2);
        }

        $first = ord($binary[0]);
        $second = ord($binary[1]);

        if (($first & 0xFE) === 0xFC) {
            return true;
        }

        // fec0::/10, deprecated site-local
        if ($first === 0xFE && ($second & 0xC0) === 0xC0) {
            return true;
        }

        return $first === 0xFE && ($second & 0xC0) === 0x80;
    }

    private static function embeddedIpv4IsForbidden(string $binary, int $offset): bool
    {
        $ipv4 = inet_ntop(substr($binary, $offset, 4));

        return $ipv4 === false || self::isForbidden($ipv4);
    }
}
