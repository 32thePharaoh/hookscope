<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RejectCaptureProbes
{
    public function handle(Request $request, Closure $next): Response
    {
        if (\in_array($request->getRealMethod(), ['HEAD', 'OPTIONS'], true)) {
            return response('', 405);
        }

        return $next($request);
    }
}
