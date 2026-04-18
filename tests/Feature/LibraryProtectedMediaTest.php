<?php

namespace Tests\Feature;

use App\Models\LibraryItem;
use App\Models\LibraryUserAccess;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LibraryProtectedMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchased_library_media_streams_inline_without_attachment_download(): void
    {
        Storage::fake(LibraryItem::LIBRARY_MEDIA_DISK);

        $user = $this->createUser();
        $item = $this->createLibraryItem();

        LibraryUserAccess::query()->create([
            'user_id' => $user->id,
            'library_item_id' => $item->id,
            'purchased_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('library.media', $item));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');

        $contentDisposition = strtolower((string) $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('inline;', $contentDisposition);
        $this->assertStringNotContainsString('attachment', $contentDisposition);
    }

    public function test_purchased_ebook_audio_companion_streams_inline_without_attachment_download(): void
    {
        Storage::fake(LibraryItem::LIBRARY_MEDIA_DISK);

        $user = $this->createUser();
        $audioPath = 'library/files/sample-audio.mp3';
        Storage::disk(LibraryItem::LIBRARY_MEDIA_DISK)->put($audioPath, 'sample audio');
        $item = $this->createLibraryItem([
            'audio_file_path' => $audioPath,
            'audio_file_name' => 'sample-audio.mp3',
            'audio_file_size' => 12,
            'audio_file_type' => 'mp3',
        ]);

        LibraryUserAccess::query()->create([
            'user_id' => $user->id,
            'library_item_id' => $item->id,
            'purchased_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('library.media', [
            'item' => $item,
            'asset' => 'audio',
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'audio/mpeg');

        $contentDisposition = strtolower((string) $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('inline;', $contentDisposition);
        $this->assertStringNotContainsString('attachment', $contentDisposition);
    }

    public function test_library_media_requires_purchased_access(): void
    {
        Storage::fake(LibraryItem::LIBRARY_MEDIA_DISK);

        $user = $this->createUser();
        $item = $this->createLibraryItem();

        $this->actingAs($user)
            ->get(route('library.media', $item))
            ->assertForbidden();
    }

    public function test_legacy_download_route_redirects_to_reader_for_purchased_access(): void
    {
        Storage::fake(LibraryItem::LIBRARY_MEDIA_DISK);

        $user = $this->createUser();
        $item = $this->createLibraryItem();

        LibraryUserAccess::query()->create([
            'user_id' => $user->id,
            'library_item_id' => $item->id,
            'purchased_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('library.download', $item))
            ->assertRedirect(route('library.read', $item));
    }

    public function test_reader_and_audio_progress_are_saved_separately(): void
    {
        Storage::fake(LibraryItem::LIBRARY_MEDIA_DISK);

        $user = $this->createUser();
        $item = $this->createLibraryItem();
        $access = LibraryUserAccess::query()->create([
            'user_id' => $user->id,
            'library_item_id' => $item->id,
            'purchased_at' => now(),
        ]);

        $this->actingAs($user)
            ->postJson(route('library.progress', $item), [
                'mode' => 'reading',
                'progress' => [
                    'page' => 8,
                    'total_pages' => 40,
                    'percentage' => 20,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('progress.reading.page', 8);

        $this->actingAs($user)
            ->postJson(route('library.progress', $item), [
                'mode' => 'audio',
                'progress' => [
                    'position' => 95,
                    'duration' => 500,
                    'percentage' => 19,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('progress.audio.position', 95);

        $progress = $access->fresh()->progress;

        $this->assertSame(8, $progress['reading']['page']);
        $this->assertSame(40, $progress['reading']['total_pages']);
        $this->assertSame(95, $progress['audio']['position']);
        $this->assertSame(500, $progress['audio']['duration']);
    }

    private function createUser(array $overrides = []): User
    {
        return User::query()->create(array_merge([
            'first_name' => 'Library',
            'last_name' => 'User',
            'email' => 'library-user-'.uniqid().'@example.com',
            'password' => 'Password123!',
            'onboarding_completed' => true,
            'onboarding_completed_at' => now(),
            'is_active' => true,
        ], $overrides));
    }

    private function createLibraryItem(array $overrides = []): LibraryItem
    {
        $path = $overrides['file_path'] ?? 'library/files/sample.pdf';
        Storage::disk(LibraryItem::LIBRARY_MEDIA_DISK)->put($path, '%PDF-1.4 sample');

        return LibraryItem::query()->create(array_merge([
            'title' => 'Sample Immigration Guide',
            'slug' => 'sample-immigration-guide',
            'type' => 'ebook',
            'description' => 'A practical immigration guide.',
            'file_path' => $path,
            'file_name' => 'sample-guide.pdf',
            'file_size' => 15,
            'file_type' => 'pdf',
            'is_premium' => false,
            'price' => 0,
            'currency' => 'USD',
            'is_active' => true,
        ], $overrides));
    }
}
