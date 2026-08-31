<?php

namespace Tests\Feature;

use App\Models\LibraryItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LibraryCoverTest extends TestCase
{
    use RefreshDatabase;

    public function test_library_cover_streams_from_configured_disk(): void
    {
        Storage::fake('s3');

        $coverPath = 'library/covers/sample-cover.webp';
        Storage::disk('s3')->put($coverPath, 'fake-webp-bytes');

        $item = LibraryItem::query()->create([
            'title' => 'Cover Test Guide',
            'slug' => 'cover-test-guide',
            'type' => 'ebook',
            'description' => 'Cover streaming test.',
            'cover_image' => $coverPath,
            'file_path' => 'library/files/sample.pdf',
            'file_name' => 'sample.pdf',
            'file_size' => 15,
            'file_type' => 'pdf',
            'is_premium' => false,
            'price' => 0,
            'currency' => 'USD',
            'is_active' => true,
        ]);

        $this->get(route('library.cover', $item))
            ->assertOk()
            ->assertHeader('content-type', 'image/webp');
    }

    public function test_cover_image_url_uses_library_cover_route(): void
    {
        Storage::fake('s3');

        $coverPath = 'library/covers/sample-cover.png';
        Storage::disk('s3')->put($coverPath, 'fake-png-bytes');

        $item = LibraryItem::query()->create([
            'title' => 'Cover URL Guide',
            'slug' => 'cover-url-guide',
            'type' => 'ebook',
            'description' => 'Cover URL test.',
            'cover_image' => $coverPath,
            'file_path' => 'library/files/sample.pdf',
            'file_name' => 'sample.pdf',
            'file_size' => 15,
            'file_type' => 'pdf',
            'is_premium' => false,
            'price' => 0,
            'currency' => 'USD',
            'is_active' => true,
        ]);

        $this->assertSame(route('library.cover', $item), $item->cover_image_url);
    }

    public function test_inactive_library_cover_returns_not_found(): void
    {
        Storage::fake('s3');

        $coverPath = 'library/covers/inactive-cover.webp';
        Storage::disk('s3')->put($coverPath, 'fake-webp-bytes');

        $item = LibraryItem::query()->create([
            'title' => 'Inactive Cover Guide',
            'slug' => 'inactive-cover-guide',
            'type' => 'ebook',
            'description' => 'Inactive cover test.',
            'cover_image' => $coverPath,
            'file_path' => 'library/files/sample.pdf',
            'file_name' => 'sample.pdf',
            'file_size' => 15,
            'file_type' => 'pdf',
            'is_premium' => false,
            'price' => 0,
            'currency' => 'USD',
            'is_active' => false,
        ]);

        $this->get(route('library.cover', $item))->assertNotFound();
    }
}
