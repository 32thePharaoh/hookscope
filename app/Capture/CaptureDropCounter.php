<?php

namespace App\Capture;

use App\Models\Endpoint;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Counts captures dropped by the rate limiter, for display on the endpoint.
 */
final class CaptureDropCounter
{
    private const WINDOW_HOURS = 24;

    public static function key(string $token): string
    {
        return 'hookscope:capture-drops:'.$token;
    }

    public static function record(string $token): void
    {
        // The limiter runs before the controller resolves the endpoint, so a flood
        // against made-up tokens reaches here too. Cache::increment creates keys
        // with no TTL, so counting those would let it mint permanent keys.
        if (! self::endpointExists($token)) {
            return;
        }

        $key = self::key($token);

        Cache::add($key, 0, now()->addHours(self::WINDOW_HOURS));
        Cache::increment($key);
    }

    public static function count(string $token): int
    {
        try {
            return (int) Cache::get(self::key($token), 0);
        } catch (Throwable) {
            return 0;
        }
    }

    private static function endpointExists(string $token): bool
    {
        return (bool) Cache::remember(
            'hookscope:endpoint-known:'.$token,
            now()->addMinute(),
            fn (): bool => Endpoint::query()->where('token', $token)->exists(),
        );
    }
}
