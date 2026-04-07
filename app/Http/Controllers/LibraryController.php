<?php

namespace App\Http\Controllers;

use App\Models\LibraryCategory;
use App\Models\LibraryItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LibraryController extends Controller
{
    public function index(Request $request): Response
    {
        $query = LibraryItem::query()
            ->with('category')
            ->active();

        // Apply filters
        if ($request->filled('search')) {
            $query->search($request->input('search'));
        }

        if ($request->filled('category')) {
            $category = LibraryCategory::where('slug', $request->input('category'))->first();
            if ($category) {
                $query->inCategory($category->id);
            }
        }

        if ($request->filled('type')) {
            $type = $request->input('type');
            $query->when($type === 'ebook', fn($q) => $q->ebooks())
                  ->when($type === 'audiobook', fn($q) => $q->audiobooks());
        }

        if ($request->boolean('favorites')) {
            $query->whereHas('userAccess', function ($q) {
                $q->where('user_id', auth()->id())->where('is_favorite', true);
            });
        }

        // Add favorite status for current user
        $items = $query->orderByDesc('is_featured')
            ->orderByDesc('created_at')
            ->paginate(16)
            ->withQueryString();

        // Add favorite status to each item
        $userId = auth()->id();
        $items->getCollection()->transform(function ($item) use ($userId) {
            $access = $item->userAccess()->where('user_id', $userId)->first();
            $item->is_favorite = $access?->is_favorite ?? false;
            return $item;
        });

        // Get counts for metadata
        $items->setCollection($items->getCollection());

        return Inertia::render('Library/Index', [
            'items' => $items,
            'categories' => LibraryCategory::active()->ordered()->get(),
            'filters' => $request->only(['search', 'category', 'type', 'favorites']),
        ]);
    }

    public function ebooks(Request $request): Response
    {
        return $this->index($request->merge(['type' => 'ebook']));
    }

    public function audiobooks(Request $request): Response
    {
        return $this->index($request->merge(['type' => 'audiobook']));
    }

    public function show(LibraryItem $item): Response
    {
        abort_unless($item->is_active, 404);

        $item->load('category');
        $item->incrementViews();
        $item->recordAccess(auth()->user());

        // Get related items
        $relatedItems = LibraryItem::query()
            ->active()
            ->where('id', '!=', $item->id)
            ->where('category_id', $item->category_id)
            ->limit(4)
            ->get();

        // Get user's access info
        $userAccess = $item->userAccess()->where('user_id', auth()->id())->first();

        return Inertia::render('Library/Show', [
            'item' => $item,
            'relatedItems' => $relatedItems,
            'isFavorite' => $userAccess?->is_favorite ?? false,
            'progress' => $userAccess?->progress ?? null,
        ]);
    }

    public function download(LibraryItem $item): StreamedResponse|RedirectResponse
    {
        abort_unless($item->is_active, 404);

        // Check if user has access (e.g., premium content)
        if ($item->is_premium) {
            // Add premium access check here if needed
        }

        // Record the download
        $item->incrementDownloads();
        $item->recordAccess(auth()->user());

        // Return the file download
        if (!Storage::disk('public')->exists($item->file_path)) {
            return back()->with('error', 'File not found.');
        }

        return Storage::disk('public')->download(
            $item->file_path,
            $item->file_name ?? basename($item->file_path)
        );
    }

    public function toggleFavorite(LibraryItem $item): RedirectResponse
    {
        $access = $item->userAccess()->firstOrCreate([
            'user_id' => auth()->id(),
        ]);

        $access->toggleFavorite();

        return back()->with('success', $access->is_favorite ? 'Added to favorites!' : 'Removed from favorites.');
    }

    public function updateProgress(Request $request, LibraryItem $item): RedirectResponse
    {
        $validated = $request->validate([
            'progress' => ['required', 'array'],
            'progress.page' => ['nullable', 'integer', 'min:0'],
            'progress.position' => ['nullable', 'integer', 'min:0'],
            'progress.percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $access = $item->userAccess()->firstOrCreate([
            'user_id' => auth()->id(),
        ]);

        $access->updateProgress($validated['progress']);

        return back();
    }
}
