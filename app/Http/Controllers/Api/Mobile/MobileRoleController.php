<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\UserResource;
use App\Support\UserRoleAccounts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MobileRoleController extends Controller
{
    public function meta(Request $request): JsonResponse
    {
        return $this->success('OK', UserRoleAccounts::meta($request->user()));
    }

    public function enableSeeker(Request $request): JsonResponse
    {
        try {
            $user = UserRoleAccounts::enableSeeker($request->user(), $request->all());
        } catch (ValidationException $exception) {
            return $this->error($exception->getMessage(), $exception->errors(), 422);
        }

        return $this->success('Service seeker account added.', [
            'user' => (new UserResource($user))->resolve(),
        ]);
    }

    public function startProvider(Request $request): JsonResponse
    {
        try {
            $user = UserRoleAccounts::startProvider($request->user());
        } catch (ValidationException $exception) {
            return $this->error($exception->getMessage(), $exception->errors(), 422);
        }

        return $this->success('Provider setup started.', [
            'user' => (new UserResource($user))->resolve(),
        ]);
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
            'errors' => (object) $errors,
        ], $status);
    }
}
