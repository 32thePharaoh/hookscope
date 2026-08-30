<?php

namespace App\Http\Controllers;

use App\Jobs\ReplayCapturedRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReplayController extends Controller
{
    public function store(Request $request, int $endpoint, int $capturedRequest): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $owned = $user->endpoints()->findOrFail($endpoint);
        $capture = $owned->capturedRequests()->findOrFail($capturedRequest);

        $validated = $request->validate([
            'target_url' => ['required', 'string', 'max:2048', 'url:http,https'],
            'forward_sensitive' => ['sometimes', 'boolean'],
        ]);

        $replay = $capture->replays()->create([
            'target_url' => $validated['target_url'],
            'forwarded_headers' => [],
        ]);

        try {
            ReplayCapturedRequest::dispatch($replay->id, $request->boolean('forward_sensitive'));
        } catch (Throwable $exception) {
            Log::warning('Failed to dispatch replay', [
                'replay_id' => $replay->id,
                'exception' => $exception,
            ]);
            $replay->forceFill(['error' => 'Replay could not be queued.'])->save();
        }

        return to_route('captured-requests.show', [
            'endpoint' => $owned->id,
            'capturedRequest' => $capture->id,
        ]);
    }
}
