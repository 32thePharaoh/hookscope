<?php

namespace Tests\Feature;

use App\Models\Endpoint;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Contracts\Broadcasting\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BroadcastChannelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // NullBroadcaster::auth is a no-op (200 for everyone). Channel
        // authorization has to run on the Reverb/Pusher broadcaster.
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'hookscope',
            'broadcasting.connections.reverb.secret' => 'secret',
            'broadcasting.connections.reverb.app_id' => 'hookscope',
        ]);

        $this->app->forgetInstance(BroadcastManager::class);
        $this->app->forgetInstance(Factory::class);

        require base_path('routes/channels.php');
    }

    public function test_a_user_cannot_subscribe_to_another_users_endpoint_channel(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $endpoint = Endpoint::factory()->for($owner)->create();

        $this->actingAs($stranger)
            ->post('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-endpoints.'.$endpoint->id,
            ])
            ->assertForbidden();
    }

    public function test_the_owner_can_subscribe_to_their_endpoint_channel(): void
    {
        $owner = User::factory()->create();
        $endpoint = Endpoint::factory()->for($owner)->create();

        $this->actingAs($owner)
            ->post('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-endpoints.'.$endpoint->id,
            ])
            ->assertOk();
    }

    public function test_channel_names_use_the_endpoint_id_not_the_token(): void
    {
        $owner = User::factory()->create();
        $endpoint = Endpoint::factory()->for($owner)->create();

        $this->actingAs($owner)
            ->post('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-endpoints.'.$endpoint->token,
            ])
            ->assertForbidden();
    }
}
