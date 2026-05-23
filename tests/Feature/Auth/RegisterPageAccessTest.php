<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\RoleHelper;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegisterPageAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_guest_can_view_register_page(): void
    {
        $this->get(route('register'))
            ->assertOk();
    }

    public function test_authenticated_provider_without_seeker_role_is_not_redirected_to_user_dashboard(): void
    {
        RoleHelper::ensureExists(UserRole::PROVIDER->value);

        $user = $this->createUser();
        $user->assignRole(UserRole::PROVIDER->value);

        $this->actingAs($user)
            ->get(route('register'))
            ->assertRedirect(route('account-roles.seeker.create'));
    }

    public function test_authenticated_seeker_is_redirected_to_onboarding_or_dashboard_not_register_form(): void
    {
        RoleHelper::ensureExists(UserRole::USER->value);

        $user = $this->createUser();
        $user->assignRole(UserRole::USER->value);

        $this->actingAs($user)
            ->get(route('register'))
            ->assertRedirect();
    }

    private function createUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'user'.uniqid().'@example.com',
            'password' => Hash::make('Password123!'),
            'onboarding_completed' => true,
            'onboarding_completed_at' => now(),
            'is_active' => true,
        ], $overrides));
    }
}
