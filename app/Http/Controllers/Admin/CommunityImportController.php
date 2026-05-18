<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CommunityImportUploadRequest;
use App\Models\CommunityImportBatch;
use App\Models\CommunityImportError;
use App\Models\User;
use App\Services\CommunityImport\CommunityImportService;
use App\Services\CommunityImport\CommunityImportValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class CommunityImportController extends Controller
{
    public function index(Request $request): Response
    {
        $batches = CommunityImportBatch::query()
            ->with('uploadedBy:id,first_name,last_name,email')
            ->latest()
            ->paginate(10)
            ->through(fn (CommunityImportBatch $batch) => $this->toBatchResource($batch));

        return Inertia::render('Admin/Community/ImportIndex', [
            'batches' => $batches,
            'headerSamples' => CommunityImportValidator::headerSamples(),
            'ownerOptions' => $this->ownerOptions(),
            'defaultOwnerId' => $request->user()?->id,
        ]);
    }

    public function store(CommunityImportUploadRequest $request, CommunityImportService $communityImportService): RedirectResponse
    {
        $batch = $communityImportService->createBatchFromUpload($request->user(), $request->validated());

        $flashType = $batch->status === CommunityImportBatch::STATUS_READY ? 'success' : 'warning';
        $flashMessage = $batch->status === CommunityImportBatch::STATUS_READY
            ? 'CSV files uploaded and validated. Review the preview, then run the import.'
            : 'CSV files uploaded, but the preview contains blocking issues that must be fixed before import.';

        return $this->redirectWithFlash(
            redirect()->route('admin.community.import.preview', $batch->id),
            $flashType,
            $flashMessage,
            [
                'action' => 'community.import.upload',
                'batch_id' => $batch->id,
                'batch_status' => $batch->status,
            ],
        );
    }

    public function preview(CommunityImportBatch $communityImportBatch, CommunityImportService $communityImportService): Response
    {
        $batch = $communityImportService->validateBatch($communityImportBatch->fresh());

        return $this->renderBatchPage($batch, true);
    }

    public function show(CommunityImportBatch $communityImportBatch): Response
    {
        return $this->renderBatchPage($communityImportBatch, false);
    }

    public function run(CommunityImportBatch $communityImportBatch, CommunityImportService $communityImportService): RedirectResponse
    {
        $batch = $communityImportService->run($communityImportBatch);

        return $this->redirectAfterImportRun($batch);
    }

    private function redirectAfterImportRun(CommunityImportBatch $batch): RedirectResponse
    {
        $redirect = redirect()->route('admin.community.import.show', $batch->id);

        if ($batch->status === CommunityImportBatch::STATUS_FAILED) {
            return $this->redirectWithFlash(
                $redirect,
                'error',
                'Import failed before finishing. Review the batch details below.',
                $this->importRunFlashContext($batch),
            );
        }

        $imported = [
            'users' => (int) $batch->imported_users,
            'posts' => (int) $batch->imported_posts,
            'comments' => (int) $batch->imported_comments,
            'reactions' => (int) $batch->imported_reactions,
        ];

        $skipped = [
            'users' => (int) $batch->skipped_users,
            'posts' => (int) $batch->skipped_posts,
            'comments' => (int) $batch->skipped_comments,
            'reactions' => (int) $batch->skipped_reactions,
        ];

        $rowIssues = (int) $batch->errors_count;
        $message = sprintf(
            'Community import completed. Imported %d users, %d posts, %d comments, and %d reactions.',
            $imported['users'],
            $imported['posts'],
            $imported['comments'],
            $imported['reactions'],
        );

        if (array_sum($skipped) > 0 || $rowIssues > 0) {
            $message .= sprintf(
                ' Skipped rows: users %d, posts %d, comments %d, reactions %d. Row-level issues recorded: %d (see batch details).',
                $skipped['users'],
                $skipped['posts'],
                $skipped['comments'],
                $skipped['reactions'],
                $rowIssues,
            );
        }

        return $this->redirectWithFlash(
            $redirect,
            'success',
            $message,
            array_merge($this->importRunFlashContext($batch), [
                'imported' => $imported,
                'skipped' => $skipped,
                'row_issues' => $rowIssues,
            ]),
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function redirectWithFlash(RedirectResponse $redirect, string $type, string $message, array $context = []): RedirectResponse
    {
        $request = request();
        $previousFlash = [
            'success' => $request->session()->get('success'),
            'error' => $request->session()->get('error'),
            'warning' => $request->session()->get('warning'),
            'info' => $request->session()->get('info'),
        ];

        $activePrevious = array_filter($previousFlash, fn ($value) => filled($value));

        if (count($activePrevious) > 0) {
            Log::info('community.import.flash.previous_cleared', [
                'flash_type' => $type,
                'previous_flash' => $activePrevious,
                ...$context,
            ]);
        }

        $request->session()->forget(['success', 'error', 'warning', 'info']);

        Log::info('community.import.flash.set', [
            'flash_type' => $type,
            'message' => $message,
            'previous_flash' => $activePrevious,
            ...$context,
        ]);

        return $redirect->with($type, $message);
    }

    /**
     * @return array<string, mixed>
     */
    private function importRunFlashContext(CommunityImportBatch $batch): array
    {
        return [
            'action' => 'community.import.run',
            'batch_id' => $batch->id,
            'batch_status' => $batch->status,
        ];
    }

    private function renderBatchPage(CommunityImportBatch $batch, bool $isPreview): Response
    {
        $batch->load('uploadedBy:id,first_name,last_name,email');

        $errors = $batch->errors()
            ->latest()
            ->paginate(20)
            ->through(fn (CommunityImportError $error) => [
                'id' => $error->id,
                'file_type' => $error->file_type,
                'row_number' => $error->row_number,
                'old_wp_id' => $error->old_wp_id,
                'severity' => $error->severity,
                'message' => $error->message,
                'row_data' => $error->row_data,
                'created_at' => optional($error->created_at)->toIso8601String(),
            ]);

        return Inertia::render('Admin/Community/ImportShow', [
            'batch' => array_merge($this->toBatchResource($batch), [
                'summary' => $batch->summary ?? [],
                'files' => [
                    'users' => basename((string) $batch->users_file_path),
                    'posts' => basename((string) $batch->posts_file_path),
                    'comments' => basename((string) $batch->comments_file_path),
                    'reactions' => basename((string) $batch->reactions_file_path),
                ],
            ]),
            'importIssues' => $errors,
            'isPreview' => $isPreview,
            'canImport' => ($batch->summary['can_import'] ?? false) === true
                && $batch->status === CommunityImportBatch::STATUS_READY,
            'isImporting' => $batch->status === CommunityImportBatch::STATUS_IMPORTING,
            'headerSamples' => CommunityImportValidator::headerSamples(),
        ]);
    }

    private function ownerOptions(): array
    {
        return User::query()
            ->role(['admin', 'super_admin'])
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'email'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
            ])
            ->values()
            ->all();
    }

    private function toBatchResource(CommunityImportBatch $batch): array
    {
        $summary = $batch->summary ?? [];

        return [
            'id' => $batch->id,
            'status' => $batch->status,
            'import_type' => $batch->import_type,
            'uploaded_by' => $batch->uploadedBy ? [
                'id' => $batch->uploadedBy->id,
                'name' => $batch->uploadedBy->full_name,
                'email' => $batch->uploadedBy->email,
            ] : null,
            'selected_owner_name' => $summary['selected_owner_name'] ?? null,
            'totals' => [
                'users' => (int) $batch->total_users,
                'posts' => (int) $batch->total_posts,
                'comments' => (int) $batch->total_comments,
                'reactions' => (int) $batch->total_reactions,
            ],
            'imported' => [
                'users' => (int) $batch->imported_users,
                'posts' => (int) $batch->imported_posts,
                'comments' => (int) $batch->imported_comments,
                'reactions' => (int) $batch->imported_reactions,
            ],
            'skipped' => [
                'users' => (int) $batch->skipped_users,
                'posts' => (int) $batch->skipped_posts,
                'comments' => (int) $batch->skipped_comments,
                'reactions' => (int) $batch->skipped_reactions,
            ],
            'errors_count' => (int) $batch->errors_count,
            'warnings_count' => (int) ($summary['warnings_count'] ?? 0),
            'created_at' => optional($batch->created_at)->toIso8601String(),
            'started_at' => optional($batch->started_at)->toIso8601String(),
            'finished_at' => optional($batch->finished_at)->toIso8601String(),
        ];
    }
}
