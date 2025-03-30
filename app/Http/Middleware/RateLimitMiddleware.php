<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

class RateLimitMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @param int $maxRequests Maximum number of requests allowed
     * @param int $decayMinutes Time window in minutes
     * @return mixed
     */
    public function handle(Request $request, Closure $next, int $maxRequests = 60, int $decayMinutes = 1): mixed
    {
        // Get client identifier (user ID if authenticated, Ip Address otherwise)
        $key = $this->resolveRequestSignature($request);

        // Use Redis to track requests
        $current = Redis::get($key) ?: 0;

        // If current requests exceed the maximum, return 429 Too many Requests
        if ($current >= $maxRequests) {
            return response()->json([
                'message' => 'Too many requests. Please try again later.',
                'retry_after' => Redis::ttl($key),
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        // Increement the counter
        Redis::incr($key);

        // Set expiry om first request
        if ($current == 0) {
            Redis::expire($key, $decayMinutes * 60);
        }

        // Add headers to response
        $response = $next($request);

        // Add rate limit headers to response
        return $this->addHeaders(
            $response,
            $maxRequests,
            $maxRequests - $current - 1,
            Redis::ttl($key)
        );
    }

    /**
     * Resolve request signature for rate limiting.
     * 
     * @param \Illuminate\Http\Request $request
     * @return string
     */
    protected function resolveRequestSignature(Request $request): string
    {
        $identifier = $request->user()
            ? 'user:' . $request->user()->id
            : 'ip:' . $request->ip();

        // Add route and method information for more granular rate limiting
        return 'rate_limit' . $identifier . ':' . $request->route()->getName() . ':' . $request->method();
    }

    /**
     * Add rate limit headers to the response
     * 
     * @param \Symfony\Component\HttpFoundation\Response $response
     * @param int $maxRequests
     * @param int $remainingRequests
     * @param int $retryAfter 
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function addHeaders($response, $maxRequests, $remainingRequests, $retryAfter): Response
    {
        $response->headers->add([
            'X-RateLimit-Limit' => $maxRequests,
            'X-RateLimit-Remaining' => max(0, $remainingRequests),
            'X-RateLimit-Reset' => $retryAfter,
        ]);

        return $response;
    }
}
