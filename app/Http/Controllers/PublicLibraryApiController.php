<?php

namespace App\Http\Controllers;

use App\Models\LibraryItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

class PublicLibraryApiController extends Controller
{
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
                'type' => ['nullable', 'string', Rule::in(LibraryItem::supportedTypes())],
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

            if (! empty($validated['type'])) {
                $query->where('type', $validated['type']);
            }

            $items = $query
                ->orderByDesc('is_featured')
                ->orderByDesc('created_at')
                ->paginate((int) ($validated['per_page'] ?? 12))
                ->through(function (LibraryItem $item): array {
                    return [
                        'title' => $item->title,
                        'slug' => $item->slug,
                        'type' => $item->type,
                        'description' => $item->description ? mb_strimwidth(strip_tags($item->description), 0, 220, '...') : null,
                        'author' => $item->author,
                        'category' => $item->category ? [
                            'name' => $item->category->name,
                            'slug' => $item->category->slug,
                        ] : null,
                        'cover_image_url' => $item->cover_image_url,
                        'is_premium' => (bool) $item->is_premium,
                        'price' => $item->price !== null ? (float) $item->price : null,
                        'currency' => $item->currency,
                    ];
                });

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
