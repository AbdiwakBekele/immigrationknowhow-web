<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LibraryCategory;
use App\Models\LibraryItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class LibraryController extends Controller
{
    public function index(Request $request): Response
    {
        $query = LibraryItem::query()
            ->with('category:id,name,slug');

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filter by category
        if ($request->filled('category')) {
            $query->where('library_category_id', $request->category);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('author', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $query->orderByDesc('created_at');

        $items = $query->paginate(20)->withQueryString();

        $categories = LibraryCategory::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/Library/Index', [
            'items' => $items,
            'categories' => $categories,
            'filters' => $request->only(['type', 'category', 'search']),
        ]);
    }

    public function create(): Response
    {
        $categories = LibraryCategory::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/Library/Create', [
            'categories' => $categories,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:ebook,audiobook'],
            'library_category_id' => ['required', 'exists:library_categories,id'],
            'author' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'language' => ['nullable', 'string', 'max:50'],
            'page_count' => ['nullable', 'integer', 'min:1'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'cover_image' => ['nullable', 'image', 'max:2048'],
            'file' => ['required', 'file', 'max:102400'], // 100MB
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
        ]);

        // Generate slug
        $validated['slug'] = Str::slug($validated['title']);
        $baseSlug = $validated['slug'];
        $counter = 1;
        while (LibraryItem::where('slug', $validated['slug'])->exists()) {
            $validated['slug'] = $baseSlug . '-' . $counter++;
        }

        // Upload cover image
        if ($request->hasFile('cover_image')) {
            $validated['cover_image_path'] = $request->file('cover_image')
                ->store('library/covers', 'public');
        }

        // Upload file
        $file = $request->file('file');
        $validated['file_path'] = $file->store('library/files', 's3-private');
        $validated['file_size'] = $file->getSize();
        $validated['file_format'] = $file->getClientOriginalExtension();

        unset($validated['cover_image'], $validated['file']);

        LibraryItem::create($validated);

        return redirect()->route('admin.library.index')
            ->with('success', 'Library item created successfully.');
    }

    public function edit(LibraryItem $library): Response
    {
        $categories = LibraryCategory::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/Library/Edit', [
            'item' => $library,
            'categories' => $categories,
        ]);
    }

    public function update(Request $request, LibraryItem $library): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:ebook,audiobook'],
            'library_category_id' => ['required', 'exists:library_categories,id'],
            'author' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'language' => ['nullable', 'string', 'max:50'],
            'page_count' => ['nullable', 'integer', 'min:1'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'cover_image' => ['nullable', 'image', 'max:2048'],
            'file' => ['nullable', 'file', 'max:102400'],
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
        ]);

        // Update slug if title changed
        if ($validated['title'] !== $library->title) {
            $validated['slug'] = Str::slug($validated['title']);
            $baseSlug = $validated['slug'];
            $counter = 1;
            while (LibraryItem::where('slug', $validated['slug'])->where('id', '!=', $library->id)->exists()) {
                $validated['slug'] = $baseSlug . '-' . $counter++;
            }
        }

        // Upload new cover image
        if ($request->hasFile('cover_image')) {
            // Delete old cover
            if ($library->cover_image_path) {
                Storage::disk('public')->delete($library->cover_image_path);
            }
            $validated['cover_image_path'] = $request->file('cover_image')
                ->store('library/covers', 'public');
        }
        unset($validated['cover_image']);

        // Upload new file
        if ($request->hasFile('file')) {
            // Delete old file
            if ($library->file_path) {
                Storage::disk('s3-private')->delete($library->file_path);
            }
            $file = $request->file('file');
            $validated['file_path'] = $file->store('library/files', 's3-private');
            $validated['file_size'] = $file->getSize();
            $validated['file_format'] = $file->getClientOriginalExtension();
        }
        unset($validated['file']);

        $library->update($validated);

        return back()->with('success', 'Library item updated successfully.');
    }

    public function destroy(LibraryItem $library): RedirectResponse
    {
        // Delete files
        if ($library->cover_image_path) {
            Storage::disk('public')->delete($library->cover_image_path);
        }
        if ($library->file_path) {
            Storage::disk('s3-private')->delete($library->file_path);
        }

        $library->delete();

        return redirect()->route('admin.library.index')
            ->with('success', 'Library item deleted.');
    }

    public function toggleActive(LibraryItem $library): RedirectResponse
    {
        $library->update(['is_active' => !$library->is_active]);

        return back()->with('success', $library->is_active ? 'Item activated.' : 'Item deactivated.');
    }

    public function toggleFeatured(LibraryItem $library): RedirectResponse
    {
        $library->update(['is_featured' => !$library->is_featured]);

        return back()->with('success', $library->is_featured ? 'Item featured.' : 'Item unfeatured.');
    }
}
