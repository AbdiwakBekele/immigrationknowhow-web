<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\ServiceProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ProviderFavoritesController extends Controller
{
    public function toggle(Request $request, ServiceProvider $provider): JsonResponse
    {
        if (! Schema::hasTable('provider_favorites')) {
            return response()->json([
                'success' => false,
                'message' => 'Favorites are currently unavailable.',
            ], 503);
        }

        $user = $request->user();
        abort_unless($user && $user->hasRole('user'), 403);

        $isOwner = $user->serviceProvider
            && (int) $user->serviceProvider->getKey() === (int) $provider->getKey();

        abort_if($isOwner, 422, 'You cannot save your own provider listing.');
        abort_unless($provider->is_active, 404);

        $viewerCountry = is_string($user->country) ? trim((string) $user->country) : '';
        $provider->loadMissing('user:id,country');
        $providerCountry = is_string($provider->user?->country) ? trim((string) $provider->user->country) : '';

        if ($viewerCountry !== '' && $providerCountry !== '' && $viewerCountry !== $providerCountry) {
            abort(404);
        }

        $result = $user->favoriteServiceProviders()->toggle([$provider->getKey()]);
        $favorited = count($result['attached'] ?? []) > 0;

        return response()->json([
            'success' => true,
            'message' => $favorited
                ? 'Provider saved to your favorites.'
                : 'Provider removed from your favorites.',
            'data' => [
                'favorited' => $favorited,
            ],
        ]);
    }
}
