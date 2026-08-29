<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EnrichCapturedRequest implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $capturedRequestId) {}

    public function handle(): void
    {
        //
    }
}
