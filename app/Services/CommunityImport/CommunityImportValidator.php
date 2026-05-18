<?php

namespace App\Services\CommunityImport;

use App\Models\CommunityComment;
use App\Models\CommunityImportBatch;
use App\Models\CommunityPost;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class CommunityImportValidator
{
    public const FILE_USERS = 'users';
    public const FILE_POSTS = 'posts';
    public const FILE_COMMENTS = 'comments';
    public const FILE_REACTIONS = 'reactions';

    public const IMPORT_SOURCE = 'wp_community';

    public const REQUIRED_HEADERS = [
        self::FILE_USERS => [
            'old_wp_user_id',
            'first_name',
            'last_name',
            'display_name',
            'email',
            'phone',
            'avatar',
            'city',
            'state',
            'postal_code',
            'country',
            'languages',
            'preferred_language',
            'timezone',
            'role',
            'bio',
            'is_active',
            'created_at',
        ],
        self::FILE_POSTS => [
            'old_wp_post_id',
            'old_wp_author_id',
            'contributor_old_wp_user_id',
            'contributor_email',
            'title',
            'slug',
            'description',
            'tag',
            'category',
            'image_url',
            'video_url',
            'old_wp_space_id',
            'is_published',
            'published_at',
            'created_at',
            'updated_at',
        ],
        self::FILE_COMMENTS => [
            'old_wp_comment_id',
            'old_wp_post_id',
            'old_wp_user_id',
            'parent_old_wp_comment_id',
            'author_name',
            'content',
            'created_at',
            'updated_at',
        ],
        self::FILE_REACTIONS => [
            'old_wp_reaction_id',
            'old_wp_post_id',
            'old_wp_user_id',
            'type',
            'dedupe_key',
            'created_at',
        ],
    ];

    public const CATEGORY_VALUES = [
        'feed',
        'ask-intro',
        'ask-announcement',
        'immigration-legal',
        'career-finance',
        'health-wellness',
        'daily-living',
        'culture-community',
    ];

    public function __construct(
        private readonly CsvReader $csvReader
    ) {}

    public static function headerSamples(): array
    {
        return self::REQUIRED_HEADERS;
    }

    public function validate(CommunityImportBatch $batch, User $selectedOwner): array
    {
        $batch->refresh();
        CsvReader::clearCachedPostRows();
        $filePaths = $this->batchFilePaths($batch);
        $fileMetadata = [];
        $rowsByType = [];
        $headersSummary = [];
        $issues = [];
        $warningsCount = 0;
        $blockingErrorsCount = 0;
        $totals = [
            'total_users' => 0,
            'total_posts' => 0,
            'total_comments' => 0,
            'total_reactions' => 0,
        ];

        foreach ($filePaths as $fileType => $path) {
            $fileMetadata[$fileType] = $this->fileMetadata($batch, $fileType, $path);

            if ($fileType === self::FILE_POSTS) {
                $this->logPostsFileDiagnostics($batch, $path, $fileMetadata[$fileType]);
            }

            try {
                $headers = $this->csvReader->headers($path, $fileType);
                $rows = $this->csvReader->all($path, $fileType);

                if ($fileType === self::FILE_POSTS) {
                    $this->logParsedPostsDiagnostics($batch, $rows, $fileMetadata[$fileType]);
                }
            } catch (Throwable $throwable) {
                $headersSummary[$fileType] = [
                    'actual' => [],
                    'expected' => self::REQUIRED_HEADERS[$fileType],
                    'missing' => self::REQUIRED_HEADERS[$fileType],
                ];
                $rowsByType[$fileType] = [];

                $this->pushIssue(
                    $issues,
                    $blockingErrorsCount,
                    $warningsCount,
                    $fileType,
                    null,
                    null,
                    'error',
                    'Unable to parse CSV file: '.$throwable->getMessage()
                );

                Log::error('community.import.csv.parse_failed', [
                    'batch_id' => $batch->id,
                    'file_type' => $fileType,
                    'path' => $path,
                    'message' => $throwable->getMessage(),
                ]);

                continue;
            }

            $missingHeaders = array_values(array_diff(self::REQUIRED_HEADERS[$fileType], $headers));

            $headersSummary[$fileType] = [
                'actual' => $headers,
                'expected' => self::REQUIRED_HEADERS[$fileType],
                'missing' => $missingHeaders,
            ];

            $rowsByType[$fileType] = $rows;
            $totals[$this->totalKeyFor($fileType)] = count($rows);

            if ($missingHeaders !== []) {
                $this->pushIssue(
                    $issues,
                    $blockingErrorsCount,
                    $warningsCount,
                    $fileType,
                    null,
                    null,
                    'error',
                    'Missing required headers: '.implode(', ', $missingHeaders)
                );
            }
        }

        $existingRoleNames = DB::table('roles')->pluck('name')->map(fn ($value) => (string) $value)->all();
        $existingUserOldIds = User::query()
            ->whereNotNull('old_wp_user_id')
            ->pluck('old_wp_user_id')
            ->map(fn ($value) => (string) $value)
            ->all();
        $existingUserEmails = User::query()
            ->pluck('email')
            ->map(fn ($value) => strtolower(trim((string) $value)))
            ->filter()
            ->all();
        $existingPostOldIds = CommunityPost::query()
            ->whereNotNull('old_wp_post_id')
            ->pluck('old_wp_post_id')
            ->map(fn ($value) => (string) $value)
            ->all();
        $existingCommentOldIds = CommunityComment::query()
            ->whereNotNull('old_wp_comment_id')
            ->pluck('old_wp_comment_id')
            ->map(fn ($value) => (string) $value)
            ->all();

        $csvUserOldIds = [];
        $csvUserEmails = [];
        $csvPostOldIds = [];
        $orderedCsvPostOldIds = [];
        $csvCommentOldIds = [];
        $duplicatePostOldIdCount = 0;
        $invalidPostOldIdCount = 0;
        $invalidPostOldIdSamples = [];
        $postsCsvIntegrityBlocked = false;
        $referenceChecks = [
            'posts_missing_contributors' => 0,
            'comments_missing_posts' => 0,
            'comments_missing_users' => 0,
            'comments_missing_parents' => 0,
            'reactions_missing_posts' => 0,
            'reactions_missing_users' => 0,
        ];

        if ($headersSummary[self::FILE_USERS]['missing'] === []) {
            $seenUserIds = [];
            $seenUserEmails = [];
            foreach ($rowsByType[self::FILE_USERS] as $row) {
                $data = $row['data'];
                $oldWpId = trim((string) ($data['old_wp_user_id'] ?? ''));
                $email = strtolower(trim((string) ($data['email'] ?? '')));
                $role = trim((string) ($data['role'] ?? ''));

                if ($oldWpId === '' || ! ctype_digit($oldWpId)) {
                    $this->pushIssue($issues, $blockingErrorsCount, $warningsCount, self::FILE_USERS, $row['row_number'], null, 'error', 'old_wp_user_id is required and must be numeric.', $data);
                    continue;
                }

                if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $this->pushIssue($issues, $blockingErrorsCount, $warningsCount, self::FILE_USERS, $row['row_number'], (int) $oldWpId, 'error', 'email is required and must be a valid email address.', $data);
                }

                if (isset($seenUserIds[$oldWpId])) {
                    $this->pushIssue($issues, $blockingErrorsCount, $warningsCount, self::FILE_USERS, $row['row_number'], (int) $oldWpId, 'error', 'Duplicate old_wp_user_id detected in users_import.csv.', $data);
                }

                $seenUserIds[$oldWpId] = true;
                $csvUserOldIds[$oldWpId] = true;

                if ($email !== '') {
                    if (isset($seenUserEmails[$email])) {
                        $this->pushIssue($issues, $blockingErrorsCount, $warningsCount, self::FILE_USERS, $row['row_number'], (int) $oldWpId, 'warning', 'Duplicate email detected in users_import.csv; later rows may update the same user.', $data);
                    }

                    $seenUserEmails[$email] = true;
                    $csvUserEmails[$email] = true;
                }

                if ($role !== '' && ! in_array($role, $existingRoleNames, true)) {
                    $this->pushIssue($issues, $blockingErrorsCount, $warningsCount, self::FILE_USERS, $row['row_number'], (int) $oldWpId, 'warning', 'Unknown role; importer will default this row to user.', $data);
                }
            }
        }

        $knownUserOldIds = array_fill_keys(array_merge(array_keys($csvUserOldIds), $existingUserOldIds), true);
        $knownUserEmails = array_fill_keys(array_merge(array_keys($csvUserEmails), $existingUserEmails), true);

        if ($headersSummary[self::FILE_POSTS]['missing'] === []) {
            $seenPostIds = [];
            foreach ($rowsByType[self::FILE_POSTS] as $row) {
                $data = $row['data'];
                $oldWpId = trim((string) ($data['old_wp_post_id'] ?? ''));
                $contributorOldWpId = trim((string) ($data['contributor_old_wp_user_id'] ?? ''));
                $contributorEmail = strtolower(trim((string) ($data['contributor_email'] ?? '')));
                $category = trim((string) ($data['category'] ?? ''));

                if ($oldWpId === '' || ! ctype_digit($oldWpId)) {
                    $invalidPostOldIdCount++;

                    if (count($invalidPostOldIdSamples) < 5) {
                        $invalidPostOldIdSamples[] = [
                            'row_number' => $row['row_number'],
                            'old_wp_post_id' => $data['old_wp_post_id'] ?? null,
                            'old_wp_author_id' => $data['old_wp_author_id'] ?? null,
                            'title' => $data['title'] ?? null,
                        ];
                    }

                    $postsCsvIntegrityBlocked = true;

                    break;
                }

                if (isset($seenPostIds[$oldWpId])) {
                    $duplicatePostOldIdCount++;
                    $this->pushIssue($issues, $blockingErrorsCount, $warningsCount, self::FILE_POSTS, $row['row_number'], (int) $oldWpId, 'error', 'Duplicate old_wp_post_id detected in community_posts_import.csv.', $data);
                }

                $seenPostIds[$oldWpId] = true;
                $csvPostOldIds[$oldWpId] = true;
                $orderedCsvPostOldIds[] = $oldWpId;

                if ($category !== '' && ! in_array($category, self::CATEGORY_VALUES, true)) {
                    $this->pushIssue($issues, $blockingErrorsCount, $warningsCount, self::FILE_POSTS, $row['row_number'], (int) $oldWpId, 'warning', 'Invalid category; importer will default this row to feed.', $data);
                }

                if (trim((string) ($data['title'] ?? '')) === '') {
                    $this->pushIssue($issues, $blockingErrorsCount, $warningsCount, self::FILE_POSTS, $row['row_number'], (int) $oldWpId, 'warning', 'Title is blank; importer will derive it from slug or description.', $data);
                }

                if (trim((string) ($data['description'] ?? '')) === '') {
                    $this->pushIssue($issues, $blockingErrorsCount, $warningsCount, self::FILE_POSTS, $row['row_number'], (int) $oldWpId, 'warning', 'Description is blank; importer will use an empty string for this post.', $data);
                }

                if ($contributorOldWpId !== '' || $contributorEmail !== '') {
                    $hasContributor = ($contributorOldWpId !== '' && isset($knownUserOldIds[$contributorOldWpId]))
                        || ($contributorEmail !== '' && isset($knownUserEmails[$contributorEmail]));

                    if (! $hasContributor) {
                        $referenceChecks['posts_missing_contributors']++;
                        $this->pushIssue($issues, $blockingErrorsCount, $warningsCount, self::FILE_POSTS, $row['row_number'], (int) $oldWpId, 'warning', 'Contributor could not be resolved by contributor_old_wp_user_id or contributor_email; contributor_user_id will be null.', $data);
                    }
                }
            }
        }

        Log::info('community.import.csv.posts.parsed', [
            'batch_id' => $batch->id,
            'total_parsed_posts' => count($rowsByType[self::FILE_POSTS] ?? []),
            'first_3_old_wp_post_ids' => array_slice($orderedCsvPostOldIds, 0, 3),
            'first_5_old_wp_post_ids' => array_slice($orderedCsvPostOldIds, 0, 5),
            'last_5_old_wp_post_ids' => array_values(array_slice($orderedCsvPostOldIds, -5)),
            'duplicate_old_wp_post_id_count' => $duplicatePostOldIdCount,
            'invalid_old_wp_post_id_count' => $invalidPostOldIdCount,
            'invalid_old_wp_post_id_samples' => $invalidPostOldIdSamples,
            'integrity_blocked' => $postsCsvIntegrityBlocked,
            'posts_file' => $fileMetadata[self::FILE_POSTS] ?? null,
        ]);

        if ($invalidPostOldIdCount > 0) {
            $this->pushIssue(
                $issues,
                $blockingErrorsCount,
                $warningsCount,
                self::FILE_POSTS,
                $invalidPostOldIdSamples[0]['row_number'] ?? null,
                null,
                'error',
                'Posts CSV parsing failed. The file is being split incorrectly. Please use a valid CSV parser or upload the no-multiline CSV.'
            );

            Log::error('community.import.csv.posts.parsing_failed', [
                'batch_id' => $batch->id,
                'total_parsed_posts' => count($rowsByType[self::FILE_POSTS] ?? []),
                'duplicate_old_wp_post_id_count' => $duplicatePostOldIdCount,
                'invalid_old_wp_post_id_count' => $invalidPostOldIdCount,
                'invalid_old_wp_post_id_samples' => $invalidPostOldIdSamples,
            ]);
        }

        $validPostOldIds = $this->buildValidPostOldIdsFromCsvRows($rowsByType[self::FILE_POSTS] ?? []);

        Log::info('community.import.csv.posts.valid_ids', [
            'batch_id' => $batch->id,
            'csv_post_id_count' => count($validPostOldIds),
            'first_3_old_wp_post_ids' => array_slice(array_keys($validPostOldIds), 0, 3),
            'last_3_old_wp_post_ids' => array_slice(array_keys($validPostOldIds), -3),
        ]);

        $knownPostOldIds = array_fill_keys(
            array_merge(array_keys($validPostOldIds), $existingPostOldIds),
            true
        );
        $canValidatePostReferences = $headersSummary[self::FILE_POSTS]['missing'] === [] && ! $postsCsvIntegrityBlocked;

        if ($headersSummary[self::FILE_COMMENTS]['missing'] === []) {
            $seenCommentIds = [];
            foreach ($rowsByType[self::FILE_COMMENTS] as $row) {
                $data = $row['data'];
                $oldWpId = trim((string) ($data['old_wp_comment_id'] ?? ''));
                $oldWpPostId = trim((string) ($data['old_wp_post_id'] ?? ''));
                $oldWpUserId = trim((string) ($data['old_wp_user_id'] ?? ''));
                $parentOldWpCommentId = trim((string) ($data['parent_old_wp_comment_id'] ?? ''));

                if ($oldWpId === '' || ! ctype_digit($oldWpId)) {
                    $this->pushIssue($issues, $blockingErrorsCount, $warningsCount, self::FILE_COMMENTS, $row['row_number'], null, 'error', 'old_wp_comment_id is required and must be numeric.', $data);
                    continue;
                }

                if (isset($seenCommentIds[$oldWpId])) {
                    $this->pushIssue($issues, $blockingErrorsCount, $warningsCount, self::FILE_COMMENTS, $row['row_number'], (int) $oldWpId, 'error', 'Duplicate old_wp_comment_id detected in community_comments_import.csv.', $data);
                }

                $seenCommentIds[$oldWpId] = true;
                $csvCommentOldIds[$oldWpId] = true;

                if ($oldWpPostId === '' || ! ctype_digit($oldWpPostId)) {
                    $this->pushIssue($issues, $blockingErrorsCount, $warningsCount, self::FILE_COMMENTS, $row['row_number'], (int) $oldWpId, 'error', 'old_wp_post_id is required and must be numeric.', $data);
                } elseif ($canValidatePostReferences && ! isset($knownPostOldIds[$oldWpPostId])) {
                    $referenceChecks['comments_missing_posts']++;
                    $this->pushIssue($issues, $blockingErrorsCount, $warningsCount, self::FILE_COMMENTS, $row['row_number'], (int) $oldWpId, 'error', 'old_wp_post_id does not match an imported or already imported community post.', $data);
                }

                if ($oldWpUserId !== '' && ! isset($knownUserOldIds[$oldWpUserId])) {
                    $referenceChecks['comments_missing_users']++;
                    $this->pushIssue($issues, $blockingErrorsCount, $warningsCount, self::FILE_COMMENTS, $row['row_number'], (int) $oldWpId, 'warning', 'old_wp_user_id could not be resolved; comment will import without a linked user.', $data);
                }

                if (trim((string) ($data['content'] ?? '')) === '') {
                    $this->pushIssue($issues, $blockingErrorsCount, $warningsCount, self::FILE_COMMENTS, $row['row_number'], (int) $oldWpId, 'error', 'content is required.', $data);
                }

                if ($parentOldWpCommentId !== '' && ! isset($seenCommentIds[$parentOldWpCommentId]) && ! in_array($parentOldWpCommentId, $existingCommentOldIds, true)) {
                    $referenceChecks['comments_missing_parents']++;
                    $this->pushIssue($issues, $blockingErrorsCount, $warningsCount, self::FILE_COMMENTS, $row['row_number'], (int) $oldWpId, 'warning', 'parent_old_wp_comment_id could not be resolved during preview; parent_id may remain null.', $data);
                }
            }
        }

        $knownCommentOldIds = array_fill_keys(array_merge(array_keys($csvCommentOldIds), $existingCommentOldIds), true);

        if ($headersSummary[self::FILE_REACTIONS]['missing'] === []) {
            $seenReactionIds = [];
            foreach ($rowsByType[self::FILE_REACTIONS] as $row) {
                $data = $row['data'];
                $oldWpId = trim((string) ($data['old_wp_reaction_id'] ?? ''));
                $oldWpPostId = trim((string) ($data['old_wp_post_id'] ?? ''));
                $oldWpUserId = trim((string) ($data['old_wp_user_id'] ?? ''));
                $type = trim((string) ($data['type'] ?? ''));

                if ($oldWpId === '' || ! ctype_digit($oldWpId)) {
                    $this->pushIssue($issues, $blockingErrorsCount, $warningsCount, self::FILE_REACTIONS, $row['row_number'], null, 'error', 'old_wp_reaction_id is required and must be numeric.', $data);
                    continue;
                }

                if (isset($seenReactionIds[$oldWpId])) {
                    $this->pushIssue($issues, $blockingErrorsCount, $warningsCount, self::FILE_REACTIONS, $row['row_number'], (int) $oldWpId, 'error', 'Duplicate old_wp_reaction_id detected in community_post_reactions_import.csv.', $data);
                }

                $seenReactionIds[$oldWpId] = true;

                if ($oldWpPostId === '' || ! ctype_digit($oldWpPostId)) {
                    $this->pushIssue($issues, $blockingErrorsCount, $warningsCount, self::FILE_REACTIONS, $row['row_number'], (int) $oldWpId, 'error', 'old_wp_post_id is required and must be numeric.', $data);
                } elseif ($canValidatePostReferences && ! isset($knownPostOldIds[$oldWpPostId])) {
                    $referenceChecks['reactions_missing_posts']++;
                    $this->pushIssue($issues, $blockingErrorsCount, $warningsCount, self::FILE_REACTIONS, $row['row_number'], (int) $oldWpId, 'error', 'old_wp_post_id does not match an imported or already imported community post.', $data);
                }

                if ($oldWpUserId !== '' && ! isset($knownUserOldIds[$oldWpUserId])) {
                    $referenceChecks['reactions_missing_users']++;
                    $this->pushIssue($issues, $blockingErrorsCount, $warningsCount, self::FILE_REACTIONS, $row['row_number'], (int) $oldWpId, 'warning', 'old_wp_user_id could not be resolved; reaction will import without a linked user.', $data);
                }

                if (! in_array($type, ['like', 'share', 'bookmark'], true)) {
                    $this->pushIssue($issues, $blockingErrorsCount, $warningsCount, self::FILE_REACTIONS, $row['row_number'], (int) $oldWpId, 'error', 'type must be one of like, share, or bookmark.', $data);
                }

                if (trim((string) ($data['dedupe_key'] ?? '')) === '') {
                    $this->pushIssue($issues, $blockingErrorsCount, $warningsCount, self::FILE_REACTIONS, $row['row_number'], (int) $oldWpId, 'warning', 'dedupe_key is blank; importer will generate a WordPress-safe fallback value.', $data);
                }
            }
        }

        return [
            'totals' => $totals,
            'issues' => $issues,
            'summary' => [
                'selected_owner_id' => $selectedOwner->id,
                'selected_owner_name' => $selectedOwner->full_name,
                'import_source' => self::IMPORT_SOURCE,
                'headers' => $headersSummary,
                'file_metadata' => $fileMetadata,
                'reference_checks' => $referenceChecks,
                'warnings_count' => $warningsCount,
                'errors_count' => $blockingErrorsCount,
                'known_comment_reference_count' => count($knownCommentOldIds),
                'posts_parser_integrity_blocked' => $postsCsvIntegrityBlocked,
                'posts_invalid_old_wp_post_id_count' => $invalidPostOldIdCount,
                'can_import' => $blockingErrorsCount === 0,
            ],
        ];
    }

    private function batchFilePaths(CommunityImportBatch $batch): array
    {
        return [
            self::FILE_USERS => Storage::disk('local')->path((string) $batch->users_file_path),
            self::FILE_POSTS => Storage::disk('local')->path((string) $batch->posts_file_path),
            self::FILE_COMMENTS => Storage::disk('local')->path((string) $batch->comments_file_path),
            self::FILE_REACTIONS => Storage::disk('local')->path((string) $batch->reactions_file_path),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $issues
     * @param  array<string, string>  $rowData
     */
    private function pushIssue(
        array &$issues,
        int &$blockingErrorsCount,
        int &$warningsCount,
        string $fileType,
        ?int $rowNumber,
        ?int $oldWpId,
        string $severity,
        string $message,
        array $rowData = [],
    ): void {
        $issues[] = [
            'file_type' => $fileType,
            'row_number' => $rowNumber,
            'old_wp_id' => $oldWpId,
            'severity' => $severity,
            'message' => $message,
            'row_data' => $rowData === [] ? null : $rowData,
        ];

        if ($severity === 'warning') {
            $warningsCount++;

            return;
        }

        $blockingErrorsCount++;
    }

    private function totalKeyFor(string $fileType): string
    {
        return match ($fileType) {
            self::FILE_USERS => 'total_users',
            self::FILE_POSTS => 'total_posts',
            self::FILE_COMMENTS => 'total_comments',
            default => 'total_reactions',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function fileMetadata(CommunityImportBatch $batch, string $fileType, string $absolutePath): array
    {
        $relativePath = match ($fileType) {
            self::FILE_USERS => (string) $batch->users_file_path,
            self::FILE_POSTS => (string) $batch->posts_file_path,
            self::FILE_COMMENTS => (string) $batch->comments_file_path,
            default => (string) $batch->reactions_file_path,
        };

        return [
            'batch_id' => $batch->id,
            'file_type' => $fileType,
            'relative_path' => $relativePath,
            'absolute_path' => $absolutePath,
            'exists' => is_file($absolutePath),
            'size_bytes' => is_file($absolutePath) ? filesize($absolutePath) : null,
            'md5' => is_file($absolutePath) ? md5_file($absolutePath) : null,
            'modified_at' => is_file($absolutePath) ? date('c', filemtime($absolutePath)) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function logPostsFileDiagnostics(CommunityImportBatch $batch, string $absolutePath, array $metadata): void
    {
        Log::info('community.import.csv.posts.file', array_merge($metadata, [
            'batch_id' => $batch->id,
            'expected_relative_path' => 'imports/community/'.$batch->id.'/community_posts_import.csv',
            'absolute_path' => $absolutePath,
        ]));
    }

    /**
     * @param  array<int, array{row_number:int,data:array<string, string>}>  $rows
     * @param  array<string, mixed>  $metadata
     */
    private function logParsedPostsDiagnostics(CommunityImportBatch $batch, array $rows, array $metadata): void
    {
        $firstIds = [];

        foreach ($rows as $row) {
            $oldWpId = trim((string) ($row['data']['old_wp_post_id'] ?? ''));

            if ($oldWpId !== '' && ctype_digit($oldWpId)) {
                $firstIds[] = $oldWpId;
            }

            if (count($firstIds) >= 3) {
                break;
            }
        }

        Log::info('community.import.csv.posts.parse_sample', [
            'batch_id' => $batch->id,
            'posts_file' => $metadata,
            'first_3_old_wp_post_ids' => $firstIds,
            'total_parsed_posts' => count($rows),
        ]);

        foreach ($rows as $row) {
            if (trim((string) ($row['data']['old_wp_post_id'] ?? '')) !== '367') {
                continue;
            }

            $description = (string) ($row['data']['description'] ?? '');

            Log::info('community.import.csv.posts.row_367', [
                'batch_id' => $batch->id,
                'row_number' => $row['row_number'],
                'old_wp_post_id' => $row['data']['old_wp_post_id'] ?? null,
                'category' => $row['data']['category'] ?? null,
                'old_wp_space_id' => $row['data']['old_wp_space_id'] ?? null,
                'title' => $row['data']['title'] ?? null,
                'slug' => $row['data']['slug'] ?? null,
                'description_length' => strlen($description),
                'description_has_newlines' => str_contains($description, "\n") || str_contains($description, "\r"),
                'description_preview' => Str::limit(str_replace(["\r\n", "\r", "\n"], ' ', $description), 240),
                'posts_file' => $metadata,
            ]);

            return;
        }

        Log::warning('community.import.csv.posts.row_367_missing', [
            'batch_id' => $batch->id,
            'total_parsed_posts' => count($rows),
            'posts_file' => $metadata,
        ]);
    }

    /**
     * @param  array<int, array{row_number:int,data:array<string, string>}>  $postRows
     * @return array<string, true>
     */
    private function buildValidPostOldIdsFromCsvRows(array $postRows): array
    {
        $validPostOldIds = [];

        foreach ($postRows as $row) {
            $oldWpPostId = trim((string) ($row['data']['old_wp_post_id'] ?? ''));

            if ($oldWpPostId !== '' && ctype_digit($oldWpPostId)) {
                $validPostOldIds[$oldWpPostId] = true;
            }
        }

        return $validPostOldIds;
    }

}
