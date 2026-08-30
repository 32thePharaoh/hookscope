<?php

namespace Tests\Feature;

use App\Models\CapturedRequest;
use App\Models\Endpoint;
use App\Models\Replay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use RuntimeException;
use Tests\TestCase;

class PruneCapturedRequestsTest extends TestCase
{
    use RefreshDatabase;

    public function test_chunked_prune_deletes_every_expired_row_across_multiple_chunk_boundaries(): void
    {
        Date::setTestNow('2026-08-30 12:00:00');

        $endpoint = Endpoint::factory()->create(['retention_days' => 7]);
        $expired = CapturedRequest::factory()
            ->for($endpoint)
            ->count(50)
            ->create(['received_at' => now()->subDays(8)]);

        foreach ($expired as $capture) {
            Replay::factory()->for($capture)->create();
        }

        $this->artisan('hookscope:prune', ['--chunk' => 10])
            ->assertSuccessful();

        $this->assertSame(0, $endpoint->capturedRequests()->count());
        $this->assertSame(0, Replay::query()->count());
    }

    public function test_a_row_exactly_at_the_cutoff_is_kept(): void
    {
        Date::setTestNow('2026-08-30 12:00:00');

        $endpoint = Endpoint::factory()->create(['retention_days' => 7]);
        $cutoff = now()->subDays(7);

        $kept = CapturedRequest::factory()->for($endpoint)->create([
            'received_at' => $cutoff,
        ]);
        $gone = CapturedRequest::factory()->for($endpoint)->create([
            'received_at' => $cutoff->copy()->subSecond(),
        ]);

        $this->artisan('hookscope:prune')->assertSuccessful();

        $this->assertTrue(CapturedRequest::query()->whereKey($kept->id)->exists());
        $this->assertFalse(CapturedRequest::query()->whereKey($gone->id)->exists());
    }

    public function test_retention_is_keyed_on_received_at_not_created_at(): void
    {
        Date::setTestNow('2026-08-30 12:00:00');

        $endpoint = Endpoint::factory()->create(['retention_days' => 7]);

        $recentReceipt = CapturedRequest::factory()->for($endpoint)->create([
            'received_at' => now()->subDay(),
            'created_at' => now()->subDays(30),
        ]);
        $staleReceipt = CapturedRequest::factory()->for($endpoint)->create([
            'received_at' => now()->subDays(8),
            'created_at' => now(),
        ]);

        $this->artisan('hookscope:prune')->assertSuccessful();

        $this->assertTrue(CapturedRequest::query()->whereKey($recentReceipt->id)->exists());
        $this->assertFalse(CapturedRequest::query()->whereKey($staleReceipt->id)->exists());
    }

    public function test_each_endpoint_uses_its_own_retention_days(): void
    {
        Date::setTestNow('2026-08-30 12:00:00');

        $week = Endpoint::factory()->create(['retention_days' => 7]);
        $month = Endpoint::factory()->create(['retention_days' => 30]);

        $weekOld = CapturedRequest::factory()->for($week)->create([
            'received_at' => now()->subDays(8),
        ]);
        $monthKept = CapturedRequest::factory()->for($month)->create([
            'received_at' => now()->subDays(8),
        ]);

        $this->artisan('hookscope:prune')->assertSuccessful();

        $this->assertFalse(CapturedRequest::query()->whereKey($weekOld->id)->exists());
        $this->assertTrue(CapturedRequest::query()->whereKey($monthKept->id)->exists());
    }

    public function test_prune_does_not_eat_seeded_demo_captures(): void
    {
        Date::setTestNow('2026-08-30 12:00:00');

        $this->seed();

        $before = CapturedRequest::query()->count();
        $this->assertGreaterThan(0, $before);

        $this->artisan('hookscope:prune')->assertSuccessful();

        $this->assertSame($before, CapturedRequest::query()->count());
    }

    public function test_a_cache_outage_skips_the_run_instead_of_throwing(): void
    {
        Date::setTestNow('2026-08-30 12:00:00');

        $endpoint = Endpoint::factory()->create(['retention_days' => 7]);
        CapturedRequest::factory()->for($endpoint)->create([
            'received_at' => now()->subDays(8),
        ]);

        Cache::shouldReceive('lock')
            ->once()
            ->andThrow(new RuntimeException('redis down'));

        $this->artisan('hookscope:prune')->assertSuccessful();

        $this->assertSame(1, $endpoint->capturedRequests()->count());
    }

    public function test_an_held_lock_skips_rather_than_running_a_second_prune(): void
    {
        Date::setTestNow('2026-08-30 12:00:00');

        $endpoint = Endpoint::factory()->create(['retention_days' => 7]);
        CapturedRequest::factory()->for($endpoint)->create([
            'received_at' => now()->subDays(8),
        ]);

        $lock = Cache::lock('hookscope:prune', 60);
        $this->assertTrue($lock->get());

        try {
            $this->artisan('hookscope:prune')->assertSuccessful();
            $this->assertSame(1, $endpoint->capturedRequests()->count());
        } finally {
            $lock->release();
        }
    }

    public function test_the_scheduler_lists_hourly_prune(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('hookscope:prune')
            ->assertSuccessful();
    }
}
