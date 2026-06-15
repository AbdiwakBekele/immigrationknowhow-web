<?php

namespace Tests\Feature;

use App\Actions\Library\IssueSignupEbookCoupon;
use App\Enums\UserRole;
use App\Models\EbookCoupon;
use App\Models\LibraryItem;
use App\Models\User;
use App\Support\RoleHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EbookCouponTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RoleHelper::ensureExists(UserRole::USER->value);
    }

    public function test_signup_issues_one_coupon_per_user(): void
    {
        Notification::fake();

        $user = User::create([
            'first_name' => 'Coupon',
            'last_name' => 'Tester',
            'email' => 'coupon-tester@example.com',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $user->assignRole(UserRole::USER->value);

        $first = app(IssueSignupEbookCoupon::class)($user, UserRole::USER->value);
        $second = app(IssueSignupEbookCoupon::class)($user, UserRole::USER->value);

        $this->assertNotNull($first);
        $this->assertSame($first?->id, $second?->id);
        $this->assertSame(1, EbookCoupon::query()->where('user_id', $user->id)->count());
    }

    public function test_user_can_redeem_signup_coupon_for_paid_title(): void
    {
        $user = $this->createSeeker('redeem-coupon@example.com');
        $coupon = EbookCoupon::query()->create([
            'user_id' => $user->id,
            'code' => 'IKH-TEST-CODE',
            'issued_for' => EbookCoupon::ISSUED_FOR_SIGNUP,
        ]);

        $item = $this->createPaidItem();

        Sanctum::actingAs($user);

        $this->postJson("/api/mobile/library/items/{$item->slug}/redeem-coupon")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.has_access', true);

        $coupon->refresh();
        $this->assertNotNull($coupon->redeemed_at);
        $this->assertSame($item->id, $coupon->library_item_id);

        $this->assertDatabaseHas('library_user_access', [
            'user_id' => $user->id,
            'library_item_id' => $item->id,
            'purchase_source' => 'coupon',
        ]);
    }

    public function test_library_show_exposes_coupon_availability(): void
    {
        $user = $this->createSeeker('coupon-show@example.com');
        EbookCoupon::query()->create([
            'user_id' => $user->id,
            'code' => 'IKH-SHOW-CODE',
            'issued_for' => EbookCoupon::ISSUED_FOR_SIGNUP,
        ]);

        $item = $this->createPaidItem(['slug' => 'coupon-show-book']);

        Sanctum::actingAs($user);

        $this->getJson("/api/mobile/library/items/{$item->slug}")
            ->assertOk()
            ->assertJsonPath('data.ebook_coupon_available', true)
            ->assertJsonPath('data.uses_ebook_credit_iap', true);
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
    private function createPaidItem(array $overrides = []): LibraryItem
    {
        config([
            'library.standard_price_cents' => 499,
            'services.apple_iap.library_ebook_product_id' => 'com.test.ebook_credit',
        ]);

        return LibraryItem::query()->create(array_merge([
            'title' => 'Paid Guide',
            'slug' => 'paid-guide',
            'type' => 'ebook',
            'file_path' => 'library/files/paid.pdf',
            'file_name' => 'paid.pdf',
            'file_size' => 1,
            'file_type' => 'pdf',
            'price' => 4.99,
            'currency' => 'USD',
            'is_premium' => false,
            'is_active' => true,
            'is_featured' => false,
        ], $overrides));
    }
}
