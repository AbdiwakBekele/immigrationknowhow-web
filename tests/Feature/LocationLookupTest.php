<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LocationLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_uszip_states_and_locations_can_be_searched_for_onboarding(): void
    {
        $user = $this->createUser();
        $this->seedUsZipRows();

        $this->actingAs($user)
            ->getJson(route('locations.states', ['country' => 'US']))
            ->assertOk()
            ->assertJsonFragment(['value' => 'MI', 'label' => 'Michigan']);

        $this->actingAs($user)
            ->getJson(route('locations.search', [
                'country' => 'US',
                'state_id' => 'MI',
                'q' => '482',
            ]))
            ->assertOk()
            ->assertJsonPath('results.0.type', 'zip')
            ->assertJsonPath('results.0.zip', '48226')
            ->assertJsonPath('results.0.city', 'Detroit')
            ->assertJsonPath('results.0.county', 'Wayne');

        $this->actingAs($user)
            ->getJson(route('locations.search', [
                'country' => 'US',
                'state_id' => 'MI',
                'q' => 'Way',
            ]))
            ->assertOk()
            ->assertJsonFragment([
                'type' => 'county',
                'county' => 'Wayne',
            ]);

        $this->actingAs($user)
            ->getJson(route('locations.search', [
                'country' => 'US',
                'state_id' => 'MI',
                'q' => 'Det',
            ]))
            ->assertOk()
            ->assertJsonFragment([
                'type' => 'city',
                'city' => 'Detroit',
            ]);
    }

    public function test_onboarding_completion_saves_selected_location_details(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->post(route('onboarding.complete'), [
                'services_needed' => ['immigration_attorney'],
                'city' => 'Detroit',
                'state' => 'MI',
                'postal_code' => '48226',
                'country' => 'US',
                'county' => 'Wayne',
                'location_label' => '48226 - Detroit, MI',
                'preferred_language' => 'en',
                'languages' => ['en'],
            ])
            ->assertRedirect(route('dashboard'));

        $user->refresh();

        $this->assertTrue($user->onboarding_completed);
        $this->assertSame('Detroit', $user->city);
        $this->assertSame('MI', $user->state);
        $this->assertSame('48226', $user->postal_code);
        $this->assertSame('US', $user->country);
        $this->assertSame('Wayne', data_get($user->onboarding_data, 'location.county'));
        $this->assertSame('48226 - Detroit, MI', data_get($user->onboarding_data, 'location.label'));
    }

    public function test_user_can_complete_onboarding_without_selecting_a_service_type(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->post(route('onboarding.complete'), [
                'services_needed' => [],
                'city' => 'Livonia',
                'state' => 'MI',
                'country' => 'US',
                'county' => 'Wayne',
                'location_label' => 'Livonia, MI',
                'preferred_language' => 'en',
                'languages' => ['en'],
            ])
            ->assertRedirect(route('dashboard'));

        $user->refresh();

        $this->assertTrue($user->onboarding_completed);
        $this->assertSame([], data_get($user->onboarding_data, 'services.services_needed', []));
        $this->assertSame('Livonia, MI', data_get($user->onboarding_data, 'location.label'));
    }

    private function seedUsZipRows(): void
    {
        DB::table('uszips')->insert([
            [
                'zip' => '48226',
                'city' => 'Detroit',
                'state' => 'MI',
                'state_id' => 'MI',
                'state_name' => 'Michigan',
                'county_name' => 'Wayne',
            ],
            [
                'zip' => '90012',
                'city' => 'Los Angeles',
                'state' => 'CA',
                'state_id' => 'CA',
                'state_name' => 'California',
                'county_name' => 'Los Angeles',
            ],
        ]);
    }

    private function createUser(array $overrides = []): User
    {
        return User::query()->create(array_merge([
            'first_name' => 'Location',
            'last_name' => 'User',
            'email' => 'location-user-'.uniqid().'@example.com',
            'password' => 'Password123!',
            'onboarding_completed' => false,
            'is_active' => true,
        ], $overrides));
    }
}
