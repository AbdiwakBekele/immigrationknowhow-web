<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Actions\Account\DeleteUserAccount;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MobileAccountController extends Controller
{
    public function destroy(Request $request, DeleteUserAccount $deleteUserAccount): JsonResponse
    {
        try {
            $request->validate([
                'password' => ['required', 'current_password'],
                'confirmation' => ['required', 'in:DELETE'],
            ]);
        } catch (ValidationException $exception) {
            return response()->json([
                'success' => false,
                'message' => collect($exception->errors())->flatten()->first() ?: 'Validation failed.',
                'errors' => (object) $exception->errors(),
            ], 422);
        }

        /** @var User $user */
        $user = $request->user();

        try {
            $deleteUserAccount->handle($user);
        } catch (ValidationException $exception) {
            return response()->json([
                'success' => false,
                'message' => collect($exception->errors())->flatten()->first() ?: 'Unable to delete account.',
                'errors' => (object) $exception->errors(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Your account has been deleted.',
            'data' => (object) [],
        ]);
    }
}
