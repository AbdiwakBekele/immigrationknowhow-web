<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Http\Controllers\LibraryController as SiteLibraryController;
use App\Models\LibraryItem;
use App\Models\LibraryUserAccess;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LibraryController extends Controller
{
    public function cart(SiteLibraryController $library): Response
    {
        return Inertia::render('Provider/Library/Cart', $library->cartPayload());
    }

    public function index(Request $request): Response
    {
        $user = $request->user();

        $purchased = LibraryItem::query()
            ->active()
            ->whereHas('userAccess', function ($q) use ($user) {
                $q->where('user_id', $user->id)->whereNotNull('purchased_at');
            })
            ->with([
                'libraryAuthor',
                'category',
                'userAccess' => function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                },
            ])
            ->orderByDesc(
                LibraryUserAccess::query()
                    ->select('purchased_at')
                    ->whereColumn('library_item_id', 'library_items.id')
                    ->where('user_id', $user->id)
                    ->limit(1)
            )
            ->paginate(12, ['*'], 'purchased_page')
            ->withQueryString();

        $available = LibraryItem::query()
            ->active()
            ->where(function ($q) use ($user) {
                $q->whereDoesntHave('userAccess', function ($access) use ($user) {
                    $access->where('user_id', $user->id)->whereNotNull('purchased_at');
                });
            })
            ->with(['libraryAuthor', 'category'])
            ->orderByDesc('is_featured')
            ->orderByDesc('created_at')
            ->paginate(12, ['*'], 'available_page')
            ->withQueryString();

        return Inertia::render('Provider/Library/Index', [
            'purchasedItems' => $purchased->toArray(),
            'availableItems' => $available->toArray(),
        ]);
    }
}
