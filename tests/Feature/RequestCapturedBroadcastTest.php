<?php

namespace Tests\Feature;

use App\Capture\CapturedRequestPresenter;
use App\Events\RequestCaptured;
use App\Jobs\EnrichCapturedRequest;
use App\Models\CapturedRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RequestCapturedBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrichment_broadcasts_list_metadata_without_the_body(): void
    {
        Event::fake([RequestCaptured::class]);

        $capture = CapturedRequest::factory()->binary()->create();

        (new EnrichCapturedRequest($capture->id))->handle();

        Event::assertDispatched(RequestCaptured::class, function (RequestCaptured $event) use ($capture): bool {
            $this->assertSame($capture->endpoint_id, $event->endpointId);
            $this->assertSame(CapturedRequestPresenter::forList($capture->fresh()), $event->broadcastWith());
            $this->assertArrayNotHasKey('body', $event->broadcastWith());
            $this->assertArrayNotHasKey('headers', $event->broadcastWith());
            $this->assertSame('private-endpoints.'.$capture->endpoint_id, $event->broadcastOn()->name);

            return true;
        });
    }

    public function test_broadcast_payload_json_encodes_for_a_binary_row(): void
    {
        $capture = CapturedRequest::factory()->binary()->create();
        $event = new RequestCaptured(
            $capture->endpoint_id,
            CapturedRequestPresenter::forList($capture),
        );

        $json = json_encode($event->broadcastWith(), JSON_THROW_ON_ERROR);

        $this->assertNotSame('', $json);
        $this->assertStringNotContainsString($capture->body, $json);
    }

    public function test_shared_props_expose_the_reverb_key_and_not_the_secret(): void
    {
        config([
            'broadcasting.connections.reverb.key' => 'public-app-key',
            'broadcasting.connections.reverb.secret' => 'must-not-leak',
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('reverbKey', 'public-app-key')
                ->missing('reverbSecret')
                ->missing('broadcasting'),
            );

        $this->assertStringNotContainsString('must-not-leak', $this->html($this->actingAs($user)->get(route('dashboard'))));
    }
}
