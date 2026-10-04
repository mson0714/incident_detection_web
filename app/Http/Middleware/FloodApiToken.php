<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class FloodApiToken
{
    public function handle(Request $request, Closure $next)
    {
        $expected = (string) config('flood.api_token');
        if ($expected === '') {
            return response()->json(['message' => 'Flood API token is not configured.'], 503);
        }
        if (! hash_equals($expected, (string) $request->bearerToken())) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        return $next($request);
    }
}
