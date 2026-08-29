<?php

namespace Tests\Feature;

use App\Models\CapturedRequest;
use App\Models\Endpoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class DemoTrafficTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_the_documented_demo_login(): void
    {
        $this->seed();

        $user = User::query()->where('email', 'demo@hookscope.test')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('password', $user->password));
        $this->assertTrue(
            Endpoint::query()->where('name', 'Demo')->where('user_id', $user->id)->exists(),
        );

        $oddHeader = CapturedRequest::query()
            ->where('body_encoding', 'utf-8')
            ->first();
        $this->assertNotNull($oddHeader);
        $this->assertIsArray($oddHeader->headers['x-odd'][0] ?? null);
        $this->assertSame('base64', $oddHeader->headers['x-odd'][0]['encoding']);
        $this->assertNotSame('', $oddHeader->headers['x-odd'][0]['value']);

        $this->post(route('login.store'), [
            'email' => 'demo@hookscope.test',
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed();
        $this->seed();

        $this->assertSame(1, User::query()->count());
        $this->assertSame(1, Endpoint::query()->where('name', 'Demo')->count());
    }

    public function test_demo_traffic_reuses_the_demo_endpoint_and_posts_through_the_base_url(): void
    {
        $this->seed();
        $endpoint = Endpoint::query()->where('name', 'Demo')->first();
        $this->assertNotNull($endpoint);

        Http::fake(function (Request $request) {
            if (strlen($request->body()) > 1_048_576) {
                return Http::response(['message' => 'Payload too large'], 413);
            }

            return Http::response('', 200);
        });

        $this->artisan('hookscope:demo-traffic', ['--base-url' => 'http://nginx'])
            ->assertSuccessful();

        $recorded = Http::recorded();
        $this->assertGreaterThanOrEqual(20, $recorded->count());
        $this->assertLessThanOrEqual(30, $recorded->count());

        $recorded->each(function (array $pair) use ($endpoint): void {
            /** @var Request $request */
            $request = $pair[0];
            $this->assertStringStartsWith('http://nginx/in/'.$endpoint->token, $request->url());
        });

        $this->assertTrue(
            $recorded->contains(function (array $pair): bool {
                return $pair[1]->status() === 413;
            }),
        );
        $this->assertTrue(
            $recorded->contains(function (array $pair): bool {
                $values = $pair[0]->header('X-Odd');

                return isset($values[0]) && str_contains($values[0], "\xe9");
            }),
        );

        $this->artisan('hookscope:demo-traffic', ['--base-url' => 'http://nginx'])
            ->assertSuccessful();

        $this->assertSame(1, Endpoint::query()->where('name', 'Demo')->count());
    }

    public function test_demo_traffic_treats_an_oversized_413_as_success(): void
    {
        $this->seed();

        Http::fake([
            '*' => Http::response(['message' => 'Payload too large'], 413),
        ]);

        $this->artisan('hookscope:demo-traffic')
            ->assertSuccessful();
    }

    public function test_demo_traffic_fails_when_the_demo_endpoint_is_missing(): void
    {
        Http::fake();

        $this->artisan('hookscope:demo-traffic')
            ->assertFailed();
    }

    public function test_demo_traffic_clears_the_per_token_limiter_before_firing(): void
    {
        $this->seed();
        $endpoint = Endpoint::query()->where('name', 'Demo')->first();
        $this->assertNotNull($endpoint);

        $key = md5('capture'.'endpoint:'.$endpoint->token);
        RateLimiter::hit($key, 60);
        RateLimiter::hit($key, 60);
        $this->assertTrue(RateLimiter::tooManyAttempts($key, 2));

        Http::fake(fn () => Http::response('', 200));

        $this->artisan('hookscope:demo-traffic')->assertSuccessful();

        $this->assertFalse(RateLimiter::tooManyAttempts($key, 2));
    }
}
