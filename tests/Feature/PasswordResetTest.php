<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\User;
use Database\Seeders\EmailTemplateSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(EmailTemplateSeeder::class);
    }

    public function test_forgot_password_page_is_available(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/ForgotPassword'));
    }

    public function test_reset_link_is_sent_for_active_user_without_revealing_existence(): void
    {
        Notification::fake();

        $user = $this->createUser('reset-me@example.com');

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_unknown_email_shows_same_status_without_sending_notification(): void
    {
        Notification::fake();

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => 'missing@example.com'])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('status');

        Notification::assertNothingSent();
    }

    public function test_inactive_user_does_not_receive_reset_notification(): void
    {
        Notification::fake();

        $user = $this->createUser('inactive@example.com', isActive: false);

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('status');

        Notification::assertNothingSent();
    }

    public function test_user_can_reset_password_with_valid_token(): void
    {
        Notification::fake();

        $user = $this->createUser('newpass@example.com');
        $oldPasswordHash = $user->password;

        $this->post(route('password.email'), ['email' => $user->email]);

        $token = '';
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return $token !== '';
        });

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/ResetPassword')
                ->where('email', $user->email)
                ->where('token', $token));

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $user->refresh();

        $this->assertNotSame($oldPasswordHash, $user->password);
        $this->assertTrue(Hash::check('NewPassword123!', $user->password));

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'NewPassword123!',
        ])->assertRedirect();
    }

    public function test_password_reset_email_is_logged_with_template(): void
    {
        $user = $this->createUser('logged-reset@example.com');

        $user->notify(new ResetPassword(Password::createToken($user)));

        $log = EmailLog::query()->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame('outgoing', $log->direction);
        $this->assertSame('sent', $log->status);
        $this->assertSame($user->email, $log->to_email);
        $this->assertSame(EmailTemplate::EVENT_PASSWORD_RESET, $log->payload['event_key'] ?? null);
    }

    public function test_login_page_links_to_forgot_password(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/Login')
                ->where('canResetPassword', true));
    }

    private function createUser(string $email, bool $isActive = true): User
    {
        $user = User::create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $email,
            'password' => Hash::make('OldPassword123!'),
            'email_verified_at' => now(),
            'onboarding_completed' => true,
            'onboarding_completed_at' => now(),
            'is_active' => $isActive,
        ]);
        $user->assignRole(UserRole::USER->value);

        return $user;
    }
}
