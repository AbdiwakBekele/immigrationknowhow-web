<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Concerns\ValidatesLibraryItemPricing;
use App\Http\Controllers\Controller;
use App\Models\LibraryCategory;
use App\Models\LibraryItem;
use App\Models\ServiceProvider;
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

    public function index(): Response
    {
        $provider = $this->resolveProvider();

        $items = LibraryItem::query()
            ->where('provider_id', $provider->id)
            ->with('category:id,name,slug')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $categories = LibraryCategory::active()->ordered()->get(['id', 'name']);

        return Inertia::render('Provider/Library/Index', [
            'items' => $items,
            'categories' => $categories,
            'types' => LibraryItem::typeOptionsWithCounts(activeOnly: false),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $provider = $this->resolveProvider();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(LibraryItem::supportedTypes())],
            'category_id' => ['nullable', 'exists:library_categories,id'],
            'author' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'file' => ['required', 'file', 'max:102400'],
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
            'is_premium' => ['boolean'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
        ]);

        $this->validateUploadRules($request, $validated['type']);

        $validated['provider_id'] = $provider->id;
        $validated['slug'] = Str::slug($validated['title']);
        $baseSlug = $validated['slug'];
        $counter = 1;
        while (LibraryItem::where('slug', $validated['slug'])->exists()) {
            $validated['slug'] = $baseSlug . '-' . $counter++;
        }

        $file = $request->file('file');
        $validated['file_path'] = $file->store('library/files', LibraryItem::LIBRARY_MEDIA_DISK);
        $validated['file_name'] = $file->getClientOriginalName();
        $validated['file_size'] = $file->getSize();
        $validated['file_type'] = strtolower($file->getClientOriginalExtension());
        $validated = $this->applyLibraryItemPricing($validated);
        $validated['is_active'] = (bool) ($validated['is_active'] ?? true);
        $validated['is_featured'] = (bool) ($validated['is_featured'] ?? false);

        unset($validated['file']);

        LibraryItem::create($validated);

        return redirect()->route('provider.library.index')
            ->with('success', 'Library item uploaded successfully.');
    }

    public function destroy(LibraryItem $library): RedirectResponse
    {
        $provider = $this->resolveProvider();
        abort_unless((int) $library->provider_id === (int) $provider->id, 403);

        if ($library->cover_image) {
            Storage::disk('public')->delete($library->cover_image);
        }
        if ($library->file_path) {
            $library->deleteStoredLibraryFile();
        }

        $library->delete();

        return redirect()->route('provider.library.index')
            ->with('success', 'Library item deleted.');
    }

    private function resolveProvider(): ServiceProvider
    {
        return ServiceProvider::query()
            ->where('user_id', auth()->id())
            ->firstOrFail();
    }

    private function validateUploadRules(Request $request, string $type): void
    {
        if (! $request->hasFile('file')) {
            return;
        }

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        if ($type === 'ebook' && $extension !== 'pdf') {
            throw ValidationException::withMessages([
                'file' => 'E-book uploads must be PDF files.',
            ]);
        }

        if ($type === 'ebook') {
            $ebookCount = LibraryItem::query()
                ->where('provider_id', $this->resolveProvider()->id)
                ->where('type', 'ebook')
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
