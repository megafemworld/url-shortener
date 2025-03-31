<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Symfony\Component\HttpFoundation\Response;
use App\Models\User;
use App\Http\Middleware\LoginRateLimiter;
use App\Http\Middleware\JWTMiddleware;
use App\Http\Middleware\Authenticate;
use Illuminate\Routing\Controller;

class AuthController extends Controller
{

    /**
     * Create a new AuthController Instance.
     * 
     * @return void
     */

     public function __construct()
     {
        $this->middleware(function ($request, $next) {
            if (!auth()->check()) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
            return $next($request);
        })->except(['login', 'register']);
    }
    /**
     * Get a JWT via given credentials.
     * 
     * @return \Illuminate\Http\JsonResponse
     */

     public function login(Request $request) {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $this->createNewToken($token);
     }

     /**
      * Register a user.
      *
      * @return \Illuminate\Http|jsonResponse
      */

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|between:2,100',
            'email' => 'required|string|email|max:100|unique:users',
            'password' => 'required|string|confirmed|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $user = User::create(array_merge(
            $validator->validated(),
            ['password' => bcrypt($request->password)]
        ));

        // Immediately log the user in
        $token = JWTAuth::fromUser($user);

        return response()->json([
            'message' => 'User successfully registered',
            'user' => $user,
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60,
        ], 201);
    }

    /**
     * Log the user out (Invalidate the token).
     */

    public function logout()
    {
        // try {
        //     JWTAuth::invalidate(JWTAuth::getToken());
            
        //     return response()->json([
        //         'message' => 'User successfully signed out'
        //     ]);
        // } catch (JWTException $e) {
        //     return response()->json([
        //         'error' => 'Failed to logout, please try again'
        //     ], 500);
        // }
        try{    
            auth()->logout();

            return response()->json([
                'message' => 'User successfully sign out'
            ]);
        } catch (JWTException $e) {
            return response()->json([
                'error' => 'Failed to logout, please try again'
            ], 500);
        }
    }

    /**
     * Refresh a token
     * 
     * @return \Illuminate\Http\JsonResponse
     */

    public function refresh()
    {
        return $this->createNewToken(auth()->refresh());
    }

    /**
     * Get the authenticated user.
     * 
     * @return \Illuminate\Http\JsonResponse
     */

    public function userProfile()
    {
        return response()->json(auth()->user());
    }

    /**
     * Get the token array structure.
     * 
     * @param string $token
     * 
     * @return \Illuminate\Http\JsonResponse
     */

     protected function createNewToken($token)
     {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60,
            'user' => auth()->user()
        ]);
     }
}
