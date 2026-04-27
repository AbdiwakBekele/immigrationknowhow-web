<?php

namespace App\Services\Ai;

use App\Models\ServiceProvider;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ServiceSeekerAssistantService
{
    public function ask(User $user, string $question): array
    {
        $apiKey = trim((string) config('services.openai.api_key', ''));
        if ($apiKey === '') {
            return [
                'answer' => null,
                'providers' => [],
                'error' => 'OpenAI is not configured.',
            ];
        }

        $model = trim((string) config('services.openai.model', 'gpt-4o-mini'));
        $prompt = $this->buildPrompt($user, $question);

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
            return [
                'answer' => null,
                'providers' => [],
                'error' => 'AI request failed. Please try again.',
            ];
        }

        $payload = json_decode((string) $response->getBody(), true);
        $rawText = $this->extractAssistantText($payload);
        $json = $this->extractJson($rawText);
        if (! is_array($json)) {
            return [
                'answer' => $rawText !== '' ? $rawText : 'I could not generate a reliable answer. Please rephrase your question.',
                'providers' => [],
                'error' => null,
            ];
        }

        $answer = trim((string) Arr::get($json, 'answer', ''));
        $criteria = Arr::get($json, 'provider_match', []);
        $providers = is_array($criteria) ? $this->matchProviders($criteria, $user) : collect();

        return [
            'answer' => $answer !== '' ? $answer : 'I could not generate a reliable answer. Please rephrase your question.',
            'providers' => $providers->values()->all(),
            'error' => null,
        ];
    }

    private function buildPrompt(User $user, string $question): string
    {
        $country = trim((string) ($user->country ?? ''));
        $state = trim((string) ($user->state ?? ''));
        $city = trim((string) ($user->city ?? ''));
        $language = trim((string) ($user->preferred_language ?? ''));

        return implode("\n", [
            'You are an assistant for immigrants looking for public information and trusted service providers.',
            'Rules:',
            '1) Give general public guidance only; do not claim legal advice.',
            '2) If user asks about benefits/DMV/government process, provide concise practical steps and mention official sites where useful.',
            '3) Also infer provider matching preferences from the request.',
            '4) Return ONLY valid minified JSON with this shape:',
            '{"answer":"...","provider_match":{"location":"...","language":"...","service_type":"...","remote_only":true,"preferred_gender":"..."}}',
            '5) If a field is unknown, use empty string for text fields and false for remote_only.',
            '',
            'User profile context:',
            'country: '.$country,
            'state: '.$state,
            'city: '.$city,
            'preferred_language: '.$language,
            '',
            'User question:',
            $question,
        ]);
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
