<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProviderFavoriteController extends Controller
{
    public function toggle(Request $request, ServiceProvider $provider): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->hasRole('user'), 403);

        $isOwner = $user->serviceProvider
            && (int) $user->serviceProvider->getKey() === (int) $provider->getKey();

        abort_if($isOwner, 422);

        abort_unless($provider->is_active, 404);

        $viewerCountry = is_string($user->country) ? trim((string) $user->country) : '';
        $provider->loadMissing('user:id,country');
        $providerCountry = is_string($provider->user?->country) ? trim((string) $provider->user->country) : '';
        if ($viewerCountry !== '' && $providerCountry !== '' && $viewerCountry !== $providerCountry) {
            abort(404);
        }

        $result = $user->favoriteServiceProviders()->toggle([$provider->getKey()]);
        $favorited = count($result['attached'] ?? []) > 0;

        return back()->with(
            'success',
            $favorited ? 'Provider saved to your favorites.' : 'Provider removed from your favorites.'
        );
    }
}
