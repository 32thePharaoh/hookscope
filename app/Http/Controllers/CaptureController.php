<?php

namespace App\Http\Controllers;

use App\Capture\HeaderSanitizer;
use App\Jobs\EnrichCapturedRequest;
use App\Models\CapturedRequest;
use App\Models\Endpoint;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

class CaptureController extends Controller
{
    public function __invoke(Request $request, string $token): Response
    {
        $endpoint = Endpoint::query()->where('token', $token)->first();

        if ($endpoint === null) {
            abort(404);
        }

        $body = $request->getContent();

        $captured = CapturedRequest::query()->create([
            'endpoint_id' => $endpoint->id,
            'method' => $request->method(),
            'path' => $request->path(),
            'query' => $request->getQueryString(),
            'headers' => HeaderSanitizer::sanitize($request->headers->all()),
            'body' => $body,
            'body_encoding' => mb_check_encoding($body, 'UTF-8') ? 'utf-8' : 'binary',
            'content_type' => HeaderSanitizer::scalar($request->headers->get('Content-Type')),
            'ip' => $request->ip(),
            'size_bytes' => strlen($body),
            'received_at' => now(),
        ]);

        try {
            EnrichCapturedRequest::dispatch($captured->id);
        } catch (Throwable $exception) {
            Log::warning('Failed to dispatch capture enrichment', [
                'captured_request_id' => $captured->id,
                'exception' => $exception,
            ]);
        }

        return response('', 200);
    }
}
