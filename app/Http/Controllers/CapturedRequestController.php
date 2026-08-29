<?php

namespace App\Http\Controllers;

use App\Capture\CapturedRequestPresenter;
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

        return Inertia::render('captured-requests/Show', [
            'endpoint' => [
                'id' => $owned->id,
                'name' => $owned->name,
                'token' => $owned->token,
            ],
            'capture' => CapturedRequestPresenter::forDetail($capture),
        ]);
    }
}
