<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\LoginRequest;
use App\Http\Requests\Mobile\RegisterRequest;
use App\Http\Resources\Mobile\UserResource;
use App\Models\User;
use App\Support\RoleHelper;
use App\Support\ServiceTypeOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function registerMeta(): JsonResponse
    {
        return $this->success('OK', [
            'service_types_user' => ServiceTypeOptions::selectOptions('user'),
            'service_types_provider' => ServiceTypeOptions::selectOptions('provider'),
        ]);
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $effectiveRole = $request->effectiveRole();

        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'onboarding_data' => [
                'registration' => [
                    'role' => $effectiveRole,
                    'service_type' => $validated['service_type'] ?? null,
                ],
            ],
        ]);

        RoleHelper::ensureExists($effectiveRole);
        $user->assignRole($effectiveRole);

        $token = $user->createToken('mobile')->plainTextToken;

        return $this->success('Registration successful', [
            'token' => $token,
            'user' => (new UserResource($user))->resolve(),
        ]);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        if (! Auth::attempt($credentials)) {
            return $this->error('Invalid credentials', [
                'email' => [__('auth.failed')],
            ], 422);
        }

        /** @var User $user */
        $user = Auth::user();
        $user->updateLastLogin();

        $token = $user->createToken('mobile')->plainTextToken;

        return $this->success('Login successful', [
            'token' => $token,
            'user' => (new UserResource($user))->resolve(),
        ]);
    }

    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = request()->user();

        return $this->success('OK', [
            'user' => (new UserResource($user))->resolve(),
        ]);
    }

    public function sendVerificationEmail(): JsonResponse
    {
        /** @var User $user */
        $user = request()->user();

        if ($user->hasVerifiedEmail()) {
            return $this->success('Email already verified.', [
                'already_verified' => true,
            ]);
        }

        $user->sendEmailVerificationNotification();

        return $this->success('Verification email sent.', [
            'already_verified' => false,
        ]);
    }

    public function logout(): JsonResponse
    {
        /** @var User $user */
        $user = request()->user();

        $user->currentAccessToken()?->delete();

        return $this->success('Logged out', []);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function success(string $message, array $data, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * @param  array<string, mixed>  $errors
     */
    private function error(string $message, array $errors = [], int $status = 422): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors === [] ? (object) [] : $errors,
        ], $status);
    }
}
