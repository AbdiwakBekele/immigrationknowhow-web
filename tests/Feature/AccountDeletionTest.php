<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ServiceProvider;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_seeker_can_delete_account_via_web(): void
    {
        $user = $this->createSeeker('seeker-delete@example.com');

        $this->actingAs($user)
            ->delete(route('account.destroy'), [
                'password' => 'Password123!',
                'confirmation' => 'DELETE',
            ])
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertSoftDeleted('users', ['id' => $user->id]);

        $deleted = User::withTrashed()->find($user->id);
        $this->assertSame('deleted-'.$user->id.'@deleted.immigrationknowhow.local', $deleted?->email);
        $this->assertFalse((bool) $deleted?->is_active);
    }

    public function test_provider_only_user_can_delete_account_via_web(): void
    {
        $user = $this->createProviderOnly('provider-delete@example.com');

        $this->actingAs($user)
            ->delete(route('account.destroy'), [
                'password' => 'Password123!',
                'confirmation' => 'DELETE',
            ])
            ->assertRedirect('/');

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_account_deletion_requires_valid_password_and_confirmation(): void
    {
        $user = $this->createSeeker('seeker-guard@example.com');

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('account.destroy'), [
                'password' => 'wrong-password',
                'confirmation' => 'DELETE',
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors('password');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'deleted_at' => null,
        ]);
    }

    public function test_mobile_user_can_delete_account_and_revokes_tokens(): void
    {
        $user = $this->createSeeker('mobile-delete@example.com');
        $token = $user->createToken('mobile')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/mobile/account', [
                'password' => 'Password123!',
                'confirmation' => 'DELETE',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_mobile_provider_can_delete_account(): void
    {
        $user = $this->createProviderOnly('mobile-provider-delete@example.com');
        Sanctum::actingAs($user);

        $this->deleteJson('/api/mobile/account', [
            'password' => 'Password123!',
            'confirmation' => 'DELETE',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    private function createSeeker(string $email): User
    {
        $user = User::create([
            'first_name' => 'Seeker',
            'last_name' => 'User',
            'email' => $email,
            'password' => Hash::make('Password123!'),
            'onboarding_completed' => true,
            'onboarding_completed_at' => now(),
            'is_active' => true,
        ]);
        $user->assignRole(UserRole::USER->value);

        return $user;
    }

    private function createProviderOnly(string $email): User
    {
        $user = User::create([
            'first_name' => 'Provider',
            'last_name' => 'Only',
            'email' => $email,
            'password' => Hash::make('Password123!'),
            'onboarding_completed' => true,
            'onboarding_completed_at' => now(),
            'is_active' => true,
        ]);
        $user->assignRole(UserRole::PROVIDER->value);

        ServiceProvider::query()->create([
            'user_id' => $user->id,
            'business_name' => 'Delete Test LLC',
            'slug' => 'delete-test-'.uniqid(),
            'service_types' => ['immigration'],
            'languages_offered' => ['en'],
            'verification_status' => 'approved',
            'is_active' => true,
        ]);

        return $user;
    }
}
