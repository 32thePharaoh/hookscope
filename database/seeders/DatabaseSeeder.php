<?php

namespace Database\Seeders;

use App\Models\CapturedRequest;
use App\Models\Endpoint;
use App\Models\Replay;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (User::query()->exists()) {
            return;
        }

        // Built without factories on purpose: this runs from the container
        // entrypoint on first boot, and the production image is installed
        // --no-dev, so fakerphp/faker is not there. Demo data wants to be
        // deterministic anyway.
        $user = User::query()->create([
            'name' => config('hookscope.demo.name'),
            'email' => config('hookscope.demo.email'),
            'password' => config('hookscope.demo.password'),
            'email_verified_at' => now(),
        ]);

        $endpoint = Endpoint::query()->create([
            'user_id' => $user->id,
            'name' => config('hookscope.demo.endpoint'),
            // WithoutModelEvents silences Endpoint::booted(), so the token has to
            // be supplied here. Random rather than fixed: a token baked into a
            // public repo would give every install the same capture URL.
            'token' => bin2hex(random_bytes(32)),
            'retention_days' => 7,
        ]);

        $request = $this->capture($endpoint, '{"event":"invoice.paid"}', 'application/json', 'utf-8');
        $this->capture($endpoint, "\x80\x81\xFF".'binary-demo', 'application/octet-stream', 'binary');

        Replay::query()->create([
            'captured_request_id' => $request->id,
            'target_url' => 'https://example.test/webhook',
            'status_code' => 200,
            'duration_ms' => 42,
            'error' => null,
            'response_snippet' => '{"received":true}',
            'forwarded_headers' => ['Content-Type'],
        ]);
    }

    private function capture(Endpoint $endpoint, string $body, string $contentType, string $encoding): CapturedRequest
    {
        return CapturedRequest::query()->create([
            'endpoint_id' => $endpoint->id,
            'method' => 'POST',
            'path' => 'in/'.$endpoint->token,
            'query' => null,
            'headers' => ['content-type' => [$contentType]],
            'body' => $body,
            'body_encoding' => $encoding,
            'content_type' => $contentType,
            'ip' => '127.0.0.1',
            'size_bytes' => strlen($body),
            'received_at' => now(),
        ]);
    }
}
