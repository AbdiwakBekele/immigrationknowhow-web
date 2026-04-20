<?php

namespace App\Jobs;

use App\Models\LibraryItem;
use App\Services\Ai\LibraryPdfSummaryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GenerateLibraryEbookSummary implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $libraryItemId)
    {
    }

    public function handle(LibraryPdfSummaryService $summaryService): void
    {
        $item = LibraryItem::query()->find($this->libraryItemId);
        if (! $item || $item->type !== 'ebook') {
            return;
        }

        if ($item->ai_summary) {
            Log::info('Admin ebook summary job: skipped (already summarized)', [
                'library_item_id' => $item->id,
            ]);

            return;
        }

        $item->forceFill([
            'ai_summary_status' => 'processing',
            'ai_summary_attempted_at' => now(),
            'ai_summary_last_error' => null,
        ])->save();

        Log::info('Admin ebook summary job: started', [
            'library_item_id' => $item->id,
            'title' => $item->title,
        ]);

        $result = $summaryService->summarize($item, function (string $status, array $meta = []) use ($item): void {
            $item->forceFill([
                'ai_summary_status' => $status,
                'ai_summary_attempted_at' => now(),
            ])->save();

            Log::info('Admin ebook summary job: progress update', [
                'library_item_id' => $item->id,
                'status' => $status,
                'meta' => $meta,
            ]);
        });
        $summary = $result['summary'] ?? null;
        $error = $result['error'] ?? null;

        if (is_string($summary) && trim($summary) !== '') {
            $item->forceFill([
                'ai_summary' => $summary,
                'ai_summary_generated_at' => now(),
                'ai_summary_status' => 'success',
                'ai_summary_last_error' => null,
            ])->save();

            Log::info('Admin ebook summary job: completed', [
                'library_item_id' => $item->id,
                'summary_chars' => mb_strlen($summary),
            ]);

            return;
        }

        $item->forceFill([
            'ai_summary_status' => 'failed',
            'ai_summary_last_error' => is_string($error) && trim($error) !== ''
                ? $error
                : 'Unknown summary generation failure.',
        ])->save();

        Log::warning('Admin ebook summary job: failed', [
            'library_item_id' => $item->id,
            'error' => $item->ai_summary_last_error,
        ]);
    }
}

