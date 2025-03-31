<?php

namespace App\Http\Middleware;

use Closure;
use JWTAuth;
use Exceptions;
use Tymon\JWTAuth\Http\Middleware\BaseMiddleware;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Illuminate\request\Request;

class JWTMiddleware extends BaseMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return response()->json(['error' => 'User not found'], 404);
            }
        } catch (Exception $e) {
            if ($e instanceof TokenInvalidException) {
                return response()->json(['message' => 'Token is invalid'], 401);
            } elseif ($e instanceof TokenExpiredException) {
                return response()->json(['message' => 'Token has expired', 'error' => 'token_expired'], 401);
            } else {
                return response()->json(['message' => 'Authorization Token not found'], 401);
            }
        }

        return $next($request);
    }
}