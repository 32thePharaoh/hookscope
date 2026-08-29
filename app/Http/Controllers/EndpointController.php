<?php

namespace App\Http\Controllers;

use App\Capture\CapturedRequestPresenter;
use App\Capture\CaptureDropCounter;
use App\Http\Requests\StoreEndpointRequest;
use App\Models\CapturedRequest;
use App\Models\Endpoint;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EndpointController extends Controller
{
    private const CAPTURES_PER_PAGE = 25;

    public function index(Request $request): Response
    {
        $user = $this->user($request);

        return Inertia::render('Dashboard', [
            'endpoints' => $user->endpoints()
                ->latest('id')
                ->get()
                ->map(fn (Endpoint $endpoint): array => [
                    'id' => $endpoint->id,
                    'name' => $endpoint->name,
                    'token' => $endpoint->token,
                    'retention_days' => $endpoint->retention_days,
                    'created_at' => $endpoint->created_at?->toIso8601String(),
                ])
                ->values(),
        ]);
    }

    public function store(StoreEndpointRequest $request): RedirectResponse
    {
        $endpoint = $this->user($request)->endpoints()->create([
            'name' => $request->string('name')->toString(),
            'retention_days' => $request->integer('retention_days') ?: 7,
        ]);

        return to_route('endpoints.show', $endpoint);
    }

    public function show(Request $request, int $endpoint): Response
    {
        $owned = $this->user($request)->endpoints()->findOrFail($endpoint);

        // Cursor pagination so Phase 7 can prepend on page one without
        // shifting offset pages. Body stays off this query on purpose.
        $captures = $owned->capturedRequests()
            ->select(CapturedRequestPresenter::LIST_COLUMNS)
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->cursorPaginate(self::CAPTURES_PER_PAGE)
            ->withQueryString()
            ->through(fn (CapturedRequest $capture): array => CapturedRequestPresenter::forList($capture));

        return Inertia::render('endpoints/Show', [
            'endpoint' => [
                'id' => $owned->id,
                'name' => $owned->name,
                'token' => $owned->token,
                'retention_days' => $owned->retention_days,
            ],
            'captures' => $captures,
            'drops_in_last_24h' => CaptureDropCounter::count($owned->token),
            'on_first_page' => $captures->previousCursor() === null,
        ]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
