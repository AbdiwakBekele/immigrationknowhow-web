<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ValidatesLibraryItemPricing;
use App\Http\Controllers\Controller;
use App\Models\LibraryAuthor;
use App\Models\LibraryCategory;
use App\Models\LibraryItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
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
            ->with(['category:id,name,slug', 'libraryAuthor:id,name']);

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
            $query->search($request->string('search')->toString());
        }

        $query->orderByDesc('created_at');

        $items = $query->paginate(20)->withQueryString();

        $categories = LibraryCategory::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/Library/Index', [
            'items' => $items,
            'categories' => $categories,
            'authors' => LibraryAuthor::orderBy('name')->get(['id', 'name']),
            'types' => LibraryItem::typeOptionsWithCounts(activeOnly: false),
            'filters' => $request->only(['type', 'category', 'search']),
        ]);
    }

    public function create(): Response
    {
        $categories = LibraryCategory::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/Library/Create', [
            'categories' => $categories,
            'authors' => LibraryAuthor::orderBy('name')->get(['id', 'name']),
            'types' => LibraryItem::typeOptionsWithCounts(activeOnly: false),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(LibraryItem::supportedTypes())],
            'all_regions' => ['boolean'],
            'regions' => ['nullable', 'array', 'exclude_if:all_regions,true'],
            'regions.*' => ['string', Rule::in(LibraryItem::supportedRegions())],
            'category_id' => ['nullable', 'exists:library_categories,id'],
            'author_id' => ['nullable', 'integer', 'exists:library_authors,id'],
            'new_author_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500000'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'publication_year' => ['nullable', 'integer', 'min:1900', 'max:'.(date('Y') + 1)],
            'published_at' => ['nullable', 'date'],
            'isbn' => ['nullable', 'string', 'max:50'],
            'page_count' => ['nullable', 'integer', 'min:1'],
            'language' => ['nullable', 'string', 'max:100'],
            'duration_seconds' => ['nullable', 'integer', 'min:1'],
            'estimated_reading_minutes' => ['nullable', 'integer', 'min:1'],
            'difficulty_level' => ['nullable', 'string', 'max:100'],
            'recommended_age_group' => ['nullable', 'string', 'max:100'],
            'cover_image' => ['nullable', 'image', 'max:2048'],
            'file' => ['nullable', 'required_if:type,audiobook', 'prohibited_if:type,ebook', 'file', 'max:1024000'],
            'pdf_file' => ['nullable', 'required_if:type,ebook', 'prohibited_if:type,audiobook', 'file', 'max:1024000'],
            'audio_file' => ['nullable', 'prohibited_if:type,audiobook', 'file', 'max:1024000'],
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

        if ($validated['type'] === 'ebook') {
            $pdf = $request->file('pdf_file');
            $validated['file_path'] = $pdf->store('library/files', LibraryItem::LIBRARY_MEDIA_DISK);
            $validated['file_name'] = $pdf->getClientOriginalName();
            $validated['file_size'] = $pdf->getSize();
            $validated['file_type'] = strtolower($pdf->getClientOriginalExtension());

            if ($request->hasFile('audio_file')) {
                $audio = $request->file('audio_file');
                $validated['audio_file_path'] = $audio->store('library/files', LibraryItem::LIBRARY_MEDIA_DISK);
                $validated['audio_file_name'] = $audio->getClientOriginalName();
                $validated['audio_file_size'] = $audio->getSize();
                $validated['audio_file_type'] = strtolower($audio->getClientOriginalExtension());
            }
        } else {
            $file = $request->file('file');
            $validated['file_path'] = $file->store('library/files', LibraryItem::LIBRARY_MEDIA_DISK);
            $validated['file_name'] = $file->getClientOriginalName();
            $validated['file_size'] = $file->getSize();
            $validated['file_type'] = strtolower($file->getClientOriginalExtension());
            $validated['audio_file_path'] = null;
            $validated['audio_file_name'] = null;
            $validated['audio_file_size'] = null;
            $validated['audio_file_type'] = null;
        }

        $validated = $this->applyLibraryItemPricing($validated);
        $validated = $this->normalizePublishedDate($validated);
        $validated['is_active'] = (bool) ($validated['is_active'] ?? true);
        $validated['is_featured'] = (bool) ($validated['is_featured'] ?? false);

        $validated = $this->normalizeLibraryRegions($request, $validated);
        $validated['author_id'] = $this->resolveLibraryAuthorId(
            $request->string('new_author_name')->toString(),
            isset($validated['author_id']) ? (int) $validated['author_id'] : null,
        );

        unset(
            $validated['file'],
            $validated['pdf_file'],
            $validated['audio_file'],
            $validated['duration_minutes'],
            $validated['new_author_name'],
            $validated['all_regions'],
        );

        LibraryItem::create($validated);

        return redirect()->route('admin.library.index')
            ->with('success', 'Library item created successfully.');
    }

    public function edit(LibraryItem $library): Response
    {
        $categories = LibraryCategory::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/Library/Edit', [
            'item' => $library->load('libraryAuthor'),
            'categories' => $categories,
            'authors' => LibraryAuthor::orderBy('name')->get(['id', 'name']),
            'types' => LibraryItem::typeOptionsWithCounts(activeOnly: false),
        ]);
    }

    public function update(Request $request, LibraryItem $library): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(LibraryItem::supportedTypes())],
            'all_regions' => ['boolean'],
            'regions' => ['nullable', 'array', 'exclude_if:all_regions,true'],
            'regions.*' => ['string', Rule::in(LibraryItem::supportedRegions())],
            'category_id' => ['nullable', 'exists:library_categories,id'],
            'author_id' => ['nullable', 'integer', 'exists:library_authors,id'],
            'new_author_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500000'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'publication_year' => ['nullable', 'integer', 'min:1900', 'max:'.(date('Y') + 1)],
            'published_at' => ['nullable', 'date'],
            'isbn' => ['nullable', 'string', 'max:50'],
            'page_count' => ['nullable', 'integer', 'min:1'],
            'language' => ['nullable', 'string', 'max:100'],
            'duration_seconds' => ['nullable', 'integer', 'min:1'],
            'estimated_reading_minutes' => ['nullable', 'integer', 'min:1'],
            'difficulty_level' => ['nullable', 'string', 'max:100'],
            'recommended_age_group' => ['nullable', 'string', 'max:100'],
            'cover_image' => ['nullable', 'image', 'max:2048'],
            'file' => ['nullable', 'prohibited_if:type,ebook', 'file', 'max:1024000'],
            'pdf_file' => ['nullable', 'prohibited_if:type,audiobook', 'file', 'max:1024000'],
            'audio_file' => ['nullable', 'prohibited_if:type,audiobook', 'file', 'max:1024000'],
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
        } else {
            unset($validated['cover_image']);
        }

        if ($validated['type'] === 'audiobook' && $library->audio_file_path) {
            $library->deleteStoredAudioFile();
            $validated['audio_file_path'] = null;
            $validated['audio_file_name'] = null;
            $validated['audio_file_size'] = null;
            $validated['audio_file_type'] = null;
        }

        if ($validated['type'] === 'ebook' && $request->hasFile('pdf_file')) {
            if ($library->file_path) {
                $library->deleteStoredLibraryFile();
            }
            $pdf = $request->file('pdf_file');
            $validated['file_path'] = $pdf->store('library/files', LibraryItem::LIBRARY_MEDIA_DISK);
            $validated['file_name'] = $pdf->getClientOriginalName();
            $validated['file_size'] = $pdf->getSize();
            $validated['file_type'] = strtolower($pdf->getClientOriginalExtension());
        } elseif ($validated['type'] === 'audiobook' && $request->hasFile('file')) {
            if ($library->file_path) {
                $library->deleteStoredLibraryFile();
            }
            $file = $request->file('file');
            $validated['file_path'] = $file->store('library/files', LibraryItem::LIBRARY_MEDIA_DISK);
            $validated['file_name'] = $file->getClientOriginalName();
            $validated['file_size'] = $file->getSize();
            $validated['file_type'] = strtolower($file->getClientOriginalExtension());
        }

        if ($validated['type'] === 'ebook' && $request->hasFile('audio_file')) {
            if ($library->audio_file_path) {
                $library->deleteStoredAudioFile();
            }
            $audio = $request->file('audio_file');
            $validated['audio_file_path'] = $audio->store('library/files', LibraryItem::LIBRARY_MEDIA_DISK);
            $validated['audio_file_name'] = $audio->getClientOriginalName();
            $validated['audio_file_size'] = $audio->getSize();
            $validated['audio_file_type'] = strtolower($audio->getClientOriginalExtension());
        }

        unset($validated['file'], $validated['pdf_file'], $validated['audio_file']);

        $validated = $this->applyLibraryItemPricing($validated);
        $validated = $this->normalizePublishedDate($validated);
        $validated['is_active'] = (bool) ($validated['is_active'] ?? true);
        $validated['is_featured'] = (bool) ($validated['is_featured'] ?? false);

        $validated = $this->normalizeLibraryRegions($request, $validated);
        $validated['author_id'] = $this->resolveLibraryAuthorId(
            $request->string('new_author_name')->toString(),
            isset($validated['author_id']) ? (int) $validated['author_id'] : null,
        );

        unset($validated['new_author_name'], $validated['all_regions']);

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
        if ($library->audio_file_path) {
            $library->deleteStoredAudioFile();
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

    private function normalizeLibraryRegions(Request $request, array $validated): array
    {
        if ($request->boolean('all_regions')) {
            $validated['regions'] = null;

            return $validated;
        }

        $regions = $validated['regions'] ?? [];
        if (! is_array($regions) || $regions === []) {
            $validated['regions'] = ['usa'];
        } else {
            $validated['regions'] = array_values(array_unique($regions));
        }

        return $validated;
    }

    private function resolveLibraryAuthorId(string $newAuthorName, ?int $authorId): ?int
    {
        $newAuthorName = trim($newAuthorName);
        if ($newAuthorName !== '') {
            $author = LibraryAuthor::query()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($newAuthorName)])
                ->first();

            if (! $author) {
                $author = LibraryAuthor::create(['name' => $newAuthorName]);
            }

            return $author->id;
        }

        return $authorId;
    }

    private function validateUploadRules(Request $request, string $type, ?LibraryItem $existingItem = null): void
    {
        if ($type === 'ebook') {
            if ($existingItem && $existingItem->type !== 'ebook' && ! $request->hasFile('pdf_file')) {
                throw ValidationException::withMessages([
                    'pdf_file' => 'Upload a PDF file when changing this item to an e-book.',
                ]);
            }

            if ($request->hasFile('pdf_file')) {
                $this->validateEbookPdfUpload($request->file('pdf_file'), $existingItem);
            }

            if ($request->hasFile('audio_file')) {
                $this->validateEbookCompanionAudio($request->file('audio_file'));
            }

            return;
        }

        if (! $request->hasFile('file')) {
            if ($existingItem && $existingItem->type !== $type) {
                throw ValidationException::withMessages([
                    'file' => 'Upload a new audio file when changing this item to an audiobook.',
                ]);
            }

            return;
        }

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        $allowedExtensions = LibraryItem::allowedExtensionsFor($type);

        if (! empty($allowedExtensions) && ! in_array($extension, $allowedExtensions, true)) {
            throw ValidationException::withMessages([
                'file' => 'This file type is not supported for the selected content type.',
            ]);
        }
    }

    private function validateEbookPdfUpload(UploadedFile $file, ?LibraryItem $existingItem): void
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension !== 'pdf') {
            throw ValidationException::withMessages([
                'pdf_file' => 'E-book uploads must be PDF files.',
            ]);
        }

        $ebookCount = LibraryItem::query()
            ->where('type', 'ebook')
            ->when($existingItem, fn ($query) => $query->where('id', '!=', $existingItem->id))
            ->count();

        if ($ebookCount >= self::MAX_EBOOK_PDF_UPLOADS) {
            throw ValidationException::withMessages([
                'pdf_file' => 'Upload limit reached: only 480 PDF e-books are allowed.',
            ]);
        }
    }

    private function validateEbookCompanionAudio(UploadedFile $file): void
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $allowed = LibraryItem::allowedExtensionsFor('audiobook');

        if (! in_array($extension, $allowed, true)) {
            throw ValidationException::withMessages([
                'audio_file' => 'Companion audio must use a supported format (MP3, M4A, AAC, WAV, OGG).',
            ]);
        }
    }

    private function normalizePublishedDate(array $validated): array
    {
        if (! empty($validated['published_at'])) {
            $validated['publication_year'] = Carbon::parse($validated['published_at'])->year;
        }

        return $validated;
    }
}
