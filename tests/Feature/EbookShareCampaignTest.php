<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\EbookCoupon;
use App\Models\EbookShareCampaign;
use App\Models\EbookShareEvent;
use App\Models\LibraryItem;
use App\Models\User;
use App\Support\RoleHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EbookShareCampaignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RoleHelper::ensureExists(UserRole::USER->value);
    }

    public function test_user_can_record_five_distinct_ebook_shares_and_receive_coupon(): void
    {
        Notification::fake();

        $user = $this->createSeeker('share-campaign@example.com');
        $visitor = $this->createSeeker('share-visitor@example.com');
        Sanctum::actingAs($user);

        for ($i = 1; $i <= 5; $i++) {
            $item = $this->createEbook(['slug' => "share-book-{$i}", 'title' => "Share Book {$i}"]);

            $intentResponse = $this->postJson("/api/mobile/library/items/{$item->slug}/share/intent", [
                'platform' => $i % 2 === 0 ? 'x' : 'facebook',
            ])->assertOk()->assertJsonPath('success', true);

            $shareUrl = (string) $intentResponse->json('data.share_url');
            $token = basename(parse_url($shareUrl, PHP_URL_PATH) ?: '');

            $this->assertNotSame('', $token);

            $this->actingAs($visitor);
            $this->get("/s/{$token}")
                ->assertRedirect(route('library.show', $item));

            Sanctum::actingAs($user);
        }

        $campaign = EbookShareCampaign::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($campaign);
        $this->assertSame(EbookShareCampaign::STATUS_REWARDED, $campaign->status);
        $this->assertSame(5, EbookShareEvent::query()->where('status', EbookShareEvent::STATUS_CONFIRMED)->count());

        $coupon = EbookCoupon::query()
            ->where('user_id', $user->id)
            ->where('issued_for', EbookCoupon::ISSUED_FOR_SOCIAL_SHARE)
            ->first();

        $this->assertNotNull($coupon);
        $this->assertSame($coupon->id, $campaign->ebook_coupon_id);
    }

    public function test_share_intent_does_not_confirm_before_link_visit(): void
    {
        $user = $this->createSeeker('share-pending@example.com');
        Sanctum::actingAs($user);

        $item = $this->createEbook(['slug' => 'pending-share-book']);

        $intentResponse = $this->postJson("/api/mobile/library/items/{$item->slug}/share/intent", [
            'platform' => 'facebook',
        ])->assertOk();

        $this->assertSame(EbookShareEvent::STATUS_PENDING, $intentResponse->json('data.event.status'));

        $event = EbookShareEvent::query()->where('library_item_id', $item->id)->first();
        $this->assertNotNull($event);
        $this->assertSame(EbookShareEvent::STATUS_PENDING, $event->status);
    }

    public function test_same_book_cannot_be_shared_twice_in_campaign(): void
    {
        $user = $this->createSeeker('share-once@example.com');
        Sanctum::actingAs($user);

        $item = $this->createEbook(['slug' => 'single-share-book']);

        $this->postJson("/api/mobile/library/items/{$item->slug}/share/intent", [
            'platform' => 'facebook',
        ])->assertOk();

        $this->postJson("/api/mobile/library/items/{$item->slug}/share/intent", [
            'platform' => 'x',
        ])->assertOk();

        $this->assertSame(1, EbookShareEvent::query()->where('library_item_id', $item->id)->count());
    }

    public function test_share_landing_redirects_to_library_item(): void
    {
        $user = $this->createSeeker('share-landing@example.com');
        $item = $this->createEbook(['slug' => 'landing-book']);

        $event = EbookShareEvent::query()->create([
            'ebook_share_campaign_id' => EbookShareCampaign::query()->create([
                'user_id' => $user->id,
                'status' => EbookShareCampaign::STATUS_IN_PROGRESS,
                'required_shares' => 5,
            ])->id,
            'user_id' => $user->id,
            'library_item_id' => $item->id,
            'share_token' => 'testsharetoken1234567890123456789012',
            'status' => EbookShareEvent::STATUS_PENDING,
            'intent_at' => now(),
        ]);

        $this->get("/s/{$event->share_token}")
            ->assertRedirect(route('library.show', $item));
    }

    public function test_share_campaign_status_endpoint_returns_progress(): void
    {
        $user = $this->createSeeker('share-status@example.com');
        Sanctum::actingAs($user);

        $this->getJson('/api/mobile/library/share-campaign')
            ->assertOk()
            ->assertJsonPath('data.required_shares', 5)
            ->assertJsonPath('data.confirmed_shares', 0)
            ->assertJsonPath('data.can_start', true);
    }

    private function createSeeker(string $email): User
    {
        $user = User::create([
            'first_name' => 'Seeker',
            'last_name' => 'User',
            'email' => $email,
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $user->assignRole(UserRole::USER->value);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createEbook(array $overrides = []): LibraryItem
    {
        return LibraryItem::query()->create(array_merge([
            'title' => 'Shareable Guide',
            'slug' => 'shareable-guide',
            'type' => 'ebook',
            'file_path' => 'library/files/share.pdf',
            'file_name' => 'share.pdf',
            'file_size' => 1,
            'file_type' => 'pdf',
            'price' => 5.99,
            'currency' => 'USD',
            'is_premium' => false,
            'is_active' => true,
            'is_featured' => false,
        ], $overrides));
    }
}
