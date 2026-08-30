<?php

namespace Tests\Feature;

use App\Jobs\ReplayCapturedRequest;
use App\Models\CapturedRequest;
use App\Models\Endpoint;
use App\Models\Replay;
use App\Models\User;
use App\Replay\DnsResolver;
use App\Replay\ReplayTargetValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\RecordingDnsResolver;
use Tests\TestCase;

class ReplayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(DnsResolver::class, new RecordingDnsResolver([
            'example.test' => ['1.1.1.1'],
            'redirect.test' => ['1.1.1.1'],
        ]));
    }

    public function test_response_snippet_is_stored_base64_so_binary_bytes_do_not_throw(): void
    {
        $binary = "\x80\x81\xFF binary response";

        $replay = Replay::factory()->create([
            'response_snippet' => base64_encode($binary),
        ]);

        $this->assertSame(base64_encode($binary), $replay->fresh()->response_snippet);
        $this->assertFalse(mb_check_encoding($binary, 'UTF-8'));
    }

    public function test_a_user_cannot_replay_another_users_capture(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $endpoint = Endpoint::factory()->for($owner)->create();
        $capture = CapturedRequest::factory()->for($endpoint)->create();
        $strangerEndpoint = Endpoint::factory()->for($stranger)->create();

        $this->actingAs($stranger)
            ->post(route('replays.store', [
                'endpoint' => $endpoint,
                'capturedRequest' => $capture,
            ]), ['target_url' => 'https://example.test/hook'])
            ->assertNotFound();

        $this->actingAs($stranger)
            ->post(route('replays.store', [
                'endpoint' => $strangerEndpoint,
                'capturedRequest' => $capture,
            ]), ['target_url' => 'https://example.test/hook'])
            ->assertNotFound();

        $this->assertDatabaseCount('replays', 0);
    }

    public function test_the_owner_can_queue_a_replay_scoped_through_their_endpoint(): void
    {
        Http::fake([
            'https://example.test/hook' => Http::response('{"ok":true}', 200),
        ]);

        $user = User::factory()->create();
        $endpoint = Endpoint::factory()->for($user)->create();
        $capture = CapturedRequest::factory()->for($endpoint)->create([
            'headers' => [
                'content-type' => ['application/json'],
                'x-request-id' => ['abc-123'],
            ],
        ]);

        $this->actingAs($user)
            ->post(route('replays.store', [
                'endpoint' => $endpoint,
                'capturedRequest' => $capture,
            ]), ['target_url' => 'https://example.test/hook'])
            ->assertRedirect(route('captured-requests.show', [
                'endpoint' => $endpoint,
                'capturedRequest' => $capture,
            ]));

        $replay = Replay::query()->where('captured_request_id', $capture->id)->first();
        $this->assertNotNull($replay);
        $this->assertSame(200, $replay->status_code);
        $this->assertSame(base64_encode('{"ok":true}'), $replay->response_snippet);
        $this->assertContains('content-type', $replay->forwarded_headers);
        $this->assertContains('x-request-id', $replay->forwarded_headers);
        $this->assertNull($replay->error);
    }

    public function test_a_301_is_recorded_as_the_status_and_redirects_are_not_followed(): void
    {
        Http::fake([
            'https://redirect.test/from' => Http::response('moved', 301, [
                'Location' => 'http://127.0.0.1/steal',
            ]),
            'http://127.0.0.1/steal' => Http::response('should-not-run', 200),
        ]);

        $capture = CapturedRequest::factory()->create();
        $replay = Replay::factory()->for($capture)->create([
            'target_url' => 'https://redirect.test/from',
            'response_snippet' => null,
            'status_code' => null,
            'forwarded_headers' => [],
        ]);

        (new ReplayCapturedRequest($replay->id))->handle(
            $this->app->make(ReplayTargetValidator::class),
        );

        $fresh = $replay->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame(301, $fresh->status_code);
        $this->assertSame(base64_encode('moved'), $fresh->response_snippet);
        $this->assertNull($fresh->error);

        Http::assertSentCount(1);
    }

    public function test_auth_headers_are_not_forwarded_unless_opted_in(): void
    {
        Http::fake([
            'https://example.test/hook' => Http::response('ok', 200),
        ]);

        $capture = CapturedRequest::factory()->create([
            'headers' => [
                'content-type' => ['application/json'],
                'authorization' => ['Bearer secret-token'],
            ],
        ]);
        $replay = Replay::factory()->for($capture)->create([
            'target_url' => 'https://example.test/hook',
            'forwarded_headers' => [],
        ]);

        (new ReplayCapturedRequest($replay->id))->handle(
            $this->app->make(ReplayTargetValidator::class),
        );

        if ($replay->fresh()?->error !== null) {
            $this->fail('replay error: '.$replay->fresh()->error);
        }

        Http::assertSent(function (Request $request): bool {
            $contentType = $request->header('Content-Type') ?: $request->header('content-type');

            return $contentType === ['application/json']
                && ! $request->hasHeader('Authorization')
                && ! $request->hasHeader('authorization');
        });

        $this->assertSame(['content-type'], $replay->fresh()?->forwarded_headers);
    }

    public function test_replay_content_type_uses_the_byte_exact_header_not_the_lossy_column(): void
    {
        Http::fake([
            'https://example.test/hook' => Http::response('ok', 200),
        ]);

        $raw = "application/caf\xe9";
        $capture = CapturedRequest::factory()->create([
            'content_type' => 'application/caf?',
            'headers' => [
                'content-type' => [[
                    'encoding' => 'base64',
                    'value' => base64_encode($raw),
                ]],
            ],
        ]);
        $replay = Replay::factory()->for($capture)->create([
            'target_url' => 'https://example.test/hook',
            'forwarded_headers' => [],
        ]);

        (new ReplayCapturedRequest($replay->id))->handle(
            $this->app->make(ReplayTargetValidator::class),
        );

        Http::assertSent(function (Request $request) use ($raw): bool {
            $contentType = $request->header('Content-Type') ?: $request->header('content-type');

            return $contentType === [$raw];
        });
    }

    public function test_a_rejected_target_is_recorded_and_does_not_leave_the_box(): void
    {
        Http::fake();

        $this->app->instance(
            ReplayTargetValidator::class,
            new ReplayTargetValidator(new RecordingDnsResolver([
                'evil.test' => ['169.254.169.254'],
            ]), false),
        );

        $capture = CapturedRequest::factory()->create();
        $replay = Replay::factory()->for($capture)->create([
            'target_url' => 'https://evil.test/',
            'forwarded_headers' => [],
        ]);

        (new ReplayCapturedRequest($replay->id))->handle(
            $this->app->make(ReplayTargetValidator::class),
        );

        Http::assertNothingSent();
        $fresh = $replay->fresh();
        $this->assertNotNull($fresh);
        $this->assertNotNull($fresh->error);
        $this->assertNull($fresh->status_code);
        $this->assertTrue(mb_check_encoding($fresh->error, 'UTF-8'));
        $this->assertDoesNotMatchRegularExpression('/[^\x20-\x7E]/', $fresh->error);
    }

    public function test_opt_in_forwards_authorization_and_records_the_name(): void
    {
        Http::fake([
            'https://example.test/hook' => Http::response('ok', 200),
        ]);

        $capture = CapturedRequest::factory()->create([
            'headers' => [
                'authorization' => ['Bearer secret-token'],
            ],
        ]);
        $replay = Replay::factory()->for($capture)->create([
            'target_url' => 'https://example.test/hook',
            'forwarded_headers' => ['authorization'],
        ]);

        // The job reads the intended opt-in from the replay row: a non-empty
        // forwarded_headers placeholder is wrong. Opt-in is a constructor flag.
        (new ReplayCapturedRequest($replay->id, forwardSensitive: true))->handle(
            $this->app->make(ReplayTargetValidator::class),
        );

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization'));
        $this->assertContains('authorization', $replay->fresh()->forwarded_headers);
    }

    public function test_the_detail_page_lists_replays_and_treats_301_as_a_status_not_an_error(): void
    {
        $user = User::factory()->create();
        $endpoint = Endpoint::factory()->for($user)->create();
        $capture = CapturedRequest::factory()->for($endpoint)->create();
        Replay::factory()->for($capture)->create([
            'status_code' => 301,
            'error' => null,
            'response_snippet' => base64_encode('moved'),
            'forwarded_headers' => ['content-type'],
        ]);

        $this->actingAs($user)
            ->get(route('captured-requests.show', [
                'endpoint' => $endpoint,
                'capturedRequest' => $capture,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('captured-requests/Show')
                ->has('replays', 1)
                ->where('replays.0.status_code', 301)
                ->where('replays.0.error', null)
                ->where('replays.0.response_snippet', base64_encode('moved'))
                ->where('allow_private_targets', false),
            );
    }
}
