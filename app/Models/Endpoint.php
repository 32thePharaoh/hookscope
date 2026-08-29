<?php

namespace App\Models;

use Database\Factories\EndpointFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $token
 * @property int $retention_days
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Collection<int, CapturedRequest> $capturedRequests
 */
#[Fillable(['user_id', 'name', 'token', 'retention_days'])]
class Endpoint extends Model
{
    /** @use HasFactory<EndpointFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Endpoint $endpoint): void {
            if (! filled($endpoint->token)) {
                $endpoint->token = bin2hex(random_bytes(32));
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'retention_days' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<CapturedRequest, $this>
     */
    public function capturedRequests(): HasMany
    {
        return $this->hasMany(CapturedRequest::class);
    }
}
