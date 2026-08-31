<?php

namespace Tests\Feature;

use App\Models\CommunityPost;
use App\Models\LibraryItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSocialPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_community_post_page_includes_open_graph_meta_tags(): void
    {
        $post = CommunityPost::query()->create([
            'title' => 'Visa Tips for Newcomers',
            'description' => '<p>Practical advice for your first month.</p>',
            'tag' => 'visa',
            'image_url' => 'https://cdn.example.com/community/visa-tips.jpg',
            'category' => 'feed',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $response = $this->get(route('community.post-page', $post));

        $response->assertOk();
        $response->assertSee('property="og:title" content="Visa Tips for Newcomers"', false);
        $response->assertSee('property="og:image" content="https://cdn.example.com/community/visa-tips.jpg"', false);
        $response->assertSee('name="twitter:card" content="summary_large_image"', false);
    }

    public function test_library_item_page_includes_open_graph_meta_tags(): void
    {
        $item = LibraryItem::query()->create([
            'title' => 'Settlement Guide',
            'slug' => 'settlement-guide',
            'type' => 'ebook',
            'description' => 'A helpful settlement guide.',
            'cover_image' => 'https://cdn.example.com/library/settlement.jpg',
            'file_path' => 'library/files/settlement.pdf',
            'file_name' => 'settlement.pdf',
            'file_size' => 1,
            'file_type' => 'pdf',
            'price' => 5.99,
            'currency' => 'USD',
            'is_premium' => false,
            'is_active' => true,
            'is_featured' => false,
        ]);

        $response = $this->get(route('library.show', $item));

        $response->assertOk();
        $response->assertSee('property="og:title" content="Settlement Guide"', false);
        $response->assertSee('property="og:image" content="https://cdn.example.com/library/settlement.jpg"', false);
    }
}
