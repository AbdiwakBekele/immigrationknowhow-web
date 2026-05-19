<?php

namespace App\Services\Ai;

use App\Models\LibraryItem;
use App\Models\ServiceProvider;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ServiceSeekerAssistantService
{
    /**
     * @param  'seeker'|'provider'  $audience
     */
    public function ask(User $user, string $question, string $audience = 'seeker'): array
    {
        $apiKey = trim((string) config('services.openai.api_key', ''));
        if ($apiKey === '') {
            return [
                'answer' => null,
                'providers' => [],
                'books' => [],
                'error' => 'OpenAI is not configured.',
            ];
        }

        $model = trim((string) config('services.openai.model', 'gpt-4o-mini'));
        $candidates = $this->collectLibraryCandidates($user, $question);
        $prompt = $this->buildPrompt(
            $user,
            $question,
            $this->formatLibraryCatalogForPrompt($candidates),
            $audience === 'provider' ? 'provider' : 'seeker'
        );

        $client = new Client([
            'base_uri' => 'https://api.openai.com',
            'timeout' => 60,
        ]);

        try {
            $response = $client->post('/v1/responses', [
                'headers' => [
                    'Authorization' => 'Bearer '.$apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $model,
                    'input' => $prompt,
                    'max_output_tokens' => 900,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('openai.ai_assistant.request_failed', [
                'user_id' => $user->id,
                'audience' => $audience,
                'model' => $model,
                'error' => $e->getMessage(),
            ]);

            return [
                'answer' => null,
                'providers' => [],
                'books' => [],
                'error' => config('app.debug')
                    ? 'AI request failed: '.$e->getMessage()
                    : 'AI request failed. Please try again.',
            ];
        }

        $payload = json_decode((string) $response->getBody(), true);
        $rawText = $this->extractAssistantText($payload);
        $json = $this->extractJson($rawText);
        if (! is_array($json)) {
            return [
                'answer' => $rawText !== '' ? $rawText : 'I could not generate a reliable answer. Please rephrase your question.',
                'providers' => [],
                'books' => [],
                'error' => null,
            ];
        }

        $answer = trim((string) Arr::get($json, 'answer', ''));
        $criteria = Arr::get($json, 'provider_match', []);
        $providers = is_array($criteria) ? $this->matchProviders($criteria, $user) : collect();
        $books = $this->resolveRecommendedBooks($candidates, Arr::get($json, 'library_recommendations', []));

        return [
            'answer' => $answer !== '' ? $answer : 'I could not generate a reliable answer. Please rephrase your question.',
            'providers' => $providers->values()->all(),
            'books' => $books,
            'error' => null,
        ];
    }

    /**
     * @param  'seeker'|'provider'  $audience
     */
    private function buildPrompt(User $user, string $question, string $libraryCatalog, string $audience = 'seeker'): string
    {
        $country = trim((string) ($user->country ?? ''));
        $state = trim((string) ($user->state ?? ''));
        $city = trim((string) ($user->city ?? ''));
        $language = trim((string) ($user->preferred_language ?? ''));

        if ($audience === 'provider') {
            return implode("\n", [
                'You help immigration and settlement service providers who use this platform.',
                'They may ask about public programs (benefits, DMV, USCIS-process style topics) to better support clients, business operations, peer providers on the marketplace, or library titles to recommend to clients.',
                'Rules:',
                '1) Give general public information only; do not claim legal advice.',
                '2) For benefits/DMV/government topics, give concise practical steps and official sources where useful.',
                '3) Infer marketplace provider_match from the question when they want peer or specialist referrals.',
                '4) When library catalog titles fit the topic (including client-education angles), mention 1–3 by title. If none fit, set library_recommendations to [].',
                '5) library_recommendations: at most 3 objects, slugs exactly from catalog only. Each: {"slug":"...","recommended_for":"short sentence (max ~140 chars) tying the book to their question or clients."}',
                '6) Return ONLY valid minified JSON:',
                '{"answer":"...","provider_match":{"location":"...","language":"...","service_type":"...","remote_only":true,"preferred_gender":"..."},"library_recommendations":[{"slug":"slug-one","recommended_for":"..."}]}',
                '7) Unknown provider_match text fields: empty string; remote_only false if unknown.',
                '',
                'Provider user profile:',
                'country: '.$country,
                'state: '.$state,
                'city: '.$city,
                'preferred_language: '.$language,
                '',
                'Library catalog (subject-matched for this question):',
                $libraryCatalog,
                '',
                'Provider question:',
                $question,
            ]);
        }

        return implode("\n", [
            'You are an assistant for immigrants looking for public information, trusted service providers, and relevant books from this platform\'s digital library.',
            'Rules:',
            '1) Give general public guidance only; do not claim legal advice.',
            '2) If user asks about benefits/DMV/government process, provide concise practical steps and mention official sites where useful.',
            '3) Also infer provider matching preferences from the request.',
            '4) When books from the catalog clearly match the user\'s topic, mention 1–3 by title in your answer. If none fit, set library_recommendations to [].',
            '5) library_recommendations: at most 3 objects only for slugs in the catalog. Each object: {"slug":"exact-slug-from-catalog","recommended_for":"one short sentence (max ~140 chars) why this fits the user question"}.',
            '6) Return ONLY valid minified JSON with this shape:',
            '{"answer":"...","provider_match":{"location":"...","language":"...","service_type":"...","remote_only":true,"preferred_gender":"..."},"library_recommendations":[{"slug":"slug-one","recommended_for":"..."}]}',
            '7) If a provider_match field is unknown, use empty string for text fields and false for remote_only.',
            '',
            'User profile context:',
            'country: '.$country,
            'state: '.$state,
            'city: '.$city,
            'preferred_language: '.$language,
            '',
            'Library catalog (site inventory; subject-matched for this question — recommend only from this list):',
            $libraryCatalog,
            '',
            'User question:',
            $question,
        ]);
    }

    /**
     * @return Collection<int, LibraryItem>
     */
    private function collectLibraryCandidates(User $user, string $question, int $catalogLimit = 15): Collection
    {
        $region = LibraryItem::regionForCountry($user->country ?? null);
        $tokens = $this->questionTokens($question);

        $base = LibraryItem::query()
            ->active()
            ->with(['category:id,name,slug', 'libraryAuthor:id,name'])
            ->when($region !== null && $region !== '', fn ($q) => $q->availableInRegion($region));

        if ($tokens->isEmpty()) {
            return $base->clone()
                ->orderByDesc('is_featured')
                ->orderByDesc('view_count')
                ->limit($catalogLimit)
                ->get();
        }

        $items = $base->clone()
            ->where(function ($q) use ($tokens) {
                foreach ($tokens as $t) {
                    $like = '%'.addcslashes($t, '%_\\').'%';
                    $q->orWhere(function ($q2) use ($like, $t) {
                        $q2->where('title', 'like', $like)
                            ->orWhere('description', 'like', $like)
                            ->orWhereHas('category', function ($cq) use ($like) {
                                $cq->where('name', 'like', $like)
                                    ->orWhere('slug', 'like', $like);
                            })
                            ->orWhereJsonContains('tags', $t);
                    });
                }
            })
            ->limit(120)
            ->get();

        if ($items->isEmpty()) {
            return $base->clone()
                ->orderByDesc('is_featured')
                ->orderByDesc('view_count')
                ->limit($catalogLimit)
                ->get();
        }

        return $this->rankLibraryItemsByTokens($items, $tokens)->take($catalogLimit)->values();
    }

    /**
     * @return Collection<int, string>
     */
    private function questionTokens(string $question): Collection
    {
        $normalized = Str::lower(preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $question));
        $stop = [
            'the', 'and', 'for', 'are', 'but', 'not', 'you', 'all', 'can', 'her', 'was', 'one', 'our', 'out', 'day',
            'get', 'has', 'him', 'his', 'how', 'its', 'may', 'new', 'now', 'old', 'see', 'two', 'way', 'who', 'boy',
            'did', 'let', 'put', 'say', 'she', 'too', 'use', 'that', 'this', 'with', 'have', 'from', 'they', 'know',
            'want', 'been', 'good', 'much', 'some', 'time', 'very', 'when', 'come', 'here', 'just', 'like', 'long',
            'make', 'many', 'over', 'such', 'take', 'than', 'them', 'well', 'were', 'what', 'will', 'your', 'about',
            'after', 'again', 'could', 'each', 'find', 'first', 'give', 'into', 'look', 'made', 'more', 'most', 'only',
            'other', 'should', 'these', 'think', 'where', 'being', 'those', 'while', 'would', 'their', 'there', 'which',
            'going', 'really', 'something', 'anything', 'please', 'help', 'need', 'tell', 'best', 'also', 'any', 'didn',
        ];

        return collect(preg_split('/\s+/', $normalized, -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn (string $w) => trim($w))
            ->filter(fn (string $w) => strlen($w) >= 3 && ! in_array($w, $stop, true))
            ->unique()
            ->values();
    }

    /**
     * @param  Collection<int, LibraryItem>  $items
     * @param  Collection<int, string>  $tokens
     * @return Collection<int, LibraryItem>
     */
    private function rankLibraryItemsByTokens(Collection $items, Collection $tokens): Collection
    {
        return $items
            ->map(function (LibraryItem $item) use ($tokens) {
                $score = 0;
                $title = Str::lower((string) ($item->title ?? ''));
                $desc = Str::lower(strip_tags((string) ($item->description ?? '')));
                $catName = Str::lower((string) ($item->category?->name ?? ''));
                $tags = collect($item->tags ?? [])->map(fn ($tag) => Str::lower((string) $tag));
                foreach ($tokens as $t) {
                    if (str_contains($title, $t)) {
                        $score += 4;
                    }
                    if (str_contains($catName, $t)) {
                        $score += 5;
                    }
                    if ($tags->contains(fn (string $tag) => $tag === $t || str_contains($tag, $t))) {
                        $score += 3;
                    }
                    if (str_contains($desc, $t)) {
                        $score += 1;
                    }
                }
                if ($item->is_featured) {
                    $score += 2;
                }

                return ['item' => $item, 'score' => $score];
            })
            ->sortByDesc(fn (array $row) => $row['score'])
            ->pluck('item')
            ->values();
    }

    /**
     * @param  Collection<int, LibraryItem>  $candidates
     */
    private function formatLibraryCatalogForPrompt(Collection $candidates): string
    {
        if ($candidates->isEmpty()) {
            return '(No library items available for this region.)';
        }

        $lines = $candidates->map(function (LibraryItem $item) {
            $category = $item->category?->name ?? 'Uncategorized';
            $author = $item->libraryAuthor?->name ?? $item->author ?? '';
            $tags = '';
            if (is_array($item->tags) && $item->tags !== []) {
                $tags = implode(', ', array_map(static fn ($t) => (string) $t, array_slice($item->tags, 0, 8)));
            }
            $type = LibraryItem::typeLabel((string) $item->type);
            $summary = Str::limit(trim(strip_tags((string) ($item->description ?? ''))), 160);

            return sprintf(
                '- slug: %s | title: %s | type: %s | category: %s | author: %s | tags: %s | summary: %s',
                $item->slug,
                $item->title,
                $type,
                $category,
                $author !== '' ? $author : '—',
                $tags !== '' ? $tags : '—',
                $summary !== '' ? $summary : '—'
            );
        });

        return $lines->implode("\n");
    }

    /**
     * @param  Collection<int, LibraryItem>  $candidates
     * @param  mixed  $rawSlugs
     * @return array<int, array<string, mixed>>
     */
    private function resolveRecommendedBooks(Collection $candidates, mixed $rawSlugs): array
    {
        $allowed = $candidates->keyBy(fn (LibraryItem $i) => $i->slug);
        if (! is_array($rawSlugs)) {
            return [];
        }

        $ordered = [];
        $seen = [];
        foreach ($rawSlugs as $entry) {
            $slug = '';
            $recommendedFor = '';
            if (is_string($entry)) {
                $slug = trim($entry);
            } elseif (is_array($entry)) {
                $slug = trim((string) Arr::get($entry, 'slug', ''));
                $recommendedFor = trim((string) Arr::get($entry, 'recommended_for',
                    Arr::get($entry, 'why', '')));
            }
            if ($slug === '' || isset($seen[$slug]) || ! isset($allowed[$slug])) {
                continue;
            }
            $seen[$slug] = true;
            $ordered[] = ['slug' => $slug, 'recommended_for' => Str::limit($recommendedFor, 220)];
            if (count($ordered) >= 3) {
                break;
            }
        }

        $out = [];
        foreach ($ordered as $row) {
            $out[] = $this->mapLibraryItemForAssistantResponse(
                $allowed[$row['slug']],
                $row['recommended_for']
            );
        }

        return $out;
    }

    private function fallbackRecommendedBlurb(LibraryItem $item): string
    {
        $ai = trim(strip_tags((string) ($item->ai_summary ?? '')));
        if ($ai !== '') {
            return Str::limit($ai, 160);
        }
        $desc = trim(strip_tags((string) ($item->description ?? '')));

        $category = trim((string) ($item->category?->name ?? ''));

        return $desc !== '' ? Str::limit($desc, 160)
            : ($category !== ''
                ? 'A library title in '.$category.'—open the listing for more detail.'
                : 'A curated pick from our library related to your topic.');
    }

    /**
     * @return array<string, mixed>
     */
    private function mapLibraryItemForAssistantResponse(LibraryItem $item, string $recommendedFor = ''): array
    {
        $recommendedFor = trim($recommendedFor);
        if ($recommendedFor === '') {
            $recommendedFor = $this->fallbackRecommendedBlurb($item);
        }

        return [
            'id' => $item->id,
            'slug' => $item->slug,
            'title' => $item->title,
            'type' => $item->type,
            'type_label' => LibraryItem::typeLabel((string) $item->type),
            'author' => $item->libraryAuthor?->name ?? $item->author,
            'category' => $item->category?->name,
            'cover_image_url' => $item->cover_image_url,
            'recommended_for' => $recommendedFor,
        ];
    }

    private function extractJson(string $rawText): ?array
    {
        $rawText = trim($rawText);
        if ($rawText === '') {
            return null;
        }

        try {
            $decoded = json_decode($rawText, true, 512, JSON_THROW_ON_ERROR);
            if (is_array($decoded)) {
                return $decoded;
            }
        } catch (\Throwable) {
            // Continue to fenced-block extraction.
        }

        if (preg_match('/\{.*\}/s', $rawText, $matches) !== 1) {
            return null;
        }

        try {
            $decoded = json_decode((string) $matches[0], true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function extractAssistantText(array $payload): string
    {
        $topLevelOutputText = trim((string) data_get($payload, 'output_text', ''));
        if ($topLevelOutputText !== '') {
            return $topLevelOutputText;
        }

        $parts = [];
        $outputItems = data_get($payload, 'output', []);
        if (! is_array($outputItems)) {
            return '';
        }

        foreach ($outputItems as $item) {
            if (! is_array($item)) {
                continue;
            }

            $contentItems = $item['content'] ?? [];
            if (! is_array($contentItems)) {
                continue;
            }

            foreach ($contentItems as $content) {
                if (! is_array($content)) {
                    continue;
                }

                $text = trim((string) ($content['text'] ?? ''));
                if ($text !== '') {
                    $parts[] = $text;
                    continue;
                }

                $nestedValue = trim((string) data_get($content, 'text.value', ''));
                if ($nestedValue !== '') {
                    $parts[] = $nestedValue;
                }
            }
        }

        return trim(implode("\n\n", $parts));
    }

    private function matchProviders(array $criteria, User $viewer)
    {
        $query = ServiceProvider::query()
            ->with(['user:id,first_name,last_name,city,state,country'])
            ->active()
            ->acceptingClients()
            ->whereUserCountry($viewer->country);

        $serviceType = trim((string) Arr::get($criteria, 'service_type', ''));
        if ($serviceType !== '') {
            $query->where(function ($q) use ($serviceType) {
                $q->whereJsonContains('service_types', Str::lower($serviceType))
                    ->orWhereJsonContains('service_types', $serviceType);
            });
        }

        $language = trim((string) Arr::get($criteria, 'language', ''));
        if ($language !== '') {
            $query->where(function ($q) use ($language) {
                $q->whereJsonContains('languages_offered', Str::lower($language))
                    ->orWhereJsonContains('languages_offered', $language);
            });
        }

        $location = trim((string) Arr::get($criteria, 'location', ''));
        if ($location !== '') {
            $query->where(function ($q) use ($location) {
                $q->where('serves_remote', true)
                    ->orWhereHas('user', function ($uq) use ($location) {
                        $uq->where('city', 'like', "%{$location}%")
                            ->orWhere('state', 'like', "%{$location}%");
                    });
            });
        }

        if ((bool) Arr::get($criteria, 'remote_only', false)) {
            $query->where('serves_remote', true);
        }

        return $query
            ->orderByDesc('is_featured')
            ->orderByDesc('average_rating')
            ->limit(6)
            ->get()
            ->map(fn (ServiceProvider $provider) => [
                'id' => $provider->id,
                'slug' => $provider->slug,
                'business_name' => $provider->business_name,
                'location' => trim(($provider->user?->city ?? '').', '.($provider->user?->state ?? ''), ', '),
                'languages_offered' => is_array($provider->languages_offered) ? $provider->languages_offered : [],
                'average_rating' => $provider->average_rating,
                'total_reviews' => $provider->total_reviews,
            ]);
    }
}
