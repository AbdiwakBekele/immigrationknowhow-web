<?php

namespace Tests\Feature;

use App\Models\LibraryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LibraryCheckoutRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_buy_link_stores_payment_screen_as_intended_url(): void
    {
        $item = $this->createPremiumLibraryItem();

        $this->get(route('library.pay', $item))
            ->assertRedirect(route('login'))
            ->assertSessionHas('url.intended', route('library.pay', $item));
    }

    public function test_login_with_intended_library_pay_redirects_to_onboarding_step_two_when_phone_unverified(): void
    {
        $item = $this->createPremiumLibraryItem();
        $user = $this->createUser([
            'onboarding_completed' => false,
            'onboarding_completed_at' => null,
        ]);

        $this->withSession(['url.intended' => route('library.pay', $item)])
            ->post(route('login'), [
                'email' => $user->email,
                'password' => 'Password123!',
                'remember' => false,
            ])
            ->assertRedirect(route('onboarding.index'))
            ->assertSessionHas('url.intended', route('library.pay', $item));
    }

    public function test_registration_with_intended_library_pay_redirects_to_onboarding_step_two(): void
    {
        $item = $this->createPremiumLibraryItem();

        $this->withSession(['url.intended' => route('library.pay', $item)])
            ->post(route('register'), [
                'first_name' => 'New',
                'last_name' => 'Reader',
                'email' => 'new-reader@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'role' => 'user',
            ])
            ->assertRedirect(route('onboarding.index', ['step' => 2]))
            ->assertSessionHas('url.intended', route('library.pay', $item));

        $this->assertAuthenticated();
    }

    private function createUser(array $overrides = []): User
    {
        return User::query()->create(array_merge([
            'first_name' => 'Library',
            'last_name' => 'Buyer',
            'email' => 'library-buyer-'.uniqid().'@example.com',
            'password' => 'Password123!',
            'onboarding_completed' => true,
            'onboarding_completed_at' => now(),
            'is_active' => true,
        ], $overrides));
    }

    private function createPremiumLibraryItem(array $overrides = []): LibraryItem
    {
        return LibraryItem::query()->create(array_merge([
            'title' => 'Paid Immigration Guide '.uniqid(),
            'slug' => 'paid-immigration-guide-'.uniqid(),
            'type' => 'ebook',
            'description' => 'A paid practical immigration guide.',
            'file_path' => 'library/files/paid-guide.pdf',
            'file_name' => 'paid-guide.pdf',
            'file_size' => 1024,
            'file_type' => 'pdf',
            'is_premium' => true,
            'price' => 9.99,
            'currency' => 'USD',
            'is_active' => true,
        ], $overrides));
    }
}
