<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RejectOversizedCapture
{
    public function handle(Request $request, Closure $next): Response
    {
        $max = (int) config('hookscope.max_body_bytes');
        $length = $request->headers->get('Content-Length');

        if (is_numeric($length) && (int) $length > $max) {
            return response()->json(['message' => 'Payload too large'], 413);
        }

        if (strlen($request->getContent()) > $max) {
            return response()->json(['message' => 'Payload too large'], 413);
        }

        return $next($request);
    }
}
