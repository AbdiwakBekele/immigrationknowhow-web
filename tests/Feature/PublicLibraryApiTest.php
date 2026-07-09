<?php

namespace Tests\Feature;

use App\Models\LibraryItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicLibraryApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createItem(array $overrides = []): LibraryItem
    {
        return LibraryItem::query()->create(array_merge([
            'title' => 'Test Book',
            'slug' => 'test-book',
            'type' => 'ebook',
            'file_path' => 'library/files/test.pdf',
            'file_name' => 'test.pdf',
            'file_size' => 1,
            'file_type' => 'pdf',
            'price' => 0,
            'currency' => 'USD',
            'is_premium' => false,
            'is_active' => true,
            'is_featured' => false,
        ], $overrides));
    }

    public function test_public_library_items_can_filter_to_featured_only(): void
    {
        $this->createItem([
            'title' => 'Featured Guide',
            'slug' => 'featured-guide',
            'is_featured' => true,
        ]);

        $this->createItem([
            'title' => 'Regular Guide',
            'slug' => 'regular-guide',
            'is_featured' => false,
        ]);

        $response = $this->getJson(route('api.public.library-items', [
            'featured' => 1,
            'per_page' => 10,
        ]));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'featured-guide')
            ->assertJsonPath('data.0.title', 'Featured Guide');
    }

    public function test_public_library_item_show_includes_ai_summary_for_ebooks(): void
    {
        $this->createItem([
            'title' => 'Summarized Guide',
            'slug' => 'summarized-guide',
            'ai_summary' => '## Quick take\n\nThis is the AI summary.',
            'ai_summary_status' => 'success',
        ]);

        $this->getJson(route('api.public.library-items.show', 'summarized-guide'))
            ->assertOk()
            ->assertJsonPath('item.slug', 'summarized-guide')
            ->assertJsonPath('item.ai_summary', '## Quick take\n\nThis is the AI summary.');
    }
}
