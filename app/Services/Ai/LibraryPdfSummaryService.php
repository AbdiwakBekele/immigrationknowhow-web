<?php

namespace App\Services\Ai;

use App\Models\LibraryItem;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;

class LibraryPdfSummaryService
{
    public function summarize(LibraryItem $item, ?callable $onProgress = null): array
    {
        $progress = function (string $status, array $meta = []) use ($onProgress): void {
            if ($onProgress) {
                $onProgress($status, $meta);
            }
        };

        $progress('validating_input');
        Log::info('Library AI summary: started', [
            'library_item_id' => $item->id,
            'title' => $item->title,
            'type' => $item->type,
        ]);

        if ($item->type !== 'ebook') {
            return $this->failed('Item is not an ebook.');
        }

        $disk = $item->resolveLibraryFileDisk();
        if (! $disk || ! $item->file_path) {
            return $this->failed('PDF file path or disk could not be resolved.');
        }

        $maxBytes = (int) config('services.openai.summary_max_pdf_bytes', 5242880);
        try {
            $pdfSize = (int) Storage::disk($disk)->size($item->file_path);
        } catch (\Throwable $e) {
            return $this->failed('Failed to determine PDF size: '.$e->getMessage(), $item);
        }
        $progress('loading_pdf', ['bytes' => $pdfSize]);
        if ($pdfSize > $maxBytes) {
            return $this->failed("PDF too large for summary extraction ({$pdfSize} bytes > {$maxBytes} bytes).", $item);
        }

        $apiKey = trim((string) config('services.openai.api_key', ''));
        if ($apiKey === '') {
            return $this->failed('OPENAI_API_KEY is missing.');
        }

        try {
            $binary = Storage::disk($disk)->get($item->file_path);
            Log::info('Library AI summary: PDF loaded', [
                'library_item_id' => $item->id,
                'disk' => $disk,
                'path' => $item->file_path,
                'bytes' => strlen($binary),
            ]);
        } catch (\Throwable $e) {
            return $this->failed('Failed to read PDF binary: '.$e->getMessage(), $item);
        }

        $progress('extracting_text');
        $sourceText = $this->extractPdfText($binary);
        if ($sourceText === null || $sourceText === '') {
            return $this->failed('PDF text extraction returned empty content.', $item);
        }
        Log::info('Library AI summary: text extracted', [
            'library_item_id' => $item->id,
            'chars' => mb_strlen($sourceText),
        ]);

        return $this->requestSummaryFromOpenAi($item, $sourceText, $apiKey, $progress);
    }

    private function extractPdfText(string $binary): ?string
    {
        try {
            $parser = new Parser();
            $pdf = $parser->parseContent($binary);
            $text = trim(preg_replace('/\s+/', ' ', $pdf->getText()) ?? '');
        } catch (\Throwable $e) {
            Log::warning('Library AI summary: PDF parser failed', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if ($text === '') {
            return null;
        }

        return mb_substr($text, 0, 24000);
    }

    private function requestSummaryFromOpenAi(LibraryItem $item, string $sourceText, string $apiKey, callable $progress): array
    {
        $model = trim((string) config('services.openai.model', 'gpt-4o-mini'));
        $progress('sending_to_ai', ['model' => $model]);
        Log::info('Library AI summary: OpenAI request starting', [
            'library_item_id' => $item->id,
            'model' => $model,
        ]);

        $client = new Client([
            'base_uri' => 'https://api.openai.com',
            'timeout' => 60,
        ]);

        $prompt = implode("\n", [
            'Summarize this PDF ebook for a reader in plain English.',
            'Return only markdown with these sections:',
            '## Quick Summary',
            '## Key Takeaways (3-6 bullets)',
            '## Who This Is For',
            '## Action Steps',
            'Keep it accurate to the source text and avoid making up facts.',
            '',
            'Book title: '.$item->title,
            'Book author: '.($item->author ?? 'Unknown'),
            '',
            'Source text:',
            $sourceText,
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
            return $this->failed('OpenAI request failed: '.$e->getMessage(), $item);
        }

        $payload = json_decode((string) $response->getBody(), true);
        $responseId = (string) data_get($payload, 'id', '');
        $progress('ai_accepted', [
            'response_id' => $responseId !== '' ? $responseId : null,
        ]);
        $summary = $this->extractSummaryTextFromResponsePayload($payload);
        Log::info('Library AI summary: OpenAI response received', [
            'library_item_id' => $item->id,
            'response_id' => $responseId !== '' ? $responseId : null,
            'status' => data_get($payload, 'status'),
            'output_items' => is_array(data_get($payload, 'output')) ? count(data_get($payload, 'output')) : 0,
            'summary_chars' => mb_strlen($summary),
        ]);

        if ($summary === '') {
            $outputItems = data_get($payload, 'output', []);
            $outputShape = [];
            if (is_array($outputItems)) {
                foreach (array_slice($outputItems, 0, 3) as $outputItem) {
                    $outputShape[] = [
                        'type' => is_array($outputItem) ? ($outputItem['type'] ?? null) : null,
                        'content_types' => is_array($outputItem) && is_array($outputItem['content'] ?? null)
                            ? array_values(array_filter(array_map(
                                static fn ($content) => is_array($content) ? ($content['type'] ?? null) : null,
                                $outputItem['content']
                            )))
                            : [],
                    ];
                }
            }

            Log::warning('Library AI summary: empty summary payload shape', [
                'library_item_id' => $item->id,
                'response_id' => $responseId !== '' ? $responseId : null,
                'status' => data_get($payload, 'status'),
                'output_shape' => $outputShape,
            ]);

            return $this->failed('OpenAI returned an empty summary output.', $item);
        }

        return [
            'summary' => mb_substr($summary, 0, 16000),
            'error' => null,
        ];
    }

    private function failed(string $message, ?LibraryItem $item = null): array
    {
        Log::warning('Library AI summary: failed', [
            'library_item_id' => $item?->id,
            'message' => $message,
        ]);

        return [
            'summary' => null,
            'error' => $message,
        ];
    }

    private function extractSummaryTextFromResponsePayload(array $payload): string
    {
        $topLevelOutputText = trim((string) data_get($payload, 'output_text', ''));
        if ($topLevelOutputText !== '') {
            return $topLevelOutputText;
        }

        $parts = [];
        $outputItems = data_get($payload, 'output', []);
        if (is_array($outputItems)) {
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
        }

        return trim(implode("\n\n", $parts));
    }
}

