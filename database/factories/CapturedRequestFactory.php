<?php

namespace Database\Factories;

use App\Models\CapturedRequest;
use App\Models\Endpoint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CapturedRequest>
 */
class CapturedRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $body = '{"ok":true}';

        return [
            'endpoint_id' => Endpoint::factory(),
            'method' => 'POST',
            'path' => '/hooks/example',
            'query' => 'source=factory',
            'headers' => [
                'Content-Type' => 'application/json',
                'User-Agent' => 'HookscopeFactory/1.0',
            ],
            'body' => $body,
            'body_encoding' => 'utf-8',
            'content_type' => 'application/json',
            'ip' => '127.0.0.1',
            'size_bytes' => strlen($body),
            'received_at' => now(),
        ];
    }

    public function binary(): static
    {
        return $this->state(function (): array {
            $body = "\x80\x81\xFF".random_bytes(16);

            return [
                'body' => $body,
                'body_encoding' => 'binary',
                'content_type' => 'application/octet-stream',
                'size_bytes' => strlen($body),
            ];
        });
    }
}
