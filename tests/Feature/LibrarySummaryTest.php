<?php

namespace Tests\Feature;

use App\Models\LibraryItem;
use App\Models\LibraryUserAccess;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LibrarySummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_available_without_purchased_access(): void
    {
        Storage::fake(LibraryItem::LIBRARY_MEDIA_DISK);

        $user = $this->createUser();
        $item = $this->createLibraryItem();

        $item->forceFill([
            'ai_summary' => 'Preview summary text.',
            'ai_summary_generated_at' => now(),
            'ai_summary_status' => 'success',
        ])->save();

        $this->actingAs($user)
            ->postJson(route('library.summary', $item))
            ->assertOk()
            ->assertJsonPath('summary', 'Preview summary text.');
    }

    public function test_summary_endpoint_returns_cached_summary_for_purchased_ebooks(): void
    {
        Storage::fake(LibraryItem::LIBRARY_MEDIA_DISK);

        $user = $this->createUser();
        $item = $this->createLibraryItem();

        LibraryUserAccess::query()->create([
            'user_id' => $user->id,
            'library_item_id' => $item->id,
            'purchased_at' => now(),
        ]);

        $item->forceFill([
            'ai_summary' => "## Quick Summary\n\nStored summary from admin upload.",
            'ai_summary_generated_at' => now(),
            'ai_summary_status' => 'success',
        ])->save();

        $response = $this->actingAs($user)
            ->postJson(route('library.summary', $item));

        $response->assertOk()
            ->assertJsonPath('summary', "## Quick Summary\n\nStored summary from admin upload.");

        $this->assertNotNull($item->fresh()->ai_summary_generated_at);
    }

    private function createUser(array $overrides = []): User
    {
        return User::query()->create(array_merge([
            'first_name' => 'Library',
            'last_name' => 'Reader',
            'email' => 'library-summary-'.uniqid().'@example.com',
            'password' => 'Password123!',
            'onboarding_completed' => true,
            'onboarding_completed_at' => now(),
            'is_active' => true,
        ], $overrides));
    }

    private function createLibraryItem(array $overrides = []): LibraryItem
    {
        $path = $overrides['file_path'] ?? 'library/files/summary-source.pdf';
        Storage::disk(LibraryItem::LIBRARY_MEDIA_DISK)->put($path, '%PDF-1.4 sample');

        return LibraryItem::query()->create(array_merge([
            'title' => 'Summary Book',
            'slug' => 'summary-book',
            'type' => 'ebook',
            'description' => 'Summary source book.',
            'file_path' => $path,
            'file_name' => 'summary-book.pdf',
            'file_size' => 15,
            'file_type' => 'pdf',
            'is_premium' => true,
            'price' => 25,
            'currency' => 'USD',
            'is_active' => true,
        ], $overrides));
    }
}

