<?php

namespace Tests\Unit;

use App\Models\ProviderProfilePost;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProviderProfilePostTest extends TestCase
{
    public static function videoParseCases(): array
    {
        return [
            'youtube watch' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'youtube', 'dQw4w9WgXcQ'],
            'youtube short' => ['https://youtu.be/dQw4w9WgXcQ', 'youtube', 'dQw4w9WgXcQ'],
            'vimeo' => ['https://vimeo.com/123456789', 'vimeo', '123456789'],
            'tiktok' => ['https://www.tiktok.com/@someone/video/1234567890123456789', 'tiktok', '1234567890123456789'],
            'instagram reel' => ['https://www.instagram.com/reel/AbCd123/', 'instagram', 'reel/AbCd123'],
            'instagram post' => ['https://www.instagram.com/p/AbCd123/', 'instagram', 'p/AbCd123'],
        ];
    }

    #[DataProvider('videoParseCases')]
    public function test_apply_parsed_video_metadata_detects_platform(string $url, string $platform, string $videoId): void
    {
        $post = new ProviderProfilePost([
            'type' => ProviderProfilePost::TYPE_VIDEO,
            'url' => $url,
        ]);
        $post->applyParsedVideoMetadata();

        $this->assertSame($platform, $post->platform);
        $this->assertSame($videoId, $post->video_id);
    }

    public function test_feed_payload_includes_link_hostname_for_articles(): void
    {
        $post = new ProviderProfilePost([
            'type' => ProviderProfilePost::TYPE_ARTICLE,
            'url' => 'https://www.example.com/story?x=1',
            'title' => 'Hello',
        ]);

        $payload = $post->toFeedPayload();

        $this->assertSame('example.com', $payload['link_hostname']);
    }

    public function test_instagram_embed_src(): void
    {
        $post = new ProviderProfilePost([
            'type' => ProviderProfilePost::TYPE_VIDEO,
            'url' => 'https://www.instagram.com/reel/XYZ/',
        ]);
        $post->applyParsedVideoMetadata();

        $this->assertTrue($post->canEmbedVideo());
        $this->assertSame('https://www.instagram.com/reel/XYZ/embed/', $post->embedSrc());
    }
}
