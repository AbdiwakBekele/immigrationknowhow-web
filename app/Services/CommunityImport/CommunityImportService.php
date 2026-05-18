<?php

namespace App\Services\CommunityImport;

use App\Models\CommunityComment;
use App\Models\CommunityImportBatch;
use App\Models\CommunityImportError;
use App\Models\CommunityPost;
use App\Models\CommunityPostReaction;
use App\Models\User;
use App\Support\CountryDisplay;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class CommunityImportService
{
    private const STORAGE_DISK = 'local';
    private const STORAGE_ROOT = 'imports/community';
    private const CHUNK_SIZE = 200;

    private bool $lookupsWarmed = false;

    private bool $postSlugCacheWarmed = false;

    /** @var array<string, true> */
    private array $usedPostSlugs = [];

    /** @var array<string, int> */
    private array $userIdByOldWpId = [];

    /** @var array<string, int> */
    private array $userIdByEmail = [];

    /** @var array<int, string> */
    private array $userFullNameById = [];

    /** @var array<string, int> */
    private array $postIdByOldWpId = [];

    /** @var array<string, int> */
    private array $commentIdByOldWpId = [];

    public function __construct(
        private readonly CsvReader $csvReader,
        private readonly CommunityImportValidator $validator,
    ) {}

    public function createBatchFromUpload(User $uploadedBy, array $validated): CommunityImportBatch
    {
        $selectedOwner = $this->resolveSelectedOwner($uploadedBy, $validated['selected_owner_id'] ?? null);

        $batch = CommunityImportBatch::query()->create([
            'uploaded_by' => $uploadedBy->id,
            'status' => CommunityImportBatch::STATUS_UPLOADED,
            'import_type' => CommunityImportBatch::IMPORT_TYPE_WP_COMMUNITY,
            'summary' => [
                'selected_owner_id' => $selectedOwner->id,
                'selected_owner_name' => $selectedOwner->full_name,
                'import_source' => CommunityImportValidator::IMPORT_SOURCE,
            ],
        ]);

        $directory = self::STORAGE_ROOT.'/'.$batch->id;
        $disk = Storage::disk(self::STORAGE_DISK);

        if ($disk->exists($directory)) {
            $disk->deleteDirectory($directory);
        }

        $disk->makeDirectory($directory);

        $postsFilePath = $this->storeFile($validated['posts_file'], $directory, 'community_posts_import.csv');

        $batch->update([
            'users_file_path' => $this->storeFile($validated['users_file'], $directory, 'users_import.csv'),
            'posts_file_path' => $postsFilePath,
            'comments_file_path' => $this->storeFile($validated['comments_file'], $directory, 'community_comments_import.csv'),
            'reactions_file_path' => $this->storeFile($validated['reactions_file'], $directory, 'community_post_reactions_import.csv'),
        ]);

        $batch->refresh();

        $this->writeLog('info', 'community.import.batch.created', [
            'batch_id' => $batch->id,
            'uploaded_by' => $uploadedBy->id,
            'selected_owner_id' => $selectedOwner->id,
            'selected_owner_name' => $selectedOwner->full_name,
            'storage_directory' => $directory,
            'files' => [
                'users' => $batch->users_file_path,
                'posts' => $batch->posts_file_path,
                'comments' => $batch->comments_file_path,
                'reactions' => $batch->reactions_file_path,
            ],
            'posts_absolute_path' => $disk->path((string) $batch->posts_file_path),
            'posts_size_bytes' => $disk->exists((string) $batch->posts_file_path) ? $disk->size((string) $batch->posts_file_path) : null,
        ]);

        return $this->validateBatch($batch, $selectedOwner);
    }

    public function validateBatch(CommunityImportBatch $batch, ?User $selectedOwner = null): CommunityImportBatch
    {
        $batch->refresh();
        $owner = $selectedOwner ?? $this->resolveSelectedOwnerFromBatch($batch);
        $disk = Storage::disk(self::STORAGE_DISK);
        $postsRelativePath = (string) $batch->posts_file_path;
        $postsAbsolutePath = $postsRelativePath !== '' ? $disk->path($postsRelativePath) : null;

        $this->writeLog('info', 'community.import.batch.validation.started', [
            'batch_id' => $batch->id,
            'selected_owner_id' => $owner->id,
            'selected_owner_name' => $owner->full_name,
            'storage_directory' => self::STORAGE_ROOT.'/'.$batch->id,
            'files' => [
                'users' => $batch->users_file_path,
                'posts' => $batch->posts_file_path,
                'comments' => $batch->comments_file_path,
                'reactions' => $batch->reactions_file_path,
            ],
            'posts_absolute_path' => $postsAbsolutePath,
            'posts_size_bytes' => $postsRelativePath !== '' && $disk->exists($postsRelativePath) ? $disk->size($postsRelativePath) : null,
            'posts_md5' => $postsAbsolutePath && is_file($postsAbsolutePath) ? md5_file($postsAbsolutePath) : null,
            'posts_modified_at' => $postsAbsolutePath && is_file($postsAbsolutePath) ? date('c', filemtime($postsAbsolutePath)) : null,
        ]);

        $batch->update([
            'status' => CommunityImportBatch::STATUS_VALIDATING,
            'errors_count' => 0,
        ]);

        $batch->errors()->delete();

        $result = $this->validator->validate($batch, $owner);

        if ($result['issues'] !== []) {
            $batch->errors()->createMany($result['issues']);

            foreach ($result['issues'] as $issue) {
                $this->writeLog(
                    ($issue['severity'] ?? CommunityImportError::SEVERITY_ERROR) === CommunityImportError::SEVERITY_WARNING ? 'warning' : 'error',
                    'community.import.batch.validation.issue',
                    [
                        'batch_id' => $batch->id,
                        'file_type' => $issue['file_type'] ?? null,
                        'row_number' => $issue['row_number'] ?? null,
                        'old_wp_id' => $issue['old_wp_id'] ?? null,
                        'message' => $issue['message'] ?? null,
                        'row_data' => $this->summarizeRowData($issue['row_data'] ?? null),
                    ]
                );
            }
        }

        $summary = array_merge($batch->summary ?? [], $result['summary'], [
            'validated_at' => now()->toIso8601String(),
        ]);

        $batch->update([
            ...$result['totals'],
            'status' => ($summary['can_import'] ?? false)
                ? CommunityImportBatch::STATUS_READY
                : CommunityImportBatch::STATUS_FAILED,
            'errors_count' => (int) ($summary['errors_count'] ?? 0),
            'summary' => $summary,
            'imported_users' => 0,
            'skipped_users' => 0,
            'imported_posts' => 0,
            'skipped_posts' => 0,
            'imported_comments' => 0,
            'skipped_comments' => 0,
            'imported_reactions' => 0,
            'skipped_reactions' => 0,
            'started_at' => null,
            'finished_at' => null,
        ]);

        $this->writeLog('info', 'community.import.batch.validation.completed', [
            'batch_id' => $batch->id,
            'status' => $batch->fresh()->status,
            'totals' => $result['totals'],
            'warnings_count' => $summary['warnings_count'] ?? 0,
            'errors_count' => $summary['errors_count'] ?? 0,
            'can_import' => $summary['can_import'] ?? false,
            'reference_checks' => $summary['reference_checks'] ?? [],
        ]);

        return $batch->fresh(['uploadedBy']);
    }

    public function run(CommunityImportBatch $batch): CommunityImportBatch
    {
        $batch->refresh();

        if (! ($batch->summary['can_import'] ?? false)) {
            throw ValidationException::withMessages([
                'batch' => 'This import batch has blocking validation errors and cannot be imported yet.',
            ]);
        }

        if ($batch->status === CommunityImportBatch::STATUS_IMPORTING) {
            throw ValidationException::withMessages([
                'batch' => 'This import batch is already running.',
            ]);
        }

        if (! in_array($batch->status, [CommunityImportBatch::STATUS_READY], true)) {
            throw ValidationException::withMessages([
                'batch' => 'This import batch cannot be started in its current state.',
            ]);
        }

        set_time_limit(0);
        ini_set('max_execution_time', '0');

        $this->resetImportRuntimeState();

        $selectedOwner = $this->resolveSelectedOwnerFromBatch($batch);
        $this->warmLookups();
        $this->warmPostSlugCache();

        $this->writeLog('info', 'community.import.batch.run.started', [
            'batch_id' => $batch->id,
            'selected_owner_id' => $selectedOwner->id,
            'selected_owner_name' => $selectedOwner->full_name,
            'totals' => [
                'users' => $batch->total_users,
                'posts' => $batch->total_posts,
                'comments' => $batch->total_comments,
                'reactions' => $batch->total_reactions,
            ],
        ]);

        $stats = [
            'imported_users' => 0,
            'skipped_users' => 0,
            'imported_posts' => 0,
            'skipped_posts' => 0,
            'imported_comments' => 0,
            'skipped_comments' => 0,
            'imported_reactions' => 0,
            'skipped_reactions' => 0,
        ];

        $touchedPostIds = [];

        $batch->update([
            'status' => CommunityImportBatch::STATUS_IMPORTING,
            'started_at' => now(),
            'finished_at' => null,
            'imported_users' => 0,
            'skipped_users' => 0,
            'imported_posts' => 0,
            'skipped_posts' => 0,
            'imported_comments' => 0,
            'skipped_comments' => 0,
            'imported_reactions' => 0,
            'skipped_reactions' => 0,
        ]);

        try {
            $this->processInChunks($this->batchPath($batch->users_file_path), CommunityImportValidator::FILE_USERS, function (array $chunk) use ($batch, &$stats): void {
                DB::transaction(function () use ($chunk, $batch, &$stats): void {
                    foreach ($chunk as $row) {
                        if ($this->importUserRow($batch, $row)) {
                            $stats['imported_users']++;
                        } else {
                            $stats['skipped_users']++;
                        }
                    }
                });
            });

            $batch->update([
                'imported_users' => $stats['imported_users'],
                'skipped_users' => $stats['skipped_users'],
            ]);

            $this->writeLog('info', 'community.import.batch.stage.completed', [
                'batch_id' => $batch->id,
                'file_type' => CommunityImportValidator::FILE_USERS,
                'imported' => $stats['imported_users'],
                'skipped' => $stats['skipped_users'],
            ]);

            $this->processInChunks($this->batchPath($batch->posts_file_path), CommunityImportValidator::FILE_POSTS, function (array $chunk) use ($batch, $selectedOwner, &$stats, &$touchedPostIds): void {
                DB::transaction(function () use ($chunk, $batch, $selectedOwner, &$stats, &$touchedPostIds): void {
                    foreach ($chunk as $row) {
                        $postId = $this->importPostRow($batch, $selectedOwner, $row);

                        if ($postId) {
                            $stats['imported_posts']++;
                            $touchedPostIds[$postId] = true;
                        } else {
                            $stats['skipped_posts']++;
                        }
                    }
                });
            });

            $batch->update([
                'imported_posts' => $stats['imported_posts'],
                'skipped_posts' => $stats['skipped_posts'],
            ]);

            $this->writeLog('info', 'community.import.batch.stage.completed', [
                'batch_id' => $batch->id,
                'file_type' => CommunityImportValidator::FILE_POSTS,
                'imported' => $stats['imported_posts'],
                'skipped' => $stats['skipped_posts'],
            ]);

            $this->processInChunks($this->batchPath($batch->comments_file_path), CommunityImportValidator::FILE_COMMENTS, function (array $chunk) use ($batch, &$stats, &$touchedPostIds): void {
                DB::transaction(function () use ($chunk, $batch, &$stats, &$touchedPostIds): void {
                    foreach ($chunk as $row) {
                        $postId = $this->importCommentRow($batch, $row);

                        if ($postId) {
                            $stats['imported_comments']++;
                            $touchedPostIds[$postId] = true;
                        } else {
                            $stats['skipped_comments']++;
                        }
                    }
                });
            });

            $batch->update([
                'imported_comments' => $stats['imported_comments'],
                'skipped_comments' => $stats['skipped_comments'],
            ]);

            $this->writeLog('info', 'community.import.batch.stage.completed', [
                'batch_id' => $batch->id,
                'file_type' => CommunityImportValidator::FILE_COMMENTS,
                'imported' => $stats['imported_comments'],
                'skipped' => $stats['skipped_comments'],
            ]);

            $this->resolveCommentParents($batch);

            $this->processInChunks($this->batchPath($batch->reactions_file_path), CommunityImportValidator::FILE_REACTIONS, function (array $chunk) use ($batch, &$stats, &$touchedPostIds): void {
                DB::transaction(function () use ($chunk, $batch, &$stats, &$touchedPostIds): void {
                    foreach ($chunk as $row) {
                        $postId = $this->importReactionRow($batch, $row);

                        if ($postId) {
                            $stats['imported_reactions']++;
                            $touchedPostIds[$postId] = true;
                        } else {
                            $stats['skipped_reactions']++;
                        }
                    }
                });
            });

            $batch->update([
                'imported_reactions' => $stats['imported_reactions'],
                'skipped_reactions' => $stats['skipped_reactions'],
            ]);

            $this->writeLog('info', 'community.import.batch.stage.completed', [
                'batch_id' => $batch->id,
                'file_type' => CommunityImportValidator::FILE_REACTIONS,
                'imported' => $stats['imported_reactions'],
                'skipped' => $stats['skipped_reactions'],
            ]);

            $this->recalculatePostCounts(array_keys($touchedPostIds));

            return $this->finishBatch($batch, $stats);
        } catch (Throwable $throwable) {
            $this->logIssue(
                $batch,
                'batch',
                null,
                null,
                CommunityImportError::SEVERITY_ERROR,
                'Import failed unexpectedly: '.$throwable->getMessage()
            );

            $this->writeLog('error', 'community.import.batch.run.failed', [
                'batch_id' => $batch->id,
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
                'stats' => $stats,
            ]);

            $batch->refresh();
            $batch->update([
                ...$stats,
                'status' => CommunityImportBatch::STATUS_FAILED,
                'errors_count' => $batch->errors()->where('severity', CommunityImportError::SEVERITY_ERROR)->count(),
                'finished_at' => now(),
                'summary' => array_merge($batch->summary ?? [], [
                    'runtime_failed_at' => now()->toIso8601String(),
                ]),
            ]);

            return $batch->fresh(['uploadedBy']);
        }
    }

    private function finishBatch(CommunityImportBatch $batch, array $stats): CommunityImportBatch
    {
        $batch->refresh();

        $errorCount = $batch->errors()->where('severity', CommunityImportError::SEVERITY_ERROR)->count();
        $warningCount = $batch->errors()->where('severity', CommunityImportError::SEVERITY_WARNING)->count();

        $summary = array_merge($batch->summary ?? [], [
            'warnings_count' => $warningCount,
            'errors_count' => $errorCount,
            'completed_at' => now()->toIso8601String(),
            'can_import' => false,
        ]);

        $batch->update([
            ...$stats,
            'status' => $errorCount > 0
                ? CommunityImportBatch::STATUS_COMPLETED_WITH_ERRORS
                : CommunityImportBatch::STATUS_COMPLETED,
            'errors_count' => $errorCount,
            'finished_at' => now(),
            'summary' => $summary,
        ]);

        $syncedCountries = $this->syncPostContributorCountries();

        $this->writeLog('info', 'community.import.batch.run.completed', [
            'batch_id' => $batch->id,
            'status' => $batch->fresh()->status,
            'stats' => $stats,
            'contributor_countries_synced' => $syncedCountries,
            'errors_count' => $errorCount,
            'warnings_count' => $warningCount,
            'flash_recommendation' => $errorCount > 0 || array_sum([
                $stats['skipped_users'] ?? 0,
                $stats['skipped_posts'] ?? 0,
                $stats['skipped_comments'] ?? 0,
                $stats['skipped_reactions'] ?? 0,
            ]) > 0
                ? 'success_with_row_issue_summary'
                : 'success',
        ]);

        return $batch->fresh(['uploadedBy']);
    }

    /**
     * @param  array{row_number:int,data:array<string,string>}  $row
     */
    private function importUserRow(CommunityImportBatch $batch, array $row): bool
    {
        $data = $row['data'];
        $oldWpId = trim((string) ($data['old_wp_user_id'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));

        if ($oldWpId === '' || ! ctype_digit($oldWpId) || $email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->logIssue($batch, CommunityImportValidator::FILE_USERS, $row['row_number'], ctype_digit($oldWpId) ? (int) $oldWpId : null, CommunityImportError::SEVERITY_ERROR, 'User row failed runtime validation and was skipped.', $data);

            return false;
        }

        $desiredRole = trim((string) ($data['role'] ?? ''));
        $desiredRole = in_array($desiredRole, ['user', 'provider', 'advertiser', 'affiliate', 'admin', 'super_admin'], true)
            ? $desiredRole
            : 'user';

        $userByOldId = $this->findImportUserByOldWpId((int) $oldWpId);
        $userByEmail = $this->findImportUserByEmail($email);

        if ($userByOldId && $userByEmail && $userByOldId->id !== $userByEmail->id) {
            $this->logIssue($batch, CommunityImportValidator::FILE_USERS, $row['row_number'], (int) $oldWpId, CommunityImportError::SEVERITY_ERROR, 'old_wp_user_id and email point to different existing users.', $data);

            return false;
        }

        if ($userByEmail && $userByEmail->old_wp_user_id && (int) $userByEmail->old_wp_user_id !== (int) $oldWpId) {
            $this->logIssue($batch, CommunityImportValidator::FILE_USERS, $row['row_number'], (int) $oldWpId, CommunityImportError::SEVERITY_ERROR, 'Existing user email is already linked to a different old_wp_user_id.', $data);

            return false;
        }

        $user = $userByOldId ?? $userByEmail ?? new User();

        if ($user->trashed()) {
            $user->restore();
        }

        $isNew = ! $user->exists;

        $this->applyImportedUserAttributes($user, $data, $email, (int) $oldWpId, $isNew);

        try {
            $user->save();
        } catch (QueryException $exception) {
            if (! $isNew || ! $this->isDuplicateEmailException($exception)) {
                throw $exception;
            }

            $existing = $this->findImportUserByEmail($email);

            if ($existing === null) {
                throw $exception;
            }

            if ($existing->old_wp_user_id && (int) $existing->old_wp_user_id !== (int) $oldWpId) {
                $this->logIssue($batch, CommunityImportValidator::FILE_USERS, $row['row_number'], (int) $oldWpId, CommunityImportError::SEVERITY_ERROR, 'Existing user email is already linked to a different old_wp_user_id.', $data);

                return false;
            }

            if ($existing->trashed()) {
                $existing->restore();
            }

            $user = $existing;
            $this->applyImportedUserAttributes($user, $data, $email, (int) $oldWpId, false);
            $user->save();
        }

        $createdAt = $this->parseDate($data['created_at'] ?? null);
        if ($createdAt) {
            $this->syncTimestamps($user, $createdAt, $createdAt);
        }

        $this->syncImportedRole($user, $desiredRole);
        $this->rememberUserLookup($user);

        return true;
    }

    /**
     * @param  array{row_number:int,data:array<string,string>}  $row
     */
    private function importPostRow(CommunityImportBatch $batch, User $selectedOwner, array $row): ?int
    {
        $data = $row['data'];
        $oldWpPostId = trim((string) ($data['old_wp_post_id'] ?? ''));

        if ($oldWpPostId === '' || ! ctype_digit($oldWpPostId)) {
            $this->logIssue($batch, CommunityImportValidator::FILE_POSTS, $row['row_number'], null, CommunityImportError::SEVERITY_ERROR, 'Post row failed runtime validation and was skipped.', $data);

            return null;
        }

        $oldWpPostIdInt = (int) $oldWpPostId;
        $existing = CommunityPost::query()->where('old_wp_post_id', $oldWpPostIdInt)->first();
        $title = $this->resolvePostTitle($data);
        $slug = $this->resolveUniquePostSlug($data['slug'] ?? '', $title, $oldWpPostIdInt, $existing?->id);
        $contributorUserId = $this->resolveContributorUserId($data);
        $contributorCountry = $this->resolvePostContributorCountry($contributorUserId);

        try {
            $post = CommunityPost::query()->updateOrCreate(
                ['old_wp_post_id' => $oldWpPostIdInt],
                [
                    'author_id' => $selectedOwner->id,
                    'contributor_user_id' => $contributorUserId,
                    'contributor_country' => $contributorCountry,
                    'old_wp_author_id' => $this->nullableInteger($data['old_wp_author_id'] ?? null),
                    'old_wp_space_id' => $this->nullableInteger($data['old_wp_space_id'] ?? null),
                    'title' => $title,
                    'slug' => $slug,
                    'description' => trim((string) ($data['description'] ?? '')),
                    'tag' => trim((string) ($data['tag'] ?? '')),
                    'category' => $this->normalizeCategory($data['category'] ?? null),
                    'image_url' => $this->nullIfBlank($data['image_url'] ?? null),
                    'video_url' => $this->nullIfBlank($data['video_url'] ?? null),
                    'is_published' => $this->normalizeBoolean($data['is_published'] ?? null, true),
                    'published_at' => $this->parseDate($data['published_at'] ?? null)
                        ?? $this->parseDate($data['created_at'] ?? null)
                        ?? now(),
                    'import_source' => CommunityImportValidator::IMPORT_SOURCE,
                    'import_meta' => [
                        'contributor_old_wp_user_id' => $this->nullableInteger($data['contributor_old_wp_user_id'] ?? null),
                        'contributor_email' => $this->nullIfBlank($data['contributor_email'] ?? null),
                    ],
                    'imported_at' => now(),
                ]
            );
        } catch (QueryException $exception) {
            $this->logIssue($batch, CommunityImportValidator::FILE_POSTS, $row['row_number'], $oldWpPostIdInt, CommunityImportError::SEVERITY_ERROR, 'Post row could not be imported: '.$exception->getMessage(), $data);

            return null;
        }

        $createdAt = $this->parseDate($data['created_at'] ?? null) ?? $post->created_at;
        $updatedAt = $this->parseDate($data['updated_at'] ?? null) ?? $createdAt;
        $this->syncTimestamps($post, $createdAt, $updatedAt);
        $this->postIdByOldWpId[(string) $oldWpPostIdInt] = $post->id;

        return $post->id;
    }

    /**
     * @param  array{row_number:int,data:array<string,string>}  $row
     */
    private function importCommentRow(CommunityImportBatch $batch, array $row): ?int
    {
        $data = $row['data'];
        $oldWpCommentId = trim((string) ($data['old_wp_comment_id'] ?? ''));
        $oldWpPostId = trim((string) ($data['old_wp_post_id'] ?? ''));

        if (
            $oldWpCommentId === '' || ! ctype_digit($oldWpCommentId)
            || $oldWpPostId === '' || ! ctype_digit($oldWpPostId)
            || trim((string) ($data['content'] ?? '')) === ''
        ) {
            $this->logIssue($batch, CommunityImportValidator::FILE_COMMENTS, $row['row_number'], ctype_digit($oldWpCommentId) ? (int) $oldWpCommentId : null, CommunityImportError::SEVERITY_ERROR, 'Comment row failed runtime validation and was skipped.', $data);

            return null;
        }

        $postId = $this->postIdByOldWpId[$oldWpPostId] ?? null;

        if (! $postId) {
            $this->logIssue($batch, CommunityImportValidator::FILE_COMMENTS, $row['row_number'], (int) $oldWpCommentId, CommunityImportError::SEVERITY_ERROR, 'Comment row references a missing imported post.', $data);

            return null;
        }

        $userId = null;
        $oldWpUserId = trim((string) ($data['old_wp_user_id'] ?? ''));
        if ($oldWpUserId !== '') {
            $userId = $this->userIdByOldWpId[$oldWpUserId] ?? null;
        }

        try {
            $comment = CommunityComment::query()->updateOrCreate(
                ['old_wp_comment_id' => (int) $oldWpCommentId],
                [
                    'community_post_id' => $postId,
                    'user_id' => $userId,
                    'old_wp_post_id' => (int) $oldWpPostId,
                    'old_wp_user_id' => $this->nullableInteger($data['old_wp_user_id'] ?? null),
                    'old_wp_parent_comment_id' => $this->nullableInteger($data['parent_old_wp_comment_id'] ?? null),
                    'parent_id' => null,
                    'author_name' => $this->resolveCommentAuthorName($userId, $data['author_name'] ?? null),
                    'content' => trim((string) ($data['content'] ?? '')),
                    'import_source' => CommunityImportValidator::IMPORT_SOURCE,
                    'imported_at' => now(),
                ]
            );
        } catch (QueryException $exception) {
            $this->logIssue($batch, CommunityImportValidator::FILE_COMMENTS, $row['row_number'], (int) $oldWpCommentId, CommunityImportError::SEVERITY_ERROR, 'Comment row could not be imported: '.$exception->getMessage(), $data);

            return null;
        }

        $createdAt = $this->parseDate($data['created_at'] ?? null) ?? $comment->created_at;
        $updatedAt = $this->parseDate($data['updated_at'] ?? null) ?? $createdAt;
        $this->syncTimestamps($comment, $createdAt, $updatedAt);
        $this->commentIdByOldWpId[$oldWpCommentId] = $comment->id;

        return $postId;
    }

    /**
     * @param  array{row_number:int,data:array<string,string>}  $row
     */
    private function importReactionRow(CommunityImportBatch $batch, array $row): ?int
    {
        $data = $row['data'];
        $oldWpReactionId = trim((string) ($data['old_wp_reaction_id'] ?? ''));
        $oldWpPostId = trim((string) ($data['old_wp_post_id'] ?? ''));
        $type = trim((string) ($data['type'] ?? ''));

        if (
            $oldWpReactionId === '' || ! ctype_digit($oldWpReactionId)
            || $oldWpPostId === '' || ! ctype_digit($oldWpPostId)
            || ! in_array($type, ['like', 'share', 'bookmark'], true)
        ) {
            $this->logIssue($batch, CommunityImportValidator::FILE_REACTIONS, $row['row_number'], ctype_digit($oldWpReactionId) ? (int) $oldWpReactionId : null, CommunityImportError::SEVERITY_ERROR, 'Reaction row failed runtime validation and was skipped.', $data);

            return null;
        }

        $postId = $this->postIdByOldWpId[$oldWpPostId] ?? null;

        if (! $postId) {
            $this->logIssue($batch, CommunityImportValidator::FILE_REACTIONS, $row['row_number'], (int) $oldWpReactionId, CommunityImportError::SEVERITY_ERROR, 'Reaction row references a missing imported post.', $data);

            return null;
        }

        $oldWpUserId = trim((string) ($data['old_wp_user_id'] ?? ''));
        $userId = $oldWpUserId !== '' ? ($this->userIdByOldWpId[$oldWpUserId] ?? null) : null;
        $dedupeKey = trim((string) ($data['dedupe_key'] ?? ''));
        if ($dedupeKey === '') {
            $dedupeKey = sprintf(
                'wp_%s_%s_%s_%s',
                $oldWpReactionId,
                $oldWpPostId,
                $type,
                $oldWpUserId !== '' ? $oldWpUserId : 'guest'
            );
        }

        try {
            $reaction = CommunityPostReaction::query()->updateOrCreate(
                ['old_wp_reaction_id' => (int) $oldWpReactionId],
                [
                    'community_post_id' => $postId,
                    'user_id' => $userId,
                    'old_wp_post_id' => (int) $oldWpPostId,
                    'old_wp_user_id' => $this->nullableInteger($data['old_wp_user_id'] ?? null),
                    'type' => $type,
                    'dedupe_key' => $dedupeKey,
                    'import_source' => CommunityImportValidator::IMPORT_SOURCE,
                    'imported_at' => now(),
                ]
            );
        } catch (QueryException $exception) {
            $this->logIssue($batch, CommunityImportValidator::FILE_REACTIONS, $row['row_number'], (int) $oldWpReactionId, CommunityImportError::SEVERITY_ERROR, 'Reaction row could not be imported: '.$exception->getMessage(), $data);

            return null;
        }

        $createdAt = $this->parseDate($data['created_at'] ?? null) ?? $reaction->created_at;
        $this->syncTimestamps($reaction, $createdAt, $createdAt);

        return $postId;
    }

    private function resolveCommentParents(CommunityImportBatch $batch): void
    {
        $this->processInChunks($this->batchPath($batch->comments_file_path), CommunityImportValidator::FILE_COMMENTS, function (array $chunk) use ($batch): void {
            DB::transaction(function () use ($chunk, $batch): void {
                foreach ($chunk as $row) {
                    $data = $row['data'];
                    $oldWpCommentId = trim((string) ($data['old_wp_comment_id'] ?? ''));
                    $parentOldWpCommentId = trim((string) ($data['parent_old_wp_comment_id'] ?? ''));

                    if ($oldWpCommentId === '' || $parentOldWpCommentId === '') {
                        continue;
                    }

                    $commentId = $this->commentIdByOldWpId[$oldWpCommentId] ?? null;
                    $parentId = $this->commentIdByOldWpId[$parentOldWpCommentId] ?? null;

                    if (! $commentId || ! $parentId) {
                        continue;
                    }

                    CommunityComment::query()
                        ->whereKey($commentId)
                        ->update(['parent_id' => $parentId]);
                }
            });
        });
    }

    /**
     * @param  array<int, int|string>  $postIds
     */
    private function recalculatePostCounts(array $postIds): void
    {
        $postIds = array_values(array_unique(array_map('intval', $postIds)));

        if ($postIds === []) {
            return;
        }

        foreach (array_chunk($postIds, 200) as $chunkIds) {
            $chunkCommentCounts = CommunityComment::query()
                ->selectRaw('community_post_id, COUNT(*) as aggregate')
                ->whereIn('community_post_id', $chunkIds)
                ->groupBy('community_post_id')
                ->pluck('aggregate', 'community_post_id');

            $chunkReactionCounts = CommunityPostReaction::query()
                ->selectRaw("
                    community_post_id,
                    SUM(CASE WHEN type = 'like' THEN 1 ELSE 0 END) as likes_count,
                    SUM(CASE WHEN type = 'share' THEN 1 ELSE 0 END) as shares_count,
                    SUM(CASE WHEN type = 'bookmark' THEN 1 ELSE 0 END) as bookmarks_count
                ")
                ->whereIn('community_post_id', $chunkIds)
                ->groupBy('community_post_id')
                ->get()
                ->keyBy('community_post_id');

            foreach ($chunkIds as $postId) {
                $reaction = $chunkReactionCounts->get($postId);

                CommunityPost::query()
                    ->whereKey($postId)
                    ->update([
                        'comments_count' => (int) ($chunkCommentCounts[$postId] ?? 0),
                        'likes_count' => (int) ($reaction?->likes_count ?? 0),
                        'shares_count' => (int) ($reaction?->shares_count ?? 0),
                        'bookmarks_count' => (int) ($reaction?->bookmarks_count ?? 0),
                    ]);
            }
        }
    }

    private function resetImportRuntimeState(): void
    {
        $this->lookupsWarmed = false;
        $this->postSlugCacheWarmed = false;
        $this->usedPostSlugs = [];
        $this->userIdByOldWpId = [];
        $this->userIdByEmail = [];
        $this->userFullNameById = [];
        $this->postIdByOldWpId = [];
        $this->commentIdByOldWpId = [];
        CsvReader::clearCachedPostRows();
    }

    private function warmPostSlugCache(): void
    {
        if ($this->postSlugCacheWarmed) {
            return;
        }

        foreach (CommunityPost::query()->pluck('slug') as $slug) {
            $slug = trim((string) $slug);

            if ($slug !== '') {
                $this->usedPostSlugs[$slug] = true;
            }
        }

        $this->postSlugCacheWarmed = true;
    }

    private function processInChunks(string $path, string $fileType, callable $callback): void
    {
        $chunk = [];

        foreach ($this->csvReader->rows($path, $fileType) as $row) {
            $chunk[] = $row;

            if (count($chunk) >= self::CHUNK_SIZE) {
                $callback($chunk);
                $chunk = [];
            }
        }

        if ($chunk !== []) {
            $callback($chunk);
        }
    }

    private function storeFile(UploadedFile $file, string $directory, string $name): string
    {
        $disk = Storage::disk(self::STORAGE_DISK);
        $relativePath = $directory.'/'.$name;

        if ($disk->exists($relativePath)) {
            $disk->delete($relativePath);
        }

        $storedPath = $file->storeAs($directory, $name, self::STORAGE_DISK);
        $absolutePath = $disk->path($storedPath);

        $this->writeLog('info', 'community.import.file.stored', [
            'relative_path' => $storedPath,
            'absolute_path' => $absolutePath,
            'original_client_name' => $file->getClientOriginalName(),
            'size_bytes' => $disk->size($storedPath),
            'md5' => is_file($absolutePath) ? md5_file($absolutePath) : null,
        ]);

        return $storedPath;
    }

    private function batchPath(?string $relativePath): string
    {
        return Storage::disk(self::STORAGE_DISK)->path((string) $relativePath);
    }

    private function resolveSelectedOwner(User $uploadedBy, mixed $selectedOwnerId): User
    {
        $selectedOwner = null;

        if ($selectedOwnerId) {
            $selectedOwner = User::query()->find((int) $selectedOwnerId);
        }

        return $selectedOwner ?: $uploadedBy;
    }

    private function resolveSelectedOwnerFromBatch(CommunityImportBatch $batch): User
    {
        $ownerId = (int) ($batch->summary['selected_owner_id'] ?? 0);
        $owner = $ownerId ? User::query()->find($ownerId) : null;

        if ($owner) {
            return $owner;
        }

        if ($batch->uploadedBy) {
            return $batch->uploadedBy;
        }

        throw ValidationException::withMessages([
            'selected_owner_id' => 'Unable to resolve the selected admin owner for this batch.',
        ]);
    }

    private function warmLookups(): void
    {
        if ($this->lookupsWarmed) {
            return;
        }

        User::query()
            ->withTrashed()
            ->select(['id', 'old_wp_user_id', 'email', 'first_name', 'last_name'])
            ->get()
            ->each(function (User $user): void {
                $this->rememberUserLookup($user);
            });

        CommunityPost::query()
            ->select(['id', 'old_wp_post_id'])
            ->whereNotNull('old_wp_post_id')
            ->get()
            ->each(function (CommunityPost $post): void {
                $this->postIdByOldWpId[(string) $post->old_wp_post_id] = $post->id;
            });

        CommunityComment::query()
            ->select(['id', 'old_wp_comment_id'])
            ->whereNotNull('old_wp_comment_id')
            ->get()
            ->each(function (CommunityComment $comment): void {
                $this->commentIdByOldWpId[(string) $comment->old_wp_comment_id] = $comment->id;
            });

        $this->lookupsWarmed = true;
    }

    private function rememberUserLookup(User $user): void
    {
        if ($user->old_wp_user_id) {
            $this->userIdByOldWpId[(string) $user->old_wp_user_id] = $user->id;
        }

        $email = strtolower(trim((string) $user->email));
        if ($email !== '') {
            $this->userIdByEmail[$email] = $user->id;
        }

        $this->userFullNameById[$user->id] = trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
    }

    private function findImportUserByOldWpId(int $oldWpUserId): ?User
    {
        return User::query()
            ->withTrashed()
            ->where('old_wp_user_id', $oldWpUserId)
            ->first();
    }

    private function findImportUserByEmail(string $email): ?User
    {
        $email = strtolower(trim($email));

        if ($email === '') {
            return null;
        }

        if (isset($this->userIdByEmail[$email])) {
            return User::query()->withTrashed()->find($this->userIdByEmail[$email]);
        }

        return User::query()
            ->withTrashed()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();
    }

    /**
     * @param  array<string, string>  $data
     */
    private function applyImportedUserAttributes(User $user, array $data, string $email, int $oldWpUserId, bool $isNew): void
    {
        [$firstName, $lastName] = $this->resolveUserNames($data);

        $user->first_name = $firstName;
        $user->last_name = $lastName;
        $user->email = $email;
        $user->phone = $this->nullIfBlank($data['phone'] ?? null);
        $user->avatar = $this->nullIfBlank($data['avatar'] ?? null);
        $user->city = $this->nullIfBlank($data['city'] ?? null);
        $user->state = $this->nullIfBlank($data['state'] ?? null);
        $user->postal_code = $this->nullIfBlank($data['postal_code'] ?? null);
        $user->country = CountryDisplay::normalizeForStorage($this->nullIfBlank($data['country'] ?? null));
        $user->languages = $this->normalizeLanguages($data['languages'] ?? '');
        $user->preferred_language = $this->nullIfBlank($data['preferred_language'] ?? null) ?? 'en';
        $user->timezone = $this->nullIfBlank($data['timezone'] ?? null) ?? 'America/New_York';
        $user->is_active = $this->normalizeBoolean($data['is_active'] ?? null, true);
        $user->old_wp_user_id = $oldWpUserId;
        $user->import_source = CommunityImportValidator::IMPORT_SOURCE;
        $user->imported_at = now();
        $user->onboarding_data = $this->mergeImportedUserMeta($user->onboarding_data, $data);

        if ($isNew) {
            $user->password = Hash::make(Str::password(32));
        }
    }

    private function isDuplicateEmailException(QueryException $exception): bool
    {
        $errorCode = (int) ($exception->errorInfo[1] ?? 0);

        return $errorCode === 1062
            && str_contains(strtolower($exception->getMessage()), 'users_email_unique');
    }

    /**
     * @param  array<string, string>  $data
     * @return array{0:string,1:string}
     */
    private function resolveUserNames(array $data): array
    {
        $firstName = trim((string) ($data['first_name'] ?? ''));
        $lastName = trim((string) ($data['last_name'] ?? ''));
        $displayName = trim((string) ($data['display_name'] ?? ''));

        if ($firstName !== '' || $lastName !== '') {
            return [
                $firstName !== '' ? $firstName : ($displayName !== '' ? $displayName : 'Community'),
                $lastName !== '' ? $lastName : 'Member',
            ];
        }

        if ($displayName !== '') {
            $parts = preg_split('/\s+/', $displayName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if (count($parts) === 1) {
                return [$parts[0], 'Member'];
            }

            return [
                array_shift($parts) ?: 'Community',
                implode(' ', $parts) ?: 'Member',
            ];
        }

        return ['Community', 'Member'];
    }

    /**
     * @param  array<string, string>  $data
     */
    private function mergeImportedUserMeta(mixed $existing, array $data): array
    {
        $payload = is_array($existing) ? $existing : [];
        $bio = trim((string) ($data['bio'] ?? ''));

        if ($bio === '') {
            return $payload;
        }

        $wpImport = is_array($payload['wp_import'] ?? null) ? $payload['wp_import'] : [];
        $wpImport['bio'] = $bio;
        $payload['wp_import'] = $wpImport;

        return $payload;
    }

    private function syncImportedRole(User $user, string $desiredRole): void
    {
        $user->loadMissing('roles');

        if ($user->roles->isEmpty()) {
            $user->syncRoles([$desiredRole]);

            return;
        }

        if (! $user->hasRole($desiredRole)) {
            return;
        }
    }

    /**
     * @param  array<string, string>  $data
     */
    private function resolvePostTitle(array $data): string
    {
        $title = trim((string) ($data['title'] ?? ''));

        if ($title !== '') {
            return $title;
        }

        $slug = trim((string) ($data['slug'] ?? ''));
        if ($slug !== '') {
            return Str::headline(str_replace('-', ' ', $slug));
        }

        $description = trim((string) ($data['description'] ?? ''));
        if ($description !== '') {
            return Str::limit($description, 80, '');
        }

        return 'Imported Community Post';
    }

    private function resolveUniquePostSlug(string $sourceSlug, string $title, int $oldWpPostId, ?int $ignoreId = null): string
    {
        $baseSlug = trim($sourceSlug) !== ''
            ? Str::slug(trim($sourceSlug))
            : Str::slug($title);

        if ($baseSlug === '') {
            $baseSlug = 'community-post-'.$oldWpPostId;
        }

        $candidate = $this->reserveUniquePostSlug($baseSlug);

        if ($candidate !== null) {
            return $candidate;
        }

        $candidate = $this->reserveUniquePostSlug("{$baseSlug}-{$oldWpPostId}");

        if ($candidate !== null) {
            return $candidate;
        }

        $counter = 1;

        while (true) {
            $candidate = $this->reserveUniquePostSlug("{$baseSlug}-{$oldWpPostId}-{$counter}");

            if ($candidate !== null) {
                return $candidate;
            }

            $counter++;
        }
    }

    private function reserveUniquePostSlug(string $slug): ?string
    {
        $slug = trim($slug);

        if ($slug === '' || isset($this->usedPostSlugs[$slug])) {
            return null;
        }

        $this->usedPostSlugs[$slug] = true;

        return $slug;
    }

    /**
     * @param  array<string, string>  $data
     */
    private function resolveContributorUserId(array $data): ?int
    {
        $contributorOldWpUserId = trim((string) ($data['contributor_old_wp_user_id'] ?? ''));
        if ($contributorOldWpUserId !== '') {
            $resolved = $this->userIdByOldWpId[$contributorOldWpUserId] ?? null;
            if ($resolved) {
                return $resolved;
            }
        }

        $contributorEmail = strtolower(trim((string) ($data['contributor_email'] ?? '')));
        if ($contributorEmail !== '') {
            return $this->userIdByEmail[$contributorEmail] ?? null;
        }

        return null;
    }

    private function resolvePostContributorCountry(?int $contributorUserId): ?string
    {
        if (! $contributorUserId) {
            return null;
        }

        $country = User::query()->whereKey($contributorUserId)->value('country');

        return CountryDisplay::normalizeForStorage(is_string($country) ? $country : null);
    }

    public function syncPostContributorCountries(): int
    {
        $updated = 0;

        CommunityPost::query()
            ->whereNotNull('contributor_user_id')
            ->with('contributor:id,country')
            ->orderBy('id')
            ->chunkById(100, function ($posts) use (&$updated): void {
                foreach ($posts as $post) {
                    $country = CountryDisplay::normalizeForStorage($post->contributor?->country);
                    if ($country === null || $post->contributor_country === $country) {
                        continue;
                    }

                    $post->forceFill(['contributor_country' => $country])->save();
                    $updated++;
                }
            });

        return $updated;
    }

    private function resolveCommentAuthorName(?int $userId, ?string $authorName): string
    {
        if ($userId && filled($this->userFullNameById[$userId] ?? null)) {
            return $this->userFullNameById[$userId];
        }

        $authorName = trim((string) $authorName);

        return $authorName !== '' ? $authorName : 'Community member';
    }

    private function normalizeCategory(?string $value): string
    {
        $value = trim((string) $value);

        return in_array($value, CommunityImportValidator::CATEGORY_VALUES, true)
            ? $value
            : 'feed';
    }

    /**
     * @return array<int, string>|null
     */
    private function normalizeLanguages(?string $value): ?array
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, '[')) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $normalized = array_values(array_filter(array_map(fn ($item) => trim((string) $item), $decoded)));

                return $normalized === [] ? null : $normalized;
            }
        }

        $normalized = array_values(array_filter(array_map(
            fn ($item) => trim((string) $item),
            explode(',', $value)
        )));

        return $normalized === [] ? null : $normalized;
    }

    private function normalizeBoolean(mixed $value, bool $default): bool
    {
        $normalized = strtolower(trim((string) $value));

        if ($normalized === '') {
            return $default;
        }

        return match ($normalized) {
            '1', 'true', 'yes', 'y', 'on' => true,
            '0', 'false', 'no', 'n', 'off' => false,
            default => $default,
        };
    }

    private function parseDate(mixed $value): ?Carbon
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function nullIfBlank(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nullableInteger(mixed $value): ?int
    {
        $value = trim((string) $value);

        return ctype_digit($value) ? (int) $value : null;
    }

    private function syncTimestamps(object $model, ?Carbon $createdAt, ?Carbon $updatedAt): void
    {
        if (! $createdAt && ! $updatedAt) {
            return;
        }

        $model->timestamps = false;

        if ($createdAt) {
            $model->created_at = $createdAt;
        }

        if ($updatedAt) {
            $model->updated_at = $updatedAt;
        }

        $model->saveQuietly();
        $model->timestamps = true;
    }

    /**
     * @param  array<string, string>|null  $rowData
     */
    private function logIssue(
        CommunityImportBatch $batch,
        string $fileType,
        ?int $rowNumber,
        ?int $oldWpId,
        string $severity,
        string $message,
        ?array $rowData = null,
    ): void {
        $batch->errors()->create([
            'file_type' => $fileType,
            'row_number' => $rowNumber,
            'old_wp_id' => $oldWpId,
            'severity' => $severity,
            'message' => $message,
            'row_data' => $rowData,
        ]);

        $this->writeLog(
            $severity === CommunityImportError::SEVERITY_WARNING ? 'warning' : 'error',
            'community.import.batch.runtime.issue',
            [
                'batch_id' => $batch->id,
                'file_type' => $fileType,
                'row_number' => $rowNumber,
                'old_wp_id' => $oldWpId,
                'message' => $message,
                'row_data' => $this->summarizeRowData($rowData),
            ]
        );
    }

    /**
     * @param  array<string, string>|null  $rowData
     * @return array<string, mixed>|null
     */
    private function summarizeRowData(?array $rowData): ?array
    {
        if (! $rowData) {
            return null;
        }

        $summary = [];
        foreach ([
            'old_wp_user_id',
            'old_wp_post_id',
            'old_wp_comment_id',
            'old_wp_reaction_id',
            'old_wp_author_id',
            'old_wp_space_id',
            'contributor_old_wp_user_id',
            'contributor_email',
            'title',
            'slug',
            'category',
            'type',
            'dedupe_key',
            'author_name',
        ] as $key) {
            if (array_key_exists($key, $rowData) && trim((string) $rowData[$key]) !== '') {
                $summary[$key] = $rowData[$key];
            }
        }

        if (array_key_exists('description', $rowData) && trim((string) $rowData['description']) !== '') {
            $summary['description_length'] = strlen((string) $rowData['description']);
            $summary['description_preview'] = Str::limit(str_replace(["\r", "\n"], [' ', ' '], (string) $rowData['description']), 240);
        }

        if (array_key_exists('content', $rowData) && trim((string) $rowData['content']) !== '') {
            $summary['content_length'] = strlen((string) $rowData['content']);
            $summary['content_preview'] = Str::limit(str_replace(["\r", "\n"], [' ', ' '], (string) $rowData['content']), 240);
        }

        return $summary === [] ? null : $summary;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function writeLog(string $level, string $message, array $context = []): void
    {
        Log::log($level, $message, $context);
    }
}
