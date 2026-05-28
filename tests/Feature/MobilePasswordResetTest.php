<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\EmailTemplateSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MobilePasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(EmailTemplateSeeder::class);
    }

    public function test_mobile_forgot_password_sends_link_for_active_user(): void
    {
        Notification::fake();

        $user = $this->createUser('mobile-reset@example.com');

        $this->postJson('/api/mobile/auth/forgot-password', [
            'email' => $user->email,
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_mobile_forgot_password_hides_unknown_emails(): void
    {
        Notification::fake();

        $this->postJson('/api/mobile/auth/forgot-password', [
            'email' => 'unknown@example.com',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        Notification::assertNothingSent();
    }

    public function test_mobile_reset_password_updates_credentials(): void
    {
        Notification::fake();

        $user = $this->createUser('mobile-newpass@example.com');
        $oldPasswordHash = $user->password;

        $this->postJson('/api/mobile/auth/forgot-password', [
            'email' => $user->email,
        ])->assertOk();

        $token = '';
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return $token !== '';
        });

        $this->postJson('/api/mobile/auth/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $user->refresh();

        $this->assertNotSame($oldPasswordHash, $user->password);
        $this->assertTrue(Hash::check('NewPassword123!', $user->password));

        $this->postJson('/api/mobile/auth/login', [
            'email' => $user->email,
            'password' => 'NewPassword123!',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'user']]);
    }

    public function test_mobile_reset_password_rejects_invalid_token(): void
    {
        $user = $this->createUser('mobile-bad-token@example.com');

        $this->postJson('/api/mobile/auth/reset-password', [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    private function createUser(string $email): User
    {
        $user = User::create([
            'first_name' => 'Mobile',
            'last_name' => 'User',
            'email' => $email,
            'password' => Hash::make('OldPassword123!'),
            'email_verified_at' => now(),
            'onboarding_completed' => true,
            'onboarding_completed_at' => now(),
            'is_active' => true,
        ]);
        $user->assignRole(UserRole::USER->value);

        return $user;
    }
}
