<?php

namespace Database\Factories;

use App\Models\CapturedRequest;
use App\Models\Replay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Replay>
 */
class ReplayFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'captured_request_id' => CapturedRequest::factory(),
            'target_url' => 'https://example.test/webhook',
            'status_code' => 200,
            'duration_ms' => 42,
            'error' => null,
            'response_snippet' => '{"received":true}',
            'forwarded_headers' => ['Content-Type'],
        ];
    }
}
