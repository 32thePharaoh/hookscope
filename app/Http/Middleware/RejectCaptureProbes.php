<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RejectCaptureProbes
{
    public function handle(Request $request, Closure $next): Response
    {
        // OPTIONS is not in the route's method list, so Laravel's own handler
        // answers it before this runs. HEAD is auto-registered alongside GET.
        if ($request->getRealMethod() === 'HEAD') {
            return response('', 405);
        }

        return $next($request);
    }
}
