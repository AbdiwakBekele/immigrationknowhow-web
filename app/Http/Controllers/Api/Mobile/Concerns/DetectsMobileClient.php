<?php

namespace App\Http\Controllers\Api\Mobile\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait DetectsMobileClient
{
    protected function mobileClientIsIos(Request $request): bool
    {
        $header = strtolower(trim((string) $request->header('X-IKH-Client', '')));

        return $header === 'ios';
    }

    protected function iosStripeCheckoutBlockedResponse(Request $request, string $iapMessage): ?JsonResponse
    {
        if (! $this->mobileClientIsIos($request)) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => $iapMessage,
            'errors' => (object) [],
        ], 422);
    }
}
