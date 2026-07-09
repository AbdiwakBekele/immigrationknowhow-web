<?php

namespace App\Actions\Library;

use App\Jobs\GenerateLibraryEbookSummary;
use App\Models\LibraryItem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class QueueLibraryEbookSummary
{
    /** @var list<string> */
    public const IN_PROGRESS_STATUSES = [
        'queued',
        'processing',
        'validating_input',
        'loading_pdf',
        'extracting_text',
        'sending_to_ai',
        'ai_accepted',
    ];

    /**
     * Queue one-time AI summary generation when an ebook has no summary yet.
     *
     * @return 'dispatched'|'already_exists'|'in_progress'|'skipped'|'misconfigured'|'dispatch_failed'
     */
    public function __invoke(LibraryItem $item, bool $syncOnDatabaseQueue = false): string
    {
        if ($item->type !== 'ebook') {
            return 'skipped';
        }

        if (
            ! Schema::hasColumn('library_items', 'ai_summary_status')
            || ! Schema::hasColumn('library_items', 'ai_summary_attempted_at')
            || ! Schema::hasColumn('library_items', 'ai_summary_last_error')
        ) {
            Log::warning('Library ebook summary: required columns missing, skipping queue dispatch');

            return 'skipped';
        }

        $fresh = $item->fresh();
        if (! $fresh) {
            return 'skipped';
        }

        if (is_string($fresh->ai_summary) && trim($fresh->ai_summary) !== '') {
            return 'already_exists';
        }

        if (in_array((string) $fresh->ai_summary_status, self::IN_PROGRESS_STATUSES, true)) {
            return 'in_progress';
        }

        $queueDriver = (string) config('queue.default', '');
        $openAiConfigured = trim((string) config('services.openai.api_key', '')) !== '';

        if (! $openAiConfigured) {
            $fresh->forceFill([
                'ai_summary_status' => 'failed',
                'ai_summary_attempted_at' => now(),
                'ai_summary_last_error' => 'OPENAI_API_KEY is missing. Add it to .env and run php artisan config:clear.',
            ])->save();

            Log::warning('Library ebook summary: skipped (OpenAI not configured)', [
                'library_item_id' => $fresh->id,
                'queue_driver' => $queueDriver,
            ]);

            return 'misconfigured';
        }

        $claimed = LibraryItem::query()
            ->whereKey($fresh->id)
            ->where('type', 'ebook')
            ->where(function ($query): void {
                $query->whereNull('ai_summary')
                    ->orWhere('ai_summary', '');
            })
            ->where(function ($query): void {
                $query->whereNull('ai_summary_status')
                    ->orWhere('ai_summary_status', 'failed');
            })
            ->update([
                'ai_summary_status' => 'queued',
                'ai_summary_attempted_at' => now(),
                'ai_summary_last_error' => null,
            ]);

        if ($claimed === 0) {
            $latest = $fresh->fresh();
            if ($latest && is_string($latest->ai_summary) && trim($latest->ai_summary) !== '') {
                return 'already_exists';
            }

            if ($latest && in_array((string) $latest->ai_summary_status, self::IN_PROGRESS_STATUSES, true)) {
                return 'in_progress';
            }

            return 'skipped';
        }

        try {
            if ($syncOnDatabaseQueue && $queueDriver === 'database') {
                Log::info('Library ebook summary: running synchronously (database queue)', [
                    'library_item_id' => $fresh->id,
                ]);
                GenerateLibraryEbookSummary::dispatchSync($fresh->id);
            } else {
                GenerateLibraryEbookSummary::dispatch($fresh->id);
            }

            Log::info('Library ebook summary: queued', [
                'library_item_id' => $fresh->id,
                'title' => $fresh->title,
                'sync' => $syncOnDatabaseQueue && $queueDriver === 'database',
            ]);

            return 'dispatched';
        } catch (Throwable $e) {
            LibraryItem::query()->whereKey($fresh->id)->update([
                'ai_summary_status' => 'failed',
                'ai_summary_last_error' => 'Dispatch failed: '.$e->getMessage(),
            ]);

            Log::error('Library ebook summary: dispatch failed', [
                'library_item_id' => $fresh->id,
                'queue_driver' => $queueDriver,
                'error' => $e->getMessage(),
                'class' => $e::class,
            ]);

            return 'dispatch_failed';
        }
    }
}
