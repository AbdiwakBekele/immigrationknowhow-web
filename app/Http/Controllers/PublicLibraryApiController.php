<?php

namespace App\Http\Controllers;

use App\Models\LibraryCategory;
use App\Models\LibraryItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

class PublicLibraryApiController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    private function publicItemPayload(LibraryItem $item, bool $fullDescription = false): array
    {
        $description = $item->description ? strip_tags($item->description) : null;
        if ($description !== null && ! $fullDescription) {
            $description = mb_strimwidth($description, 0, 220, '...');
        }

        return [
            'title' => $item->title,
            'slug' => $item->slug,
            'type' => $item->type,
            'description' => $description,
            'author' => $item->author,
            'category' => $item->category ? [
                'name' => $item->category->name,
                'slug' => $item->category->slug,
            ] : null,
            'cover_image_url' => $item->cover_image_url,
            'is_premium' => (bool) $item->is_premium,
            'price' => $item->price !== null ? (float) $item->price : null,
            'currency' => $item->currency,
            'page_count' => $item->page_count,
            'publisher' => $item->publisher,
            'publication_year' => $item->publication_year,
            'published_at' => $item->published_at?->toIso8601String(),
            'isbn' => $item->isbn,
            'language' => $item->language,
            'estimated_reading_minutes' => $item->estimated_reading_minutes,
            'duration_seconds' => $item->duration_seconds,
            'narrator' => $item->narrator,
            'difficulty_level' => $item->difficulty_level,
            'recommended_age_group' => $item->recommended_age_group,
        ];
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $item = LibraryItem::query()
            ->active()
            ->where('slug', $slug)
            ->with(['category:id,name,slug', 'libraryAuthor:id,name'])
            ->first();

        if (! $item) {
            return response()->json(['message' => 'Library item not found.'], 404);
        }

        return response()->json([
            'item' => $this->publicItemPayload($item, fullDescription: true),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $startedAt = microtime(true);
        $requestId = (string) str()->ulid();
        $context = [
            'request_id' => $requestId,
            'path' => $request->path(),
            'method' => $request->method(),
            'full_url' => $request->fullUrl(),
            'scheme' => $request->getScheme(),
            'host' => $request->getHost(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referer' => $request->headers->get('referer'),
            'origin' => $request->headers->get('origin'),
            'x_forwarded_for' => $request->headers->get('x-forwarded-for'),
            'x_forwarded_proto' => $request->headers->get('x-forwarded-proto'),
            'query' => $request->query(),
        ];

        Log::info('PublicLibraryApi request started', $context);

        try {
            $validated = $request->validate([
                'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
                'page' => ['nullable', 'integer', 'min:1'],
                'search' => ['nullable', 'string', 'max:100'],
                'slug' => ['nullable', 'string', 'max:255'],
                'type' => ['nullable', 'string', Rule::in(LibraryItem::supportedTypes())],
                'category' => ['nullable', 'string', 'max:120'],
                'featured' => ['nullable', 'boolean'],
            ]);

            $query = LibraryItem::query()
                ->active()
                ->with(['category:id,name,slug', 'libraryAuthor:id,name'])
                ->select([
                    'id',
                    'title',
                    'slug',
                    'type',
                    'description',
                    'cover_image',
                    'author_id',
                    'category_id',
                    'is_premium',
                    'price',
                    'currency',
                    'created_at',
                ]);

            if (! empty($validated['search'])) {
                $query->search($validated['search']);
            }

            if (! empty($validated['slug'])) {
                $query->where('slug', $validated['slug']);
            }

            if (! empty($validated['type'])) {
                $query->where('type', $validated['type']);
            }

            if (! empty($validated['category'])) {
                $category = LibraryCategory::query()
                    ->active()
                    ->where('slug', $validated['category'])
                    ->first();

                if ($category) {
                    $query->inCategory($category->id);
                }
            }

            if (! empty($validated['featured'])) {
                $query->featured();
            }

            $fullDescription = ! empty($validated['slug']);

            $items = $query
                ->orderByDesc('is_featured')
                ->orderByDesc('created_at')
                ->paginate((int) ($validated['per_page'] ?? 12))
                ->through(fn (LibraryItem $item): array => $this->publicItemPayload($item, $fullDescription));

            Log::info('PublicLibraryApi request completed', array_merge($context, [
                'validated' => $validated,
                'result_count' => count($items->items()),
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'total' => $items->total(),
                'per_page' => $items->perPage(),
                'from' => $items->firstItem(),
                'to' => $items->lastItem(),
                'returned_item_slugs' => collect($items->items())
                    ->pluck('slug')
                    ->filter()
                    ->values()
                    ->take(10)
                    ->all(),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]));

            return response()->json($items);
        } catch (Throwable $exception) {
            Log::error('PublicLibraryApi request failed', array_merge($context, [
                'error_message' => $exception->getMessage(),
                'error_class' => $exception::class,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]));

            throw $exception;
        }
    }
}
