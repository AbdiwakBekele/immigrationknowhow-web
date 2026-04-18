<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\UsZipSeeder;
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
        $user = $this->createUser(['phone' => '15555550111', 'phone_verified_at' => now()]);

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
        $user = $this->createUser(['phone' => '15555550112', 'phone_verified_at' => now()]);

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

    public function test_uszip_seeder_imports_adminer_sql_gzip_dump(): void
    {
        $basePath = tempnam(sys_get_temp_dir(), 'uszips-test-');
        @unlink($basePath);
        $path = $basePath.'.sql.gz';

        $sql = <<<'SQL'
-- Adminer 4.8.4 MySQL dump
INSERT INTO `uszips` (`zip`, `lat`, `lng`, `city`, `state_id`, `state_name`, `zcta`, `parent_zcta`, `population`, `density`, `county_fips`, `county_name`, `county_weights`, `county_names_all`, `county_fips_all`, `imprecise`, `military`, `timezone`) VALUES
('48150', '42.36837', '-83.35271', 'Livonia', 'MI', 'Michigan', 1, NULL, 27986, 1316.5, '26163', 'Wayne', '{\"26163\": 100}', 'Wayne', '26163', 0, 0, 'America/Detroit'),
('90012', '34.06178', '-118.23898', 'Los Angeles', 'CA', 'California', 1, NULL, 31103, 3574.2, '06037', 'Los Angeles', '{\"06037\": 100}', 'Los Angeles', '06037', 0, 0, 'America/Los_Angeles');
SQL;

        $handle = gzopen($path, 'wb9');
        gzwrite($handle, $sql);
        gzclose($handle);

        $_SERVER['USZIPS_SQL_PATH'] = $path;
        $_ENV['USZIPS_SQL_PATH'] = $path;
        putenv("USZIPS_SQL_PATH={$path}");

        try {
            $this->seed(UsZipSeeder::class);
        } finally {
            unset($_SERVER['USZIPS_SQL_PATH'], $_ENV['USZIPS_SQL_PATH']);
            putenv('USZIPS_SQL_PATH');
            @unlink($path);
        }

        $this->assertDatabaseCount('uszips', 2);
        $this->assertDatabaseHas('uszips', [
            'zip' => '48150',
            'city' => 'Livonia',
            'state_id' => 'MI',
            'county_name' => 'Wayne',
        ]);
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
