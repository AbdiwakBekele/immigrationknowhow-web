<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CommunityComment;
use App\Models\CommunityImportBatch;
use App\Models\CommunityPost;
use App\Models\CommunityPostReaction;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminCommunityImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
        \App\Services\CommunityImport\CsvReader::clearCachedPostRows();
    }

    public function test_admin_can_upload_and_preview_a_valid_community_import_batch(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post(route('admin.community.import.store'), [
            'selected_owner_id' => $admin->id,
            'users_file' => $this->csvUpload('users_import.csv', $this->usersCsv()),
            'posts_file' => $this->csvUpload('community_posts_import.csv', $this->postsCsv()),
            'comments_file' => $this->csvUpload('community_comments_import.csv', $this->commentsCsv()),
            'reactions_file' => $this->csvUpload('community_post_reactions_import.csv', $this->reactionsCsv()),
        ]);

        $batch = CommunityImportBatch::query()->firstOrFail();

        $response->assertRedirect(route('admin.community.import.preview', $batch->id));
        $this->assertSame(CommunityImportBatch::STATUS_READY, $batch->status);
        $this->assertSame(1, $batch->total_users);
        $this->assertSame(1, $batch->total_posts);
        $this->assertSame(1, $batch->total_comments);
        $this->assertSame(1, $batch->total_reactions);
        $this->assertTrue($batch->summary['can_import']);
        $this->assertSame('Community Admin', $batch->summary['selected_owner_name']);
    }

    public function test_existing_community_pages_still_load_after_importer_changes(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->get(route('admin.community.index'))
            ->assertOk();

        $this->get(route('community.index'))
            ->assertOk();

        $this->getJson(route('community.posts'))
            ->assertOk()
            ->assertJsonStructure(['posts']);
    }

    public function test_multiline_post_description_is_parsed_as_one_csv_record(): void
    {
        $admin = $this->createAdmin();

        $batch = $this->createImportBatch(
            $admin,
            $this->postsCsvWithMultilineDescription(),
            $this->commentsCsv(postId: 367),
            $this->reactionsCsv(postId: 367)
        );

        $batch->refresh();

        $this->assertSame(CommunityImportBatch::STATUS_READY, $batch->status);
        $this->assertSame(1, $batch->total_posts);
        $this->assertSame(0, $batch->errors_count);
        $this->assertTrue($batch->summary['can_import']);

        $run = $this->actingAs($admin)->post(route('admin.community.import.run', $batch->id));
        $run->assertRedirect(route('admin.community.import.show', $batch->id));

        $post = CommunityPost::query()->where('old_wp_post_id', 367)->firstOrFail();
        $normalizedDescription = str_replace(["\r\n", "\r"], "\n", $post->description);

        $this->assertStringContainsString("First paragraph, with commas, quotes like \"quoted text\", and markdown [Read more](https://example.com/article).", $normalizedDescription);
        $this->assertStringContainsString("Second paragraph\nwith an intentional line break inside the same CSV field.", $normalizedDescription);
        $this->assertDatabaseCount('community_posts', 1);
    }

    public function test_malformed_posts_csv_orphan_rows_are_merged_into_previous_post(): void
    {
        $admin = $this->createAdmin();

        $batch = $this->createImportBatch(
            $admin,
            $this->postsCsvWithMalformedSplitDescription(),
            $this->commentsCsv(postId: 367),
            $this->reactionsCsv(postId: 367)
        );

        $batch->refresh();

        $this->assertSame(CommunityImportBatch::STATUS_READY, $batch->status);
        $this->assertSame(1, $batch->total_posts);
        $this->assertSame(0, $batch->errors_count);
        $this->assertTrue($batch->summary['can_import']);
        $this->assertSame(0, $batch->summary['posts_invalid_old_wp_post_id_count'] ?? 0);

        $messages = $batch->errors()->pluck('message')->all();

        $this->assertNotContains('Posts CSV parsing failed. The file is being split incorrectly. Please use a valid CSV parser or upload the no-multiline CSV.', $messages);
        $this->assertNotContains('old_wp_post_id does not match an imported or already imported community post.', $messages);

        $run = $this->actingAs($admin)->post(route('admin.community.import.run', $batch->id));
        $run->assertRedirect(route('admin.community.import.show', $batch->id));

        $post = CommunityPost::query()->where('old_wp_post_id', 367)->firstOrFail();
        $this->assertStringContainsString('[Mayor Johnson', $post->description);
    }

    public function test_unquoted_commas_in_post_description_are_parsed_with_column_aware_parser(): void
    {
        $admin = $this->createAdmin();

        $batch = $this->createImportBatch(
            $admin,
            $this->postsCsvWithUnquotedCommasInDescription(),
            $this->commentsCsv(postId: 367),
            $this->reactionsCsv(postId: 367)
        );

        $batch->refresh();

        $this->assertSame(CommunityImportBatch::STATUS_READY, $batch->status);
        $this->assertSame(1, $batch->total_posts);
        $this->assertSame(0, $batch->errors_count);
        $this->assertTrue($batch->summary['can_import']);
        $this->assertSame(0, $batch->summary['warnings_count'] ?? 0);

        $run = $this->actingAs($admin)->post(route('admin.community.import.run', $batch->id));
        $run->assertRedirect(route('admin.community.import.show', $batch->id));

        $post = CommunityPost::query()->where('old_wp_post_id', 367)->firstOrFail();

        $this->assertSame('daily-living', $post->category);
        $this->assertSame(2, $post->old_wp_space_id);
        $this->assertStringContainsString('Updated on: January 1, 2026', $post->description);
        $this->assertStringContainsString('pre-ordained decision', $post->description);
    }

    public function test_flattened_problem_posts_csv_passes_validation_and_imports(): void
    {
        $admin = $this->createAdmin();

        $batch = $this->createImportBatch(
            $admin,
            $this->postsCsvWithFlattenedDescription(),
            $this->commentsCsv(postId: 367),
            $this->reactionsCsv(postId: 367)
        );

        $batch->refresh();

        $this->assertSame(CommunityImportBatch::STATUS_READY, $batch->status);
        $this->assertSame(0, $batch->errors_count);
        $this->assertTrue($batch->summary['can_import']);
        $this->assertSame(0, $batch->summary['posts_invalid_old_wp_post_id_count']);

        $run = $this->actingAs($admin)->post(route('admin.community.import.run', $batch->id));
        $run->assertRedirect(route('admin.community.import.show', $batch->id));

        $post = CommunityPost::query()->where('old_wp_post_id', 367)->firstOrFail();
        $this->assertStringContainsString('Judge voids decision to end legal status', $post->title);
        $this->assertStringContainsString('A federal judge on Wednesday called it a "pre-ordained decision."', $post->description);
    }

    public function test_import_updates_existing_user_by_email_instead_of_duplicate_insert(): void
    {
        $admin = $this->createAdmin();

        $existing = User::create([
            'first_name' => 'Existing',
            'last_name' => 'Account',
            'email' => 'test@gmail.com',
            'password' => Hash::make('Password123!'),
            'onboarding_completed' => true,
            'onboarding_completed_at' => now(),
            'is_active' => true,
        ]);

        $batch = $this->createImportBatch(
            $admin,
            $this->postsCsv(),
            usersCsv: $this->usersCsvWithWpUser(39, 'test@gmail.com', 'Test', 'Test'),
        );

        $batch->refresh();
        $this->assertTrue($batch->summary['can_import']);

        $run = $this->actingAs($admin)->post(route('admin.community.import.run', $batch->id));
        $run->assertRedirect(route('admin.community.import.show', $batch->id));

        $existing->refresh();

        $this->assertSame($existing->id, User::query()->where('email', 'test@gmail.com')->value('id'));
        $this->assertSame(39, $existing->old_wp_user_id);
        $this->assertSame('Test', $existing->first_name);
        $this->assertSame(1, User::query()->whereRaw('LOWER(email) = ?', ['test@gmail.com'])->count());
        $this->assertDatabaseCount('users', 3);
    }

    public function test_import_restores_soft_deleted_user_with_matching_email(): void
    {
        $admin = $this->createAdmin();

        $existing = User::create([
            'first_name' => 'Deleted',
            'last_name' => 'User',
            'email' => 'test@gmail.com',
            'password' => Hash::make('Password123!'),
            'onboarding_completed' => true,
            'onboarding_completed_at' => now(),
            'is_active' => true,
        ]);
        $existing->delete();

        $batch = $this->createImportBatch(
            $admin,
            $this->postsCsv(),
            usersCsv: $this->usersCsvWithWpUser(39, 'test@gmail.com', 'Test', 'Test'),
        );

        $run = $this->actingAs($admin)->post(route('admin.community.import.run', $batch->id));
        $run->assertRedirect(route('admin.community.import.show', $batch->id));

        $existing->refresh();

        $this->assertNull($existing->deleted_at);
        $this->assertSame(39, $existing->old_wp_user_id);
        $this->assertSame('Test', $existing->first_name);
    }

    public function test_import_is_idempotent_across_multiple_batches_using_old_wp_ids(): void
    {
        $admin = $this->createAdmin();

        $firstBatch = $this->createImportBatch($admin, $this->postsCsv());

        $runFirst = $this->actingAs($admin)->post(route('admin.community.import.run', $firstBatch->id));
        $runFirst->assertRedirect(route('admin.community.import.show', $firstBatch->id));

        $importedUser = User::query()->where('old_wp_user_id', 101)->firstOrFail();
        $importedPost = CommunityPost::query()->where('old_wp_post_id', 201)->firstOrFail();
        $importedComment = CommunityComment::query()->where('old_wp_comment_id', 301)->firstOrFail();
        $importedReaction = CommunityPostReaction::query()->where('old_wp_reaction_id', 401)->firstOrFail();

        $this->assertSame($admin->id, $importedPost->author_id);
        $this->assertSame($importedUser->id, $importedPost->contributor_user_id);
        $this->assertSame($importedUser->id, $importedComment->user_id);
        $this->assertSame($importedUser->id, $importedReaction->user_id);
        $this->assertSame(1, $importedPost->comments_count);
        $this->assertSame(1, $importedPost->likes_count);
        $this->assertSame(0, $importedPost->shares_count);
        $this->assertSame(0, $importedPost->bookmarks_count);

        $secondBatch = $this->createImportBatch($admin, $this->postsCsv('Updated WP Import Title'));
        $runSecond = $this->actingAs($admin)->post(route('admin.community.import.run', $secondBatch->id));
        $runSecond->assertRedirect(route('admin.community.import.show', $secondBatch->id));

        $this->assertDatabaseCount('community_posts', 1);
        $this->assertDatabaseCount('community_comments', 1);
        $this->assertDatabaseCount('community_post_reactions', 1);
        $this->assertDatabaseCount('users', 2); // admin + imported WordPress user

        $updatedPost = CommunityPost::query()->where('old_wp_post_id', 201)->firstOrFail();
        $this->assertSame('Updated WP Import Title', $updatedPost->title);
        $this->assertSame(1, $updatedPost->likes_count);
        $this->assertSame(1, $updatedPost->comments_count);
    }

    private function createImportBatch(
        User $admin,
        string $postsCsv,
        ?string $commentsCsv = null,
        ?string $reactionsCsv = null,
        ?string $usersCsv = null,
    ): CommunityImportBatch
    {
        $response = $this->actingAs($admin)->post(route('admin.community.import.store'), [
            'selected_owner_id' => $admin->id,
            'users_file' => $this->csvUpload('users_import.csv', $usersCsv ?? $this->usersCsv()),
            'posts_file' => $this->csvUpload('community_posts_import.csv', $postsCsv),
            'comments_file' => $this->csvUpload('community_comments_import.csv', $commentsCsv ?? $this->commentsCsv()),
            'reactions_file' => $this->csvUpload('community_post_reactions_import.csv', $reactionsCsv ?? $this->reactionsCsv()),
        ]);

        $batch = CommunityImportBatch::query()->latest('id')->firstOrFail();
        $response->assertRedirect(route('admin.community.import.preview', $batch->id));

        return $batch;
    }

    private function createAdmin(): User
    {
        $admin = User::create([
            'first_name' => 'Community',
            'last_name' => 'Admin',
            'email' => 'community-admin'.uniqid().'@example.com',
            'password' => Hash::make('Password123!'),
            'onboarding_completed' => true,
            'onboarding_completed_at' => now(),
            'is_active' => true,
        ]);

        $admin->assignRole(UserRole::ADMIN->value);

        return $admin;
    }

    private function csvUpload(string $name, string $contents): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $contents);
    }

    private function usersCsv(): string
    {
        return implode("\n", [
            'old_wp_user_id,first_name,last_name,display_name,email,phone,avatar,city,state,postal_code,country,languages,preferred_language,timezone,role,bio,is_active,created_at',
            '101,Jane,Doe,Jane Doe,jane@example.com,5551112222,https://example.com/avatar.jpg,New York,NY,10001,US,"English,Spanish",en,America/New_York,user,"WordPress bio",1,2024-01-10 08:00:00',
        ]);
    }

    private function usersCsvWithWpUser(int $oldWpUserId, string $email, string $firstName, string $lastName): string
    {
        return implode("\n", [
            'old_wp_user_id,first_name,last_name,display_name,email,phone,avatar,city,state,postal_code,country,languages,preferred_language,timezone,role,bio,is_active,created_at',
            sprintf(
                '%d,%s,%s,%s %s,%s,,,,,,,en,America/New_York,user,,1,2024-01-10 08:00:00',
                $oldWpUserId,
                $firstName,
                $lastName,
                $firstName,
                $lastName,
                $email
            ),
            '101,Jane,Doe,Jane Doe,jane@example.com,5551112222,https://example.com/avatar.jpg,New York,NY,10001,US,"English,Spanish",en,America/New_York,user,"WordPress bio",1,2024-01-10 08:00:00',
        ]);
    }

    private function postsCsv(string $title = 'Imported WP Title'): string
    {
        return implode("\n", [
            'old_wp_post_id,old_wp_author_id,contributor_old_wp_user_id,contributor_email,title,slug,description,tag,category,image_url,video_url,old_wp_space_id,is_published,published_at,created_at,updated_at',
            sprintf('201,101,101,jane@example.com,%s,imported-wp-title,"Imported description",general,feed,https://example.com/image.jpg,https://example.com/video.mp4,55,1,2024-01-11 09:00:00,2024-01-11 09:00:00,2024-01-11 09:30:00', $title),
        ]);
    }

    private function commentsCsv(int $postId = 201): string
    {
        return implode("\n", [
            'old_wp_comment_id,old_wp_post_id,old_wp_user_id,parent_old_wp_comment_id,author_name,content,created_at,updated_at',
            "301,{$postId},101,,Jane Doe,\"Imported comment\",2024-01-11 10:00:00,2024-01-11 10:00:00",
        ]);
    }

    private function reactionsCsv(int $postId = 201): string
    {
        return implode("\n", [
            'old_wp_reaction_id,old_wp_post_id,old_wp_user_id,type,dedupe_key,created_at',
            "401,{$postId},101,like,u101_like_{$postId},2024-01-11 11:00:00",
        ]);
    }

    private function postsCsvWithMultilineDescription(): string
    {
        $description = <<<CSV
"First paragraph, with commas, quotes like ""quoted text"", and markdown [Read more](https://example.com/article).

Second paragraph
with an intentional line break inside the same CSV field."
CSV;

        return implode("\n", [
            'old_wp_post_id,old_wp_author_id,contributor_old_wp_user_id,contributor_email,title,slug,description,tag,category,image_url,video_url,old_wp_space_id,is_published,published_at,created_at,updated_at',
            "367,101,101,jane@example.com,Multiline Import Post,multiline-import-post,{$description},general,feed,https://example.com/image.jpg,,77,1,2024-01-12 09:00:00,2024-01-12 09:00:00,2024-01-12 09:30:00",
        ]);
    }

    private function postsCsvWithMalformedSplitDescription(): string
    {
        $description = <<<CSV
"A federal judge on Wednesday called it a "pre-ordained decision."

[Mayor Johnson, Cmdr. Bovino spar on social media over "ABOLISH ICE" snowplow name](https://example.com/mayor-johnson)

### Go deeper with The Free Press

* [Immigration](https://example.com/tag/immigration)"
CSV;

        return implode("\n", [
            'old_wp_post_id,old_wp_author_id,contributor_old_wp_user_id,contributor_email,title,slug,description,tag,category,image_url,video_url,old_wp_space_id,is_published,published_at,created_at,updated_at',
            "367,68,68,ktz1000@yahoo.com,Judge voids decision to end legal status,judge-voids-decision,{$description},,feed,,, ,1,2026-01-01 10:22:00,2026-01-01 10:22:00,2026-01-01 10:22:00",
        ]);
    }

    private function postsCsvWithUnquotedCommasInDescription(): string
    {
        $description = 'By Camilo Montoya-Galvez, Updated on: January 1, 2026 / 10:22 AM EST / CBS News A federal judge called it a pre-ordained decision.';

        return implode("\n", [
            'old_wp_post_id,old_wp_author_id,contributor_old_wp_user_id,contributor_email,title,slug,description,tag,category,image_url,video_url,old_wp_space_id,is_published,published_at,created_at,updated_at',
            "367,101,101,jane@example.com,Judge voids decision to end legal status,judge-voids-decision,{$description},,daily-living,,,2,1,2026-01-01 10:22:00,2026-01-01 10:22:00,2026-01-01 10:22:00",
        ]);
    }

    private function postsCsvWithFlattenedDescription(): string
    {
        $description = 'A federal judge on Wednesday called it a ""pre-ordained decision."" [Mayor Johnson, Cmdr. Bovino spar on social media over ""ABOLISH ICE"" snowplow name](https://example.com/mayor-johnson) ### Go deeper with The Free Press * [Immigration](https://example.com/tag/immigration) * [Honduras](https://example.com/tag/honduras)';

        return implode("\n", [
            'old_wp_post_id,old_wp_author_id,contributor_old_wp_user_id,contributor_email,title,slug,description,tag,category,image_url,video_url,old_wp_space_id,is_published,published_at,created_at,updated_at',
            "367,68,68,ktz1000@yahoo.com,Judge voids decision to end legal status,judge-voids-decision,\"{$description}\",,feed,,, ,1,2026-01-01 10:22:00,2026-01-01 10:22:00,2026-01-01 10:22:00",
        ]);
    }
}
