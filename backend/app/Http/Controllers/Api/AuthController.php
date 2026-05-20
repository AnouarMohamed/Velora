<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\Users\UserWriteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Controller handling authentication and registration.
 *
 * This controller manages user lifecycle events such as registration, login, and logout
 * using Laravel Sanctum for token-based authentication.
 */
class AuthController extends Controller
{
    /**
     * @param  UserWriteService  $users  Service for handling user creation and updates.
     */
    public function __construct(private readonly UserWriteService $users) {}

    /**
     * Register a new user.
     *
     * This endpoint is public. It validates the registration request,
     * creates a new user via the UserWriteService, and returns an authentication token.
     *
     * @param  RegisterRequest  $request  Validated registration data (name, email, password, role).
     * @return JsonResponse 201 Created with the user and SPA token.
     */
    public function register(RegisterRequest $request)
    {
        // Delegate user creation to the service
        $user = $this->users->create($request->validated());

        // Create a Sanctum token for the new user
        $token = $user->createToken('spa')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
        ], 201);
    }

    /**
     * Authenticate a user and return a token.
     *
     * This endpoint is public. It attempts to authenticate the user using the provided credentials.
     * If successful, it returns a new SPA token.
     *
     * @param  LoginRequest  $request  Validated login credentials (email, password).
     * @return JsonResponse 200 OK with token or 422 on failure.
     */
    public function login(LoginRequest $request)
    {
        $credentials = $request->validated();

        // Attempt authentication using Laravel's Auth facade
        if (! Auth::attempt($credentials)) {
            return response()->json(['message' => 'Identifiants invalides.'], 422);
        }

        /** @var User $user */
        $user = User::where('email', $credentials['email'])->firstOrFail();
        // Issue a new Sanctum token for the session
        $token = $user->createToken('spa')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
    }

    /**
     * Log out the user by revoking their current token.
     *
     * This endpoint requires authentication.
     *
     * @return JsonResponse 200 OK message.
     */
    public function logout(Request $request)
    {
        // Delete the token that was used for this request
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Déconnexion réussie.']);
    }

    /**
     * Get the authenticated user's information.
     *
     * This endpoint requires authentication.
     *
     * @return JsonResponse 200 OK with user object.
     */
    public function user(Request $request)
    {
        // Return the user associated with the authentication token
        return response()->json($request->user());
    }
}
