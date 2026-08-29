<?php

namespace App\Models;

use Database\Factories\ReplayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $captured_request_id
 * @property string $target_url
 * @property int|null $status_code
 * @property int|null $duration_ms
 * @property string|null $error
 * @property string|null $response_snippet
 * @property list<string> $forwarded_headers
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CapturedRequest $capturedRequest
 */
#[Fillable([
    'captured_request_id',
    'target_url',
    'status_code',
    'duration_ms',
    'error',
    'response_snippet',
    'forwarded_headers',
])]
class Replay extends Model
{
    /** @use HasFactory<ReplayFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'forwarded_headers' => 'array',
            'status_code' => 'integer',
            'duration_ms' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CapturedRequest, $this>
     */
    public function capturedRequest(): BelongsTo
    {
        return $this->belongsTo(CapturedRequest::class);
    }
}
