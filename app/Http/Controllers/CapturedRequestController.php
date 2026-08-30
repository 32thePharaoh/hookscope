<?php

namespace App\Http\Controllers;

use App\Capture\CapturedRequestPresenter;
use App\Models\Replay;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CapturedRequestController extends Controller
{
    public function show(Request $request, int $endpoint, int $capturedRequest): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $owned = $user->endpoints()->findOrFail($endpoint);
        $capture = $owned->capturedRequests()->findOrFail($capturedRequest);

        $replays = $capture->replays()
            ->latest('id')
            ->get()
            ->map(fn (Replay $replay): array => [
                'id' => $replay->id,
                'target_url' => $replay->target_url,
                'status_code' => $replay->status_code,
                'duration_ms' => $replay->duration_ms,
                'error' => $replay->error,
                'response_snippet' => $replay->response_snippet,
                'forwarded_headers' => $replay->forwarded_headers,
                'created_at' => $replay->created_at?->toIso8601String(),
            ])
            ->values();

        return Inertia::render('captured-requests/Show', [
            'endpoint' => [
                'id' => $owned->id,
                'name' => $owned->name,
                'token' => $owned->token,
            ],
            'capture' => CapturedRequestPresenter::forDetail($capture),
            'replays' => $replays,
            'allow_private_targets' => (bool) config('hookscope.allow_private_targets'),
        ]);
    }
}
