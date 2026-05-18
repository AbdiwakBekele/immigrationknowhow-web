<?php

namespace Tests\Unit;

use App\Services\CommunityImport\CsvReader;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class CommunityImportCsvReaderTest extends TestCase
{
    #[Test]
    public function test_production_posts_csv_parses_620_records_with_expected_ids(): void
    {
        $path = storage_path('app/imports/community/11/community_posts_import.csv');

        if (! is_file($path)) {
            $this->markTestSkipped('Batch 11 posts fixture is not available.');
        }

        $reader = app(CsvReader::class);
        $rows = $reader->all($path, CsvReader::FILE_POSTS);
        $ids = array_column(array_column($rows, 'data'), 'old_wp_post_id');

        $this->assertCount(620, $rows);
        $this->assertSame(['21', '22', '23'], array_slice($ids, 0, 3));
        $this->assertSame(['663', '664', '665', '666', '667'], array_slice($ids, -5));
        $this->assertContains('631', $ids);
        $this->assertContains('667', $ids);
    }

    #[Test]
    public function test_post_395_parses_category_and_image_url_inside_quoted_trailing_fields(): void
    {
        $path = storage_path('app/imports/community/11/community_posts_import.csv');

        if (! is_file($path)) {
            $this->markTestSkipped('Batch 11 posts fixture is not available.');
        }

        $reader = app(CsvReader::class);
        $rows = $reader->all($path, CsvReader::FILE_POSTS);
        $post395 = collect($rows)->first(
            fn (array $row) => ($row['data']['old_wp_post_id'] ?? '') === '395'
        );

        $this->assertNotNull($post395);
        $this->assertSame('daily-living', $post395['data']['category']);
        $this->assertSame('Housing Assistance', $post395['data']['tag']);
        $this->assertSame('2', $post395['data']['old_wp_space_id']);
        $this->assertStringContainsString('forbes.com/specials-images', $post395['data']['image_url']);
        $this->assertStringNotContainsString('crop=1281,1280,x0,y0', $post395['data']['category']);
    }

}
