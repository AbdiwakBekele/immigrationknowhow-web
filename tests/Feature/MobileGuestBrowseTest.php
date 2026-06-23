<?php

namespace Tests\Feature;

use App\Models\LibraryCategory;
use App\Models\LibraryItem;
use App\Models\ServiceProvider;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileGuestBrowseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_guest_meta_is_public(): void
    {
        $this->getJson('/api/mobile/guest/meta')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'service_types',
                    'language_options',
                    'library_categories',
                    'library_regions',
                ],
            ]);
    }

    public function test_guest_library_returns_limited_fields(): void
    {
        $category = LibraryCategory::query()->create([
            'name' => 'Guides',
            'slug' => 'guides',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        LibraryItem::query()->create([
            'title' => 'Guest Preview Book',
            'slug' => 'guest-preview-book',
            'type' => 'ebook',
            'file_path' => 'library/files/guest.pdf',
            'file_name' => 'guest.pdf',
            'file_size' => 1,
            'file_type' => 'pdf',
            'price' => 9.99,
            'currency' => 'USD',
            'is_premium' => true,
            'is_active' => true,
            'is_featured' => false,
            'category_id' => $category->id,
            'regions' => ['usa'],
            'description' => 'Secret full description',
        ]);

        $this->getJson('/api/mobile/guest/library')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.items.data.0.slug', 'guest-preview-book')
            ->assertJsonPath('data.items.data.0.title', 'Guest Preview Book')
            ->assertJsonPath('data.items.data.0.category.name', 'Guides')
            ->assertJsonPath('data.items.data.0.countries.0', 'USA')
            ->assertJsonMissingPath('data.items.data.0.description')
            ->assertJsonMissingPath('data.items.data.0.author')
            ->assertJsonMissingPath('data.items.data.0.price');
    }

    public function test_guest_library_show_requires_auth_flag(): void
    {
        LibraryItem::query()->create([
            'title' => 'Locked Book',
            'slug' => 'locked-book',
            'type' => 'ebook',
            'file_path' => 'library/files/locked.pdf',
            'file_name' => 'locked.pdf',
            'file_size' => 1,
            'file_type' => 'pdf',
            'price' => 0,
            'currency' => 'USD',
            'is_premium' => false,
            'is_active' => true,
            'is_featured' => false,
        ]);

        $this->getJson('/api/mobile/guest/library/locked-book')
            ->assertOk()
            ->assertJsonPath('data.requires_auth', true)
            ->assertJsonPath('data.item.slug', 'locked-book')
            ->assertJsonMissingPath('data.item.description');
    }

    public function test_guest_providers_return_limited_fields(): void
    {
        $providerUser = User::query()->create([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'provider-guest@example.test',
            'password' => 'password',
            'city' => 'Michigan',
            'state' => 'GA',
            'onboarding_completed' => true,
        ]);

        ServiceProvider::query()->create([
            'user_id' => $providerUser->id,
            'business_name' => 'Hidden Business Name',
            'slug' => 'guest-provider',
            'service_types' => ['immigration_attorney'],
            'languages_offered' => ['en'],
            'pricing_model' => 'hourly',
            'hourly_rate' => 200.00,
            'is_active' => true,
            'accepting_clients' => true,
            'bio' => 'Should not be exposed to guests',
        ]);

        $this->getJson('/api/mobile/guest/providers')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.providers.data.0.slug', 'guest-provider')
            ->assertJsonPath('data.providers.data.0.business_type', 'Immigration Attorney')
            ->assertJsonPath('data.providers.data.0.location', 'GA, Michigan')
            ->assertJsonMissingPath('data.providers.data.0.business_name')
            ->assertJsonMissingPath('data.providers.data.0.bio')
            ->assertJsonMissingPath('data.providers.data.0.hourly_rate');
    }
}
