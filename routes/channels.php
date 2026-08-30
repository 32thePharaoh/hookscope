<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('endpoints.{endpoint}', function (User $user, string $endpoint): bool {
    if (! ctype_digit($endpoint)) {
        return false;
    }

    return $user->endpoints()->whereKey((int) $endpoint)->exists();
});
