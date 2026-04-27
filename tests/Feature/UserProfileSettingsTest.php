<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Lead;
use App\Models\LibraryItem;
use App\Models\LibraryUserAccess;
use App\Models\Message;
use App\Models\ServiceProvider;
use App\Models\User;
use App\Notifications\RecommendedProviderInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserProfileSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upload_and_remove_profile_photo(): void
    {
        Storage::fake('public');
        $user = $this->createCompletedUser();

        $this->actingAs($user)
            ->post(route('profile.avatar'), [
                'avatar' => UploadedFile::fake()->image('avatar.jpg')->size(512),
            ])
            ->assertRedirect();

        $user->refresh();
        $this->assertNotNull($user->avatar);
        Storage::disk('public')->assertExists($user->avatar);

        $path = $user->avatar;

        $this->actingAs($user)
            ->delete(route('profile.avatar.delete'))
            ->assertRedirect();

        $user->refresh();
        $this->assertNull($user->avatar);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_user_profile_saves_languages_and_profile_details(): void
    {
        $user = $this->createCompletedUser();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'first_name' => 'Jacky',
                'last_name' => 'Norton',
                'email' => 'jacky@example.com',
                'phone' => '555-111-2222',
                'city' => 'Livonia',
                'state' => 'MI',
                'country' => 'US',
                'preferred_language' => 'en',
                'languages_spoken' => ['en', 'es'],
                'country_of_origin' => 'CA',
                'has_children' => true,
                'children_ages' => [4, 9],
                'has_pets' => true,
                'pet_types' => ['Dog'],
                'social_links' => [
                    ['label' => 'LinkedIn', 'url' => 'https://example.com/jacky'],
                ],
                'hobbies' => ['Cooking', 'Soccer'],
            ])
            ->assertRedirect();

        $user->refresh();

        $this->assertSame('Jacky', $user->first_name);
        $this->assertSame(['en', 'es'], $user->languages);
        $this->assertSame('CA', data_get($user->onboarding_data, 'profile.country_of_origin'));
        $this->assertSame([4, 9], data_get($user->onboarding_data, 'profile.children_ages'));
        $this->assertSame(['Dog'], data_get($user->onboarding_data, 'profile.pet_types'));
        $this->assertSame('https://example.com/jacky', data_get($user->onboarding_data, 'profile.social_links.0.url'));
        $this->assertSame(['Cooking', 'Soccer'], data_get($user->onboarding_data, 'profile.hobbies'));
    }

    public function test_user_notification_preferences_are_saved_in_onboarding_data(): void
    {
        $user = $this->createCompletedUser();

        $this->actingAs($user)
            ->patch(route('profile.notifications'), [
                'notification_preferences' => [
                    'email_new_message' => true,
                    'email_lead_update' => false,
                    'email_review_received' => true,
                    'email_marketing' => true,
                    'push_enabled' => false,
                ],
            ])
            ->assertRedirect();

        $user->refresh();

        $this->assertFalse(data_get($user->onboarding_data, 'notification_preferences.email_lead_update'));
        $this->assertTrue(data_get($user->onboarding_data, 'notification_preferences.email_marketing'));
        $this->assertFalse(data_get($user->onboarding_data, 'notification_preferences.push_enabled'));
    }

    public function test_user_can_invite_a_recommended_provider_from_profile(): void
    {
        Notification::fake();
        $user = $this->createCompletedUser();

        $this->actingAs($user)
            ->post(route('profile.provider-invites.store'), [
                'name' => 'Trusted Care LLC',
                'email' => 'care@example.com',
                'phone' => '555-444-3333',
                'service_type' => 'pet_sitter',
                'website' => 'https://care.example.com',
                'note' => 'Great with pets and families.',
            ])
            ->assertRedirect();

        $user->refresh();

        $this->assertSame('Trusted Care LLC', data_get($user->onboarding_data, 'profile.provider_invites.0.name'));
        $this->assertSame('invited', data_get($user->onboarding_data, 'profile.provider_invites.0.status'));

        Notification::assertSentOnDemand(RecommendedProviderInvitationNotification::class, function ($notification, $channels, $notifiable) {
            return ($notifiable->routes['mail'] ?? null) === 'care@example.com';
        });
    }

    public function test_profile_page_includes_messages_purchases_and_matched_providers(): void
    {
        Storage::fake(LibraryItem::LIBRARY_MEDIA_DISK);

        $user = $this->createCompletedUser([
            'city' => 'Livonia',
            'state' => 'MI',
            'country' => 'US',
            'languages' => ['en'],
            'onboarding_data' => [
                'services' => ['services_needed' => ['babysitter']],
                'profile' => [
                    'has_children' => true,
                    'children_ages' => [5],
                    'has_pets' => true,
                    'pet_types' => ['Dog'],
                ],
            ],
        ]);
        $providerUser = $this->createCompletedUser([
            'first_name' => 'Provider',
            'last_name' => 'Match',
            'email' => 'provider-match-'.uniqid().'@example.com',
            'city' => 'Livonia',
            'state' => 'MI',
            'country' => 'US',
            'languages' => ['en'],
        ]);
        $provider = ServiceProvider::query()->create([
            'user_id' => $providerUser->id,
            'business_name' => 'Family And Pet Care',
            'slug' => 'family-and-pet-care',
            'service_types' => ['babysitter', 'pet_sitter'],
            'languages_offered' => ['en'],
            'serves_remote' => true,
            'serves_in_person' => true,
            'verification_status' => 'approved',
            'is_active' => true,
            'accepting_clients' => true,
            'average_rating' => 4.8,
            'total_reviews' => 3,
        ]);
        $lead = Lead::query()->create([
            'user_id' => $user->id,
            'service_provider_id' => $provider->id,
            'service_type' => 'babysitter',
            'status' => 'new',
            'message' => 'Need family support.',
        ]);
        $conversation = Conversation::query()->create([
            'lead_id' => $lead->id,
            'user_id' => $user->id,
            'service_provider_id' => $provider->id,
            'subject' => 'Family support',
            'last_message_at' => now(),
        ]);
        Message::query()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $providerUser->id,
            'body' => 'Happy to help with child care.',
        ]);

        $item = $this->createLibraryItem();
        LibraryUserAccess::query()->create([
            'user_id' => $user->id,
            'library_item_id' => $item->id,
            'purchased_at' => now(),
            'progress' => [
                'reading' => ['percentage' => 42],
                'audio' => ['percentage' => 10],
            ],
        ]);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('User/Profile/Edit')
                ->has('recentMessages', 1)
                ->has('purchasedProducts', 1)
                ->has('matchedProviders', 1)
            );
    }

    public function test_onboarding_saves_family_pet_and_service_preferences_for_profile_matching(): void
    {
        $user = User::query()->create([
            'first_name' => 'Onboarding',
            'last_name' => 'User',
            'email' => 'onboarding-user-'.uniqid().'@example.com',
            'password' => 'Password123!',
            'phone_verified_at' => now(),
            'onboarding_completed' => false,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('onboarding.complete'), [
                'services_needed' => ['pet_sitter'],
                'city' => 'Livonia',
                'state' => 'MI',
                'country' => 'US',
                'preferred_language' => 'en',
                'languages' => ['en'],
                'profile' => [
                    'has_children' => true,
                    'children_ages' => [3],
                    'has_pets' => true,
                    'pet_types' => ['Dog'],
                ],
            ])
            ->assertRedirect(route('dashboard'));

        $user->refresh();

        $this->assertTrue($user->onboarding_completed);
        $this->assertSame(['pet_sitter'], data_get($user->onboarding_data, 'services.services_needed'));
        $this->assertSame([3], data_get($user->onboarding_data, 'profile.children_ages'));
        $this->assertSame(['Dog'], data_get($user->onboarding_data, 'profile.pet_types'));
    }

    private function createCompletedUser(array $overrides = []): User
    {
        return User::query()->create(array_merge([
            'first_name' => 'Profile',
            'last_name' => 'User',
            'email' => 'profile-user-'.uniqid().'@example.com',
            'password' => 'Password123!',
            'onboarding_completed' => true,
            'is_active' => true,
        ], $overrides));
    }

    private function createLibraryItem(array $overrides = []): LibraryItem
    {
        $path = $overrides['file_path'] ?? 'library/files/profile-sample.pdf';
        Storage::disk(LibraryItem::LIBRARY_MEDIA_DISK)->put($path, '%PDF-1.4 profile sample');

        return LibraryItem::query()->create(array_merge([
            'title' => 'Profile Sample Guide',
            'slug' => 'profile-sample-guide',
            'type' => 'ebook',
            'description' => 'A practical guide.',
            'file_path' => $path,
            'file_name' => 'profile-sample-guide.pdf',
            'file_size' => 20,
            'file_type' => 'pdf',
            'is_premium' => true,
            'price' => 12,
            'currency' => 'USD',
            'is_active' => true,
            'audio_file_path' => 'library/files/profile-sample.mp3',
        ], $overrides));
    }
}
