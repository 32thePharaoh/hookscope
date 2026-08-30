<?php

namespace App\Console\Commands;

use App\Models\CapturedRequest;
use App\Models\Endpoint;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

#[Signature('hookscope:prune {--chunk= : Rows deleted per statement}')]
#[Description('Delete captured requests older than each endpoint\'s retention_days')]
class PruneCapturedRequestsCommand extends Command
{
    public function handle(): int
    {
        $lock = $this->acquireLock();

        if ($lock === null) {
            return self::SUCCESS;
        }

        $deleted = 0;
        $chunk = $this->chunkSize();

        try {
            Endpoint::query()->orderBy('id')->each(function (Endpoint $endpoint) use ($chunk, &$deleted): void {
                $cutoff = now()->subDays($endpoint->retention_days);

                $endpoint->capturedRequests()
                    ->where('received_at', '<', $cutoff)
                    ->chunkById($chunk, function (Collection $rows) use (&$deleted): void {
                        $ids = $rows->modelKeys();
                        $deleted += CapturedRequest::query()->whereIn('id', $ids)->delete();
                    });
            });
        } finally {
            $this->releaseLock($lock);
        }

        $this->info("Pruned {$deleted} captured requests.");

        return self::SUCCESS;
    }

    private function acquireLock(): ?Lock
    {
        try {
            $lock = Cache::lock('hookscope:prune', 600);

            if (! $lock->get()) {
                $this->info('Prune already in progress; skipping.');

                return null;
            }

            return $lock;
        } catch (Throwable) {
            $this->warn('Cache unavailable; skipping prune.');

            return null;
        }
    }

    private function releaseLock(Lock $lock): void
    {
        try {
            $lock->release();
        } catch (Throwable) {
        }
    }

    private function chunkSize(): int
    {
        $chunk = (int) ($this->option('chunk') ?: config('hookscope.prune_chunk'));

        return max(1, $chunk);
    }
}
