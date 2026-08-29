<?php

namespace Tests\Feature;

use App\Capture\CaptureDropCounter;
use App\Models\CapturedRequest;
use App\Models\Endpoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class EndpointDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_lists_only_the_authenticated_users_endpoints(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Endpoint::factory()->for($user)->create(['name' => 'Mine']);
        Endpoint::factory()->for($other)->create(['name' => 'Theirs']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('endpoints', 1)
                ->where('endpoints.0.name', 'Mine')
                ->has('endpoints.0.token')
                ->missing('endpoints.0.capture_url'),
            );
    }

    public function test_creating_an_endpoint_scopes_it_to_the_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('endpoints.store'), ['name' => 'Stripe'])
            ->assertRedirect();

        $endpoint = Endpoint::query()->where('name', 'Stripe')->first();
        $this->assertNotNull($endpoint);
        $this->assertSame($user->id, $endpoint->user_id);
        $this->assertSame(64, strlen($endpoint->token));
    }

    public function test_creating_an_endpoint_requires_a_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('dashboard'))
            ->post(route('endpoints.store'), ['name' => ''])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHasErrors('name');
    }

    public function test_a_user_cannot_view_another_users_endpoint_by_id(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $endpoint = Endpoint::factory()->for($owner)->create();

        $this->actingAs($stranger)
            ->get(route('endpoints.show', $endpoint))
            ->assertNotFound();
    }

    public function test_a_user_cannot_view_another_users_captured_request(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $endpoint = Endpoint::factory()->for($owner)->create();
        $capture = CapturedRequest::factory()->for($endpoint)->create();
        $strangerEndpoint = Endpoint::factory()->for($stranger)->create();

        $this->actingAs($stranger)
            ->get(route('captured-requests.show', [
                'endpoint' => $endpoint,
                'capturedRequest' => $capture,
            ]))
            ->assertNotFound();

        $this->actingAs($stranger)
            ->get(route('captured-requests.show', [
                'endpoint' => $strangerEndpoint,
                'capturedRequest' => $capture,
            ]))
            ->assertNotFound();
    }

    public function test_the_capture_list_does_not_include_bodies_or_headers(): void
    {
        $user = User::factory()->create();
        $endpoint = Endpoint::factory()->for($user)->create();
        $marker = 'LIST_MUST_NOT_CONTAIN_THIS_BODY';
        CapturedRequest::factory()->for($endpoint)->create([
            'body' => $marker,
            'headers' => [
                'x-secret' => ['LIST_MUST_NOT_CONTAIN_THIS_HEADER'],
            ],
        ]);

        $response = $this->actingAs($user)
            ->get(route('endpoints.show', $endpoint))
            ->assertOk();

        $this->assertStringNotContainsString($marker, $response->getContent());
        $this->assertStringNotContainsString('LIST_MUST_NOT_CONTAIN_THIS_HEADER', $response->getContent());

        $response->assertInertia(fn (Assert $page) => $page
            ->component('endpoints/Show')
            ->has('captures.data', 1)
            ->missing('captures.data.0.body')
            ->missing('captures.data.0.headers')
            ->has('captures.data.0.id')
            ->has('captures.data.0.method')
            ->has('on_first_page'),
        );
    }

    public function test_the_capture_list_truncates_content_type_for_the_table(): void
    {
        $user = User::factory()->create();
        $endpoint = Endpoint::factory()->for($user)->create();
        CapturedRequest::factory()->for($endpoint)->create([
            'content_type' => str_repeat('a', 200),
        ]);

        $this->actingAs($user)
            ->get(route('endpoints.show', $endpoint))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('captures.data.0.content_type', str_repeat('a', 48).'…'),
            );
    }

    public function test_binary_bodies_are_encoded_before_they_become_inertia_props(): void
    {
        $user = User::factory()->create();
        $endpoint = Endpoint::factory()->for($user)->create();
        $capture = CapturedRequest::factory()->for($endpoint)->binary()->create();

        $this->actingAs($user)
            ->get(route('captured-requests.show', [
                'endpoint' => $endpoint,
                'capturedRequest' => $capture,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('captured-requests/Show')
                ->where('capture.body_encoding', 'binary')
                ->where('capture.body', base64_encode($capture->body)),
            );
    }

    public function test_utf8_bodies_are_passed_as_strings_and_odd_headers_keep_their_marker_shape(): void
    {
        $user = User::factory()->create();
        $endpoint = Endpoint::factory()->for($user)->create();
        $odd = [
            'encoding' => 'base64',
            'value' => base64_encode("caf\xe9-\xff"),
        ];
        $capture = CapturedRequest::factory()->for($endpoint)->create([
            'body' => '{"event":"invoice.paid"}',
            'body_encoding' => 'utf-8',
            'headers' => [
                'content-type' => ['application/json'],
                'x-odd' => [$odd],
            ],
        ]);

        $this->actingAs($user)
            ->get(route('captured-requests.show', [
                'endpoint' => $endpoint,
                'capturedRequest' => $capture,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('capture.body', '{"event":"invoice.paid"}')
                ->where('capture.body_encoding', 'utf-8')
                ->where('capture.headers.x-odd.0.encoding', 'base64')
                ->where('capture.headers.x-odd.0.value', $odd['value'])
                ->where('capture.headers.content-type.0', 'application/json'),
            );
    }

    public function test_the_owner_can_open_a_seeded_binary_capture_without_a_500(): void
    {
        $this->seed();

        $user = User::query()->where('email', 'demo@hookscope.test')->first();
        $endpoint = Endpoint::query()->where('name', 'Demo')->first();
        $this->assertNotNull($user);
        $this->assertNotNull($endpoint);

        $binary = CapturedRequest::query()
            ->where('endpoint_id', $endpoint->id)
            ->where('body_encoding', 'binary')
            ->first();
        $this->assertNotNull($binary);

        $this->actingAs($user)
            ->get(route('endpoints.show', $endpoint))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('captured-requests.show', [
                'endpoint' => $endpoint,
                'capturedRequest' => $binary,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('capture.body_encoding', 'binary')
                ->where('capture.headers.x-odd.0.encoding', 'base64'),
            );
    }

    public function test_drop_counter_renders_zero_when_the_cache_throws(): void
    {
        $user = User::factory()->create();
        $endpoint = Endpoint::factory()->for($user)->create();

        Cache::shouldReceive('get')
            ->andThrow(new RuntimeException('cache down'));

        $this->assertSame(0, CaptureDropCounter::count($endpoint->token));

        $this->actingAs($user)
            ->get(route('endpoints.show', $endpoint))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('drops_in_last_24h', 0),
            );
    }

    public function test_drop_counter_is_surfaced_on_the_endpoint_page(): void
    {
        $user = User::factory()->create();
        $endpoint = Endpoint::factory()->for($user)->create();
        Cache::put(CaptureDropCounter::key($endpoint->token), 4);

        $this->actingAs($user)
            ->get(route('endpoints.show', $endpoint))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('drops_in_last_24h', 4),
            );
    }

    public function test_capture_list_uses_cursor_pagination(): void
    {
        $user = User::factory()->create();
        $endpoint = Endpoint::factory()->for($user)->create();
        CapturedRequest::factory()->for($endpoint)->count(26)->create();

        $this->actingAs($user)
            ->get(route('endpoints.show', $endpoint))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('captures.data', 25)
                ->whereNotNull('captures.next_cursor')
                ->where('on_first_page', true),
            );
    }
}
