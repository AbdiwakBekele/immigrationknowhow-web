<?php

namespace Tests\Feature;

use App\Models\CommunityPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCommunityApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createPost(array $overrides = []): CommunityPost
    {
        return CommunityPost::query()->create(array_merge([
            'title' => 'Test post',
            'description' => 'Test description',
            'tag' => 'general',
            'category' => 'feed',
            'is_published' => true,
        ], $overrides));
    }

    public function test_public_community_posts_endpoint_returns_published_posts(): void
    {
        $published = $this->createPost([
            'title' => 'Published story',
        ]);

        $draft = $this->createPost([
            'title' => 'Draft story',
            'is_published' => false,
        ]);

        $response = $this->getJson(route('api.public.community.posts', [
            'category' => 'feed',
            'per_page' => 10,
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('posts.data.0.id', $published->id)
            ->assertJsonPath('posts.data.0.title', 'Published story')
            ->assertJsonStructure([
                'posts' => [
                    'data' => [
                        [
                            'id',
                            'title',
                            'description',
                            'category',
                            'image_url',
                            'video_url',
                            'likes_count',
                            'comments_count',
                        ],
                    ],
                ],
            ]);

        $ids = collect($response->json('posts.data'))->pluck('id')->all();
        $this->assertContains($published->id, $ids);
        $this->assertNotContains($draft->id, $ids);
    }

    public function test_public_community_show_and_comments_endpoints(): void
    {
        $post = $this->createPost([
            'category' => 'immigration-legal',
        ]);

        $this->getJson(route('api.public.community.posts.show', $post))
            ->assertOk()
            ->assertJsonPath('post.id', $post->id);

        $this->getJson(route('api.public.community.posts.comments', $post))
            ->assertOk()
            ->assertJsonStructure(['comments']);
    }

    public function test_public_community_news_endpoint(): void
    {
        $this->getJson(route('api.public.community.news', ['country' => 'US', 'limit' => 3]))
            ->assertOk()
            ->assertJsonStructure(['country', 'items']);
    }
}
