<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\Response;

class SessionController extends Controller
{
    public function csrf(Request $request): JsonResponse
    {
        return response()->json([
            'csrf_token' => $request->session()->token(),
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        event(new Registered($user));

        return response()->json([
            'user' => $user->only(['id', 'name', 'email', 'created_at']),
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $request->authenticate();
        $user = $request->user();

        if (! $user?->is_admin) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json([
                'message' => 'This account does not have administrator access.',
            ], 403);
        }

        $request->session()->regenerate();

        return response()->json([
            'user' => $user->only(['id', 'name', 'email']),
        ]);
    }

    public function currentUser(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user()->only(['id', 'name', 'email']),
        ]);
    }

    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
