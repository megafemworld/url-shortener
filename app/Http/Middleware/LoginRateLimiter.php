<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class LoginRateLimiter
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Create a key based on IP and email (if present)
        $key = 'login.' . ($request->ip() ?? '0.0.0.0');
        if ($request->has('email')) {
            $key .= ':' . sha1($request->input('email'));
        }

        // Limit to 5 attempts per minute
        if (RateLimter::toManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'message' => 'Too many login attempts. Please try again in ' . $seconds . ' seconds.',
                'try_after' => $seconds,
            ], SymfonyResponse::HTTP_TOO_MANY_REQUESTS);
        }

        // Add a hit to the rate limiter
        RateLimiter::hit($key, 60);

        $response = $next($request);

        // If the response indicate success, clear the rate limiter
        if ($response->status() === SymfonyResponse::HTTP_OK) {
            RateLimiter::clear($key);
        }

        return $response;
    }

}