<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\LibraryItem;
use App\Models\User;
use App\Services\Library\LibraryMediaStreamService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MobileLibraryStreamController extends Controller
{
    public function show(Request $request, string $token): BinaryFileResponse|StreamedResponse
    {
        $cacheKey = 'lib_mob_media_'.$token;
        $payload = Cache::get($cacheKey);
        if (! is_array($payload)) {
            abort(404);
        }

        $user = User::query()->whereKey((int) ($payload['user_id'] ?? 0))->firstOrFail();
        $item = LibraryItem::query()->whereKey((int) ($payload['item_id'] ?? 0))->firstOrFail();

        abort_unless($item->is_active, 404);

        $region = LibraryItem::regionForCountry($user->country ?? null);
        if ($region !== null) {
            $regions = $item->regions ?? null;
            if (is_array($regions) && count($regions) > 0 && ! in_array($region, $regions, true)) {
                abort(404);
            }
        }

        $hasAccess = $item->userAccess()
            ->where('user_id', $user->id)
            ->whereNotNull('purchased_at')
            ->exists();
        abort_unless($hasAccess, 403);

        $asset = $request->query('asset');
        $asset = is_string($asset) ? $asset : null;

        return app(LibraryMediaStreamService::class)->deliver($item, $asset, $request);
    }
}
