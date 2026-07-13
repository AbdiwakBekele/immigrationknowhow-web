<?php

namespace Tests\Feature;

use App\Actions\Library\QueueLibraryEbookSummary;
use App\Jobs\GenerateLibraryEbookSummary;
use App\Models\LibraryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QueueLibraryEbookSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_page_queues_summary_when_missing(): void
    {
        Bus::fake();
        config(['services.openai.api_key' => 'test-openai-key']);

        $user = $this->createUser();
        $item = $this->createLibraryItem();

        $this->actingAs($user)
            ->get(route('library.show', $item))
            ->assertOk();

        Bus::assertDispatched(GenerateLibraryEbookSummary::class);

        $this->assertSame('queued', $item->fresh()->ai_summary_status);
    }

    public function test_summary_endpoint_queues_summary_when_missing(): void
    {
        Bus::fake();
        config(['services.openai.api_key' => 'test-openai-key']);

        $user = $this->createUser();
        $item = $this->createLibraryItem();

        $this->actingAs($user)
            ->postJson(route('library.summary', $item))
            ->assertOk()
            ->assertJsonPath('summary', null)
            ->assertJsonPath('message', 'Summary is being generated. Please check back shortly.');

        Bus::assertDispatched(GenerateLibraryEbookSummary::class);
    }

    public function test_queue_action_skips_when_summary_already_exists(): void
    {
        Bus::fake();

        $item = $this->createLibraryItem([
            'ai_summary' => 'Existing summary text.',
            'ai_summary_status' => 'success',
        ]);

        $result = app(QueueLibraryEbookSummary::class)($item);

        $this->assertSame('already_exists', $result);
        Bus::assertNothingDispatched();
    }

    public function test_public_library_show_queues_summary_when_missing(): void
    {
        Bus::fake();
        config(['services.openai.api_key' => 'test-openai-key']);

        $item = $this->createLibraryItem([
            'slug' => 'public-summary-book',
        ]);

        $this->getJson(route('api.public.library-items.show', $item->slug))
            ->assertOk()
            ->assertJsonPath('item.slug', 'public-summary-book')
            ->assertJsonPath('item.ai_summary', null);

        Bus::assertDispatched(GenerateLibraryEbookSummary::class);
    }

    private function createUser(array $overrides = []): User
    {
        return User::query()->create(array_merge([
            'first_name' => 'Library',
            'last_name' => 'Reader',
            'email' => 'queue-summary-'.uniqid().'@example.com',
            'password' => 'Password123!',
            'onboarding_completed' => true,
            'onboarding_completed_at' => now(),
            'is_active' => true,
        ], $overrides));
    }

    private function createLibraryItem(array $overrides = []): LibraryItem
    {
        Storage::fake(LibraryItem::LIBRARY_MEDIA_DISK);

        $path = $overrides['file_path'] ?? 'library/files/queue-summary.pdf';
        Storage::disk(LibraryItem::LIBRARY_MEDIA_DISK)->put($path, '%PDF-1.4 sample');

        return LibraryItem::query()->create(array_merge([
            'title' => 'Queue Summary Book',
            'slug' => 'queue-summary-book-'.uniqid(),
            'type' => 'ebook',
            'description' => 'Queue summary source book.',
            'file_path' => $path,
            'file_name' => 'queue-summary.pdf',
            'file_size' => 15,
            'file_type' => 'pdf',
            'is_premium' => false,
            'price' => 0,
            'currency' => 'USD',
            'is_active' => true,
        ], $overrides));
    }
}
