<?php

namespace App\Jobs;

use App\Capture\CapturedRequestPresenter;
use App\Events\RequestCaptured;
use App\Models\CapturedRequest;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EnrichCapturedRequest implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $capturedRequestId) {}

    public function handle(): void
    {
        $capture = CapturedRequest::query()
            ->select(CapturedRequestPresenter::LIST_COLUMNS)
            ->find($this->capturedRequestId);

        if ($capture === null) {
            return;
        }

        RequestCaptured::dispatch(
            $capture->endpoint_id,
            CapturedRequestPresenter::forList($capture),
        );
    }
}
