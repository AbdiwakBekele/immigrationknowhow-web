<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserImpersonationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function makeUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'user-'.uniqid('', true).'@example.com',
            'password' => Hash::make('password'),
            'onboarding_completed' => true,
        ], $overrides));
    }

    public function test_guest_cannot_start_impersonation(): void
    {
        $target = $this->makeUser();
        $target->assignRole(UserRole::USER->value);

        $this->post(route('admin.users.impersonate', $target))
            ->assertRedirect(route('login'));
    }

    public function test_admin_can_impersonate_general_user(): void
    {
        $admin = $this->makeUser(['email' => 'admin@example.com']);
        $admin->assignRole(UserRole::ADMIN->value);
        $target = $this->makeUser(['email' => 'seeker@example.com']);
        $target->assignRole(UserRole::USER->value);

        $response = $this->actingAs($admin)->post(route('admin.users.impersonate', $target));

        $response->assertRedirect();
        $this->assertSame($target->id, auth()->id());
        $this->assertTrue(session()->has('impersonating'));
    }

    public function test_admin_impersonating_incomplete_service_needer_lands_on_step_two(): void
    {
        $admin = $this->makeUser(['email' => 'admin-step2@example.com']);
        $admin->assignRole(UserRole::ADMIN->value);
        $target = $this->makeUser([
            'email' => 'incomplete-user@example.com',
            'onboarding_completed' => false,
            'phone_verified_at' => null,
            'city' => null,
            'state' => null,
            'country' => null,
            'postal_code' => null,
        ]);
        $target->assignRole(UserRole::USER->value);

        $response = $this->actingAs($admin)->post(route('admin.users.impersonate', $target));

        $response->assertRedirect(route('onboarding.index', ['step' => 2]));
    }

    public function test_regular_admin_cannot_impersonate_super_admin(): void
    {
        $admin = $this->makeUser();
        $admin->assignRole(UserRole::ADMIN->value);
        $super = $this->makeUser();
        $super->assignRole(UserRole::SUPER_ADMIN->value);

        $response = $this->actingAs($admin)->post(route('admin.users.impersonate', $super));

        $response->assertSessionHasErrors('error');
        $this->assertSame($admin->id, auth()->id());
    }

    public function test_super_admin_can_impersonate_super_admin(): void
    {
        $superA = $this->makeUser();
        $superA->assignRole(UserRole::SUPER_ADMIN->value);
        $superB = $this->makeUser();
        $superB->assignRole(UserRole::SUPER_ADMIN->value);

        $response = $this->actingAs($superA)->post(route('admin.users.impersonate', $superB));

        $response->assertRedirect();
        $this->assertSame($superB->id, auth()->id());
    }

    public function test_stop_impersonation_restores_admin_session(): void
    {
        $admin = $this->makeUser();
        $admin->assignRole(UserRole::ADMIN->value);
        $target = $this->makeUser();
        $target->assignRole(UserRole::USER->value);

        $this->actingAs($admin)->post(route('admin.users.impersonate', $target));
        $this->assertSame($target->id, auth()->id());

        $response = $this->post(route('impersonation.leave'));

        $response->assertRedirect(route('admin.users.index'));
        $this->assertSame($admin->id, auth()->id());
        $this->assertFalse(session()->has('impersonating'));
    }
}
