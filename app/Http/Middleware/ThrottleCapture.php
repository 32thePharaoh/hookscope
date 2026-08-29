<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Throttles captures, but fails open when the rate limiter's cache store is down.
 *
 * The limiter runs before the controller, so a dead Redis would otherwise 500 the
 * request before the row is ever inserted. Losing the capture is a worse outcome
 * than not throttling it; nginx still caps body size in front of this.
 */
class ThrottleCapture
{
    public function __construct(private readonly ThrottleRequests $throttle) {}

    public function handle(Request $request, Closure $next): Response
    {
        $reachedController = false;

        $downstream = function (Request $request) use ($next, &$reachedController): Response {
            $reachedController = true;

            return $next($request);
        };

        try {
            return $this->throttle->handle($request, $downstream, 'capture');
        } catch (Throwable $exception) {
            // A real 429: the limiter's response callback surfaces as
            // HttpResponseException, the bare global limit as ThrottleRequestsException.
            if ($exception instanceof HttpResponseException || $exception instanceof ThrottleRequestsException) {
                throw $exception;
            }

            // Once the request has gone downstream the exception is the
            // controller's, not the limiter's.
            if ($reachedController) {
                throw $exception;
            }

            Log::warning('Capture throttle unavailable, allowing request', [
                'exception' => $exception,
            ]);

            return $next($request);
        }
    }
}
