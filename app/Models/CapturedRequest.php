<?php

namespace App\Models;

use Database\Factories\CapturedRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $endpoint_id
 * @property string $method
 * @property string $path
 * @property string|null $query
 * @property array<string, mixed> $headers
 * @property string $body
 * @property string $body_encoding
 * @property string|null $content_type
 * @property string|null $ip
 * @property int $size_bytes
 * @property Carbon $received_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Endpoint $endpoint
 * @property-read Collection<int, Replay> $replays
 */
#[Fillable([
    'endpoint_id',
    'method',
    'path',
    'query',
    'headers',
    'body',
    'body_encoding',
    'content_type',
    'ip',
    'size_bytes',
    'received_at',
])]
class CapturedRequest extends Model
{
    /** @use HasFactory<CapturedRequestFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'received_at' => 'datetime',
            'size_bytes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Endpoint, $this>
     */
    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(Endpoint::class);
    }

    /**
     * @return HasMany<Replay, $this>
     */
    public function replays(): HasMany
    {
        return $this->hasMany(Replay::class);
    }
}
