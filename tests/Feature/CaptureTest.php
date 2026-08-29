<?php

namespace Tests\Feature;

use App\Capture\CaptureDropCounter;
use App\Http\Middleware\ThrottleCapture;
use App\Jobs\EnrichCapturedRequest;
use App\Models\CapturedRequest;
use App\Models\Endpoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;
use Tests\TestCase;

class CaptureTest extends TestCase
{
    use RefreshDatabase;

    public function test_capture_url_is_in_token_not_under_api(): void
    {
        $endpoint = Endpoint::factory()->create();

        $this->postJson('/api/in/'.$endpoint->token, ['ok' => true])->assertNotFound();
        $this->postJson('/in/'.$endpoint->token, ['ok' => true])->assertOk();
    }

    public function test_post_persists_the_raw_body_and_returns_200(): void
    {
        $endpoint = Endpoint::factory()->create();
        $body = '{"ok":true}';

        $response = $this->call('POST', '/in/'.$endpoint->token.'?source=test', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_TRACE' => 'one',
            'REMOTE_ADDR' => '203.0.113.10',
        ], content: $body);

        $response->assertOk();
        $this->assertSame(1, CapturedRequest::query()->count());

        $captured = CapturedRequest::query()->first();
        $this->assertNotNull($captured);
        $this->assertSame($endpoint->id, $captured->endpoint_id);
        $this->assertSame('POST', $captured->method);
        $this->assertSame('in/'.$endpoint->token, $captured->path);
        $this->assertSame('source=test', $captured->query);
        $this->assertSame($body, $captured->body);
        $this->assertSame('utf-8', $captured->body_encoding);
        $this->assertSame('application/json', $captured->content_type);
        $this->assertSame(strlen($body), $captured->size_bytes);
        $this->assertSame('203.0.113.10', $captured->ip);
        $this->assertIsArray($captured->headers['x-trace']);
        $this->assertSame(['one'], $captured->headers['x-trace']);
        $response->assertHeaderMissing('Set-Cookie');
    }

    public function test_get_put_patch_and_delete_are_recorded(): void
    {
        $endpoint = Endpoint::factory()->create();

        foreach (['GET', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $this->call($method, '/in/'.$endpoint->token)->assertOk();
        }

        $this->assertSame(4, CapturedRequest::query()->count());
        $this->assertEqualsCanonicalizing(
            ['GET', 'PUT', 'PATCH', 'DELETE'],
            CapturedRequest::query()->pluck('method')->all(),
        );
    }

    public function test_head_and_options_do_not_create_rows(): void
    {
        $endpoint = Endpoint::factory()->create();

        $this->call('HEAD', '/in/'.$endpoint->token)->assertMethodNotAllowed();
        $this->call('OPTIONS', '/in/'.$endpoint->token);

        $this->assertSame(0, CapturedRequest::query()->count());
    }

    public function test_unknown_token_is_404_and_creates_no_row(): void
    {
        $this->postJson('/in/'.str_repeat('ab', 32), ['ok' => true])->assertNotFound();
        $this->assertSame(0, CapturedRequest::query()->count());
    }

    public function test_invalid_utf8_body_is_stored_as_binary(): void
    {
        $endpoint = Endpoint::factory()->create();
        $body = "\x80\x81\xFF";

        $this->call('POST', '/in/'.$endpoint->token, server: [
            'CONTENT_TYPE' => 'application/octet-stream',
        ], content: $body)->assertOk();

        $captured = CapturedRequest::query()->first();
        $this->assertNotNull($captured);
        $this->assertSame($body, $captured->body);
        $this->assertSame('binary', $captured->body_encoding);
        $this->assertFalse(mb_check_encoding($captured->body, 'UTF-8'));
    }

    public function test_non_utf8_header_values_are_base64_marked_so_the_insert_does_not_throw(): void
    {
        $endpoint = Endpoint::factory()->create();

        $this->call('POST', '/in/'.$endpoint->token, server: [
            'CONTENT_TYPE' => 'text/plain',
            'HTTP_X_ODD' => "\x80\x81",
        ], content: 'ok')->assertOk();

        $captured = CapturedRequest::query()->first();
        $this->assertNotNull($captured);
        $values = $captured->headers['x-odd'];
        $this->assertIsArray($values);
        $this->assertSame('base64', $values[0]['encoding']);
        $this->assertSame(base64_encode("\x80\x81"), $values[0]['value']);
    }

    public function test_oversized_content_length_is_413_without_inserting(): void
    {
        config(['hookscope.max_body_bytes' => 64]);
        $endpoint = Endpoint::factory()->create();

        $this->call('POST', '/in/'.$endpoint->token, server: [
            'CONTENT_TYPE' => 'application/octet-stream',
            'CONTENT_LENGTH' => '128',
        ], content: str_repeat('a', 128))->assertStatus(413);

        $this->assertSame(0, CapturedRequest::query()->count());
    }

    public function test_queue_dispatch_failure_still_returns_200_and_keeps_the_row(): void
    {
        $endpoint = Endpoint::factory()->create();

        Bus::shouldReceive('dispatch')
            ->once()
            ->andThrow(new RuntimeException('Redis is down'));

        $this->postJson('/in/'.$endpoint->token, ['ok' => true])->assertOk();
        $this->assertSame(1, CapturedRequest::query()->count());
    }

    public function test_successful_capture_dispatches_enrichment(): void
    {
        Bus::fake();
        $endpoint = Endpoint::factory()->create();

        $this->postJson('/in/'.$endpoint->token, ['ok' => true])->assertOk();

        Bus::assertDispatched(EnrichCapturedRequest::class);
    }

    public function test_per_token_throttle_returns_429_and_increments_the_drop_counter(): void
    {
        config(['hookscope.throttle_per_minute' => 2]);
        $endpoint = Endpoint::factory()->create();

        $this->postJson('/in/'.$endpoint->token, ['n' => 1])->assertOk();
        $this->postJson('/in/'.$endpoint->token, ['n' => 2])->assertOk();
        $this->postJson('/in/'.$endpoint->token, ['n' => 3])->assertStatus(429);

        $this->assertSame(2, CapturedRequest::query()->count());
        $this->assertSame(1, (int) Cache::get('hookscope:capture-drops:'.$endpoint->token));
    }

    public function test_capture_survives_the_rate_limiter_store_being_down(): void
    {
        // The limiter runs before the controller, so a dead cache would otherwise
        // 500 the request before the row is inserted and lose the capture.
        RateLimiter::for('capture', function (): never {
            throw new RuntimeException('cache unavailable');
        });

        $endpoint = Endpoint::factory()->create();

        $this->postJson('/in/'.$endpoint->token, ['ok' => true])->assertOk();

        $this->assertSame(1, CapturedRequest::query()->count());
    }

    public function test_failing_open_does_not_swallow_downstream_exceptions(): void
    {
        $middleware = app(ThrottleCapture::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('from the controller');

        $middleware->handle(
            Request::create('/in/token'),
            function (): never {
                throw new RuntimeException('from the controller');
            },
        );
    }

    public function test_unknown_tokens_do_not_create_drop_counters(): void
    {
        config(['hookscope.throttle_per_minute' => 1]);

        $this->postJson('/in/no-such-token')->assertNotFound();
        $this->postJson('/in/no-such-token')->assertStatus(429);

        // Cache::increment creates keys with no TTL, so counting unknown tokens
        // would let a flood mint permanent ones.
        $this->assertNull(Cache::get(CaptureDropCounter::key('no-such-token')));
    }

    public function test_drop_counters_expire(): void
    {
        config(['hookscope.throttle_per_minute' => 1]);
        $endpoint = Endpoint::factory()->create();

        $this->postJson('/in/'.$endpoint->token, ['n' => 1])->assertOk();
        $this->postJson('/in/'.$endpoint->token, ['n' => 2])->assertStatus(429);

        $this->assertSame(1, CaptureDropCounter::count($endpoint->token));

        $this->travel(25)->hours();

        $this->assertSame(0, CaptureDropCounter::count($endpoint->token));
    }

    public function test_capture_does_not_require_csrf(): void
    {
        $endpoint = Endpoint::factory()->create();

        $this->post('/in/'.$endpoint->token, ['ok' => true], [
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
        ])->assertOk();
    }
}
