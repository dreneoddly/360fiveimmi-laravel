<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            User::query()
                ->select(['id', 'name', 'email', 'created_at'])
                ->orderBy('id')
                ->paginate(20),
        );
    }

    public function admins(): JsonResponse
    {
        return response()->json(
            User::query()
                ->where('is_admin', true)
                ->select(['id', 'name', 'email', 'is_admin', 'created_at'])
                ->orderBy('id')
                ->paginate(20),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(12)],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);
        $user->forceFill(['is_admin' => true])->save();

        return response()->json([
            'user' => $user->only(['id', 'name', 'email', 'is_admin', 'created_at']),
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        abort_unless($user->is_admin, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => ['nullable', 'confirmed', Password::min(12)],
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
        }
        $user->save();

        return response()->json([
            'user' => $user->only(['id', 'name', 'email', 'is_admin', 'created_at']),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse|Response
    {
        abort_unless($user->is_admin, 404);

        if ($user->is($request->user())) {
            return response()->json(['message' => 'You cannot remove your own admin account.'], 422);
        }

        $user->delete();

        return response()->noContent();
    }
}
