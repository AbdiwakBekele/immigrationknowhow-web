<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ValidatesLibraryItemPricing;
use App\Http\Controllers\Controller;
use App\Models\LibraryCategory;
use App\Models\LibraryItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LibraryController extends Controller
{
    use ValidatesLibraryItemPricing;

    private const MAX_EBOOK_PDF_UPLOADS = 480;

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
            $query->where('category_id', $request->category);
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
            'types' => LibraryItem::typeOptionsWithCounts(activeOnly: false),
            'filters' => $request->only(['type', 'category', 'search']),
        ]);
    }

    public function create(): Response
    {
        $categories = LibraryCategory::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/Library/Create', [
            'categories' => $categories,
            'types' => LibraryItem::typeOptionsWithCounts(activeOnly: false),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(LibraryItem::supportedTypes())],
            'category_id' => ['nullable', 'exists:library_categories,id'],
            'author' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'publication_year' => ['nullable', 'integer', 'min:1900', 'max:'.(date('Y') + 1)],
            'isbn' => ['nullable', 'string', 'max:50'],
            'page_count' => ['nullable', 'integer', 'min:1'],
            'duration_seconds' => ['nullable', 'integer', 'min:1'],
            'cover_image' => ['nullable', 'image', 'max:2048'],
            'file' => ['required', 'file', 'max:1024000'], // 1000MB
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
        ]);

        $this->validateUploadRules($request, $validated['type']);

        // Generate slug
        $validated['slug'] = Str::slug($validated['title']);
        $baseSlug = $validated['slug'];
        $counter = 1;
        while (LibraryItem::where('slug', $validated['slug'])->exists()) {
            $validated['slug'] = $baseSlug.'-'.$counter++;
        }

        // Upload cover image
        if ($request->hasFile('cover_image')) {
            $validated['cover_image'] = $request->file('cover_image')
                ->store('library/covers', 'public');
        }

        // Upload file
        $file = $request->file('file');
        $validated['file_path'] = $file->store('library/files', LibraryItem::LIBRARY_MEDIA_DISK);
        $validated['file_name'] = $file->getClientOriginalName();
        $validated['file_size'] = $file->getSize();
        $validated['file_type'] = strtolower($file->getClientOriginalExtension());

        $validated = $this->applyLibraryItemPricing($validated);
        $validated['is_active'] = (bool) ($validated['is_active'] ?? true);
        $validated['is_featured'] = (bool) ($validated['is_featured'] ?? false);

        unset($validated['cover_image'], $validated['file'], $validated['duration_minutes']);

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
            'types' => LibraryItem::typeOptionsWithCounts(activeOnly: false),
        ]);
    }

    public function update(Request $request, LibraryItem $library): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(LibraryItem::supportedTypes())],
            'category_id' => ['nullable', 'exists:library_categories,id'],
            'author' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'publication_year' => ['nullable', 'integer', 'min:1900', 'max:'.(date('Y') + 1)],
            'isbn' => ['nullable', 'string', 'max:50'],
            'page_count' => ['nullable', 'integer', 'min:1'],
            'duration_seconds' => ['nullable', 'integer', 'min:1'],
            'cover_image' => ['nullable', 'image', 'max:2048'],
            'file' => ['nullable', 'file', 'max:1024000'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
        ]);

        $this->validateUploadRules($request, $validated['type'], $library);

        // Update slug if title changed
        if ($validated['title'] !== $library->title) {
            $validated['slug'] = Str::slug($validated['title']);
            $baseSlug = $validated['slug'];
            $counter = 1;
            while (LibraryItem::where('slug', $validated['slug'])->where('id', '!=', $library->id)->exists()) {
                $validated['slug'] = $baseSlug.'-'.$counter++;
            }
        }

        // Upload new cover image
        if ($request->hasFile('cover_image')) {
            // Delete old cover
            if ($library->cover_image) {
                Storage::disk('public')->delete($library->cover_image);
            }
            $validated['cover_image'] = $request->file('cover_image')
                ->store('library/covers', 'public');
        }
        unset($validated['cover_image']);

        // Upload new file
        if ($request->hasFile('file')) {
            // Delete old file
            if ($library->file_path) {
                $library->deleteStoredLibraryFile();
            }
            $file = $request->file('file');
            $validated['file_path'] = $file->store('library/files', LibraryItem::LIBRARY_MEDIA_DISK);
            $validated['file_name'] = $file->getClientOriginalName();
            $validated['file_size'] = $file->getSize();
            $validated['file_type'] = strtolower($file->getClientOriginalExtension());
        }
        unset($validated['file']);

        $validated = $this->applyLibraryItemPricing($validated);
        $validated['is_active'] = (bool) ($validated['is_active'] ?? true);
        $validated['is_featured'] = (bool) ($validated['is_featured'] ?? false);

        $library->update($validated);

        return back()->with('success', 'Library item updated successfully.');
    }

    public function destroy(LibraryItem $library): RedirectResponse
    {
        // Delete files
        if ($library->cover_image) {
            Storage::disk('public')->delete($library->cover_image);
        }
        if ($library->file_path) {
            $library->deleteStoredLibraryFile();
        }

        $library->delete();

        return redirect()->route('admin.library.index')
            ->with('success', 'Library item deleted.');
    }

    public function toggleActive(LibraryItem $library): RedirectResponse
    {
        $library->update(['is_active' => ! $library->is_active]);

        return back()->with('success', $library->is_active ? 'Item activated.' : 'Item deactivated.');
    }

    public function toggleFeatured(LibraryItem $library): RedirectResponse
    {
        $library->update(['is_featured' => ! $library->is_featured]);

        return back()->with('success', $library->is_featured ? 'Item featured.' : 'Item unfeatured.');
    }

    private function validateUploadRules(Request $request, string $type, ?LibraryItem $existingItem = null): void
    {
        if (! $request->hasFile('file')) {
            return;
        }

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        if ($type === 'ebook') {
            if ($extension !== 'pdf') {
                throw ValidationException::withMessages([
                    'file' => 'E-book uploads must be PDF files.',
                ]);
            }

            $ebookCount = LibraryItem::query()
                ->where('type', 'ebook')
                ->when($existingItem, fn ($query) => $query->where('id', '!=', $existingItem->id))
                ->count();

            if ($ebookCount >= self::MAX_EBOOK_PDF_UPLOADS) {
                throw ValidationException::withMessages([
                    'file' => 'Upload limit reached: only 480 PDF e-books are allowed.',
                ]);
            }
        }

        if ($type !== 'ebook') {
            $allowedExtensions = LibraryItem::allowedExtensionsFor($type);

            if (! empty($allowedExtensions) && ! in_array($extension, $allowedExtensions, true)) {
                throw ValidationException::withMessages([
                    'file' => 'This file type is not supported for the selected content type.',
                ]);
            }
        }
    }
}
