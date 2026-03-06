<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\Token;
use Laravel\Passport\RefreshToken;

class AuthController extends Controller
{
    use ApiResponse;

    /**
     * Register a new user
     *
     * @param RegisterRequest $request
     * @return JsonResponse
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'username' => $request->username,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
            ]);

            // Create token with custom scopes
            $tokenResult = $user->createToken(
                'Personal Access Token',
                ['*'] // You can define custom scopes like ['read', 'write']
            );

            $token = $tokenResult->accessToken;

            DB::commit();

            return response()->error([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'username' => $user->username,
                    'phone' => $user->phone,
                    'created_at' => $user->created_at->toISOString(),
                ],
                'access_token' => $token,
                'token_type' => 'Bearer',
                'expires_at' => $tokenResult->token->expires_at->toISOString(),
            ], 'User registered successfully', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return $this->errorResponse(
                'Registration failed. Please try again.',
                500,
                config('app.debug') ? ['exception' => $e->getMessage()] : null
            );
        }
    }

    /**
     * Login user and create token
     *
     * @param LoginRequest $request
     * @return JsonResponse
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = [
            'email' => $request->email,
            'password' => $request->password
        ];

        if (!Auth::attempt($credentials)) {
            return $this->unauthorizedResponse('Invalid credentials');
        }

        $user = Auth::user();
        
        // Revoke previous tokens if needed (optional - for single session)
        // $user->tokens()->delete();

        $tokenResult = $user->createToken(
            'Personal Access Token',
            ['*'],
            $request->remember_me ? now()->addMonths(6) : now()->addDays(30)
        );

        $token = $tokenResult->accessToken;

        return $this->successResponse([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'username' => $user->username,
                'phone' => $user->phone,
                'avatar' => $user->avatar,
            ],
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_at' => $tokenResult->token->expires_at->toISOString(),
        ], 'Login successful');
    }

    /**
     * Get authenticated user details
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function user(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return $this->unauthorizedResponse('User not authenticated');
        }

        return $this->successResponse([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'username' => $user->username,
                'phone' => $user->phone,
                'avatar' => $user->avatar,
                'email_verified_at' => $user->email_verified_at?->toISOString(),
                'created_at' => $user->created_at->toISOString(),
            ],
        ], 'User data retrieved successfully');
    }

    /**
     * Logout user (revoke token)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $token = $request->user()->token();
            $token->revoke();

            // Optionally revoke refresh tokens
            DB::table('oauth_refresh_tokens')
                ->where('access_token_id', $token->id)
                ->update(['revoked' => true]);

            return $this->successResponse(null, 'Successfully logged out');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Logout failed',
                500,
                config('app.debug') ? ['exception' => $e->getMessage()] : null
            );
        }
    }

    /**
     * Logout from all devices (revoke all tokens)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function logoutAll(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Revoke all tokens
            $user->tokens->each(function ($token) {
                $token->revoke();
            });

            // Revoke all refresh tokens
            DB::table('oauth_refresh_tokens')
                ->whereIn('access_token_id', $user->tokens->pluck('id'))
                ->update(['revoked' => true]);

            return $this->successResponse(null, 'Successfully logged out from all devices');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Logout failed',
                500,
                config('app.debug') ? ['exception' => $e->getMessage()] : null
            );
        }
    }

    /**
     * Refresh token
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function refresh(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Revoke current token
            $request->user()->token()->revoke();

            // Create new token
            $tokenResult = $user->createToken(
                'Personal Access Token',
                ['*']
            );

            $token = $tokenResult->accessToken;

            return $this->successResponse([
                'access_token' => $token,
                'token_type' => 'Bearer',
                'expires_at' => $tokenResult->token->expires_at->toISOString(),
            ], 'Token refreshed successfully');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Token refresh failed',
                500,
                config('app.debug') ? ['exception' => $e->getMessage()] : null
            );
        }
    }

    /**
     * Get active tokens for the authenticated user
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function tokens(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $tokens = $user->tokens()->where('revoked', false)->get()->map(function ($token) {
            return [
                'id' => $token->id,
                'name' => $token->name,
                'scopes' => $token->scopes,
                'created_at' => $token->created_at->toISOString(),
                'expires_at' => $token->expires_at?->toISOString(),
            ];
        });

        return $this->successResponse([
            'tokens' => $tokens
        ], 'Tokens retrieved successfully');
    }

    /**
     * Revoke a specific token
     *
     * @param Request $request
     * @param string $tokenId
     * @return JsonResponse
     */
    public function revokeToken(Request $request, string $tokenId): JsonResponse
    {
        try {
            $user = $request->user();
            
            $token = $user->tokens()->where('id', $tokenId)->first();

            if (!$token) {
                return $this->notFoundResponse('Token not found');
            }

            $token->revoke();

            // Revoke associated refresh tokens
            DB::table('oauth_refresh_tokens')
                ->where('access_token_id', $token->id)
                ->update(['revoked' => true]);

            return $this->successResponse(null, 'Token revoked successfully');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Token revocation failed',
                500,
                config('app.debug') ? ['exception' => $e->getMessage()] : null
            );
        }
    }
}
