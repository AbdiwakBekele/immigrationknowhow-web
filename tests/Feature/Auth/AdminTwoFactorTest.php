<?php

namespace Tests\Feature\Auth;

use App\Enums\AdminTwoFactorDeliveryMethod;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\TwilioService;
use App\Support\AdminTwoFactorSession;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class AdminTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        config([
            'admin_two_factor.enabled' => true,
            'services.twilio.fake' => true,
        ]);
    }

    public function test_admin_login_redirects_to_two_factor_without_sending_otp(): void
    {
        $twilio = Mockery::mock(TwilioService::class);
        $twilio->shouldReceive('isFakeMode')->andReturn(false);
        $twilio->shouldNotReceive('sendVerificationOtp');
        $this->app->instance(TwilioService::class, $twilio);

        $admin = $this->createAdmin();

        $this->post(route('login'), [
            'email' => $admin->email,
            'password' => 'Password123!',
        ])->assertRedirect(route('admin.2fa.challenge'));
    }

    public function test_challenge_issues_one_sms_on_first_load(): void
    {
        $twilio = Mockery::mock(TwilioService::class);
        $twilio->shouldReceive('isFakeMode')->andReturn(false);
        $twilio->shouldReceive('sendVerificationOtp')
            ->once()
            ->with('+15551234567', 'sms');
        $this->app->instance(TwilioService::class, $twilio);

        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->get(route('admin.2fa.challenge'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('codeSent', true)
            );
    }

    public function test_challenge_refresh_does_not_resend_otp(): void
    {
        $twilio = Mockery::mock(TwilioService::class);
        $twilio->shouldReceive('isFakeMode')->andReturn(false);
        $twilio->shouldReceive('sendVerificationOtp')
            ->once()
            ->with('+15551234567', 'sms');
        $this->app->instance(TwilioService::class, $twilio);

        $admin = $this->createAdmin();

        $session = $this->actingAs($admin);
        $session->get(route('admin.2fa.challenge'))->assertOk();
        $session->get(route('admin.2fa.challenge'))->assertOk();
    }

    public function test_resend_issues_exactly_one_new_code(): void
    {
        config(['admin_two_factor.send_cooldown_seconds' => 0]);

        $twilio = Mockery::mock(TwilioService::class);
        $twilio->shouldReceive('isFakeMode')->andReturn(false);
        $twilio->shouldReceive('sendVerificationOtp')
            ->twice()
            ->with('+15551234567', 'sms');
        $this->app->instance(TwilioService::class, $twilio);

        $admin = $this->createAdmin();
        $session = $this->actingAs($admin);

        $session->get(route('admin.2fa.challenge'));
        $session->post(route('admin.2fa.send'))->assertRedirect();
    }

    public function test_concurrent_resend_is_blocked_by_backend_cooldown(): void
    {
        config(['admin_two_factor.send_cooldown_seconds' => 30]);

        $twilio = Mockery::mock(TwilioService::class);
        $twilio->shouldReceive('isFakeMode')->andReturn(false);
        $twilio->shouldReceive('sendVerificationOtp')
            ->twice()
            ->with('+15551234567', 'sms');
        $this->app->instance(TwilioService::class, $twilio);

        $admin = $this->createAdmin();
        $session = $this->actingAs($admin);
        $session->get(route('admin.2fa.challenge'));

        $session->post(route('admin.2fa.send'))->assertRedirect();
        $session->post(route('admin.2fa.send'))->assertSessionHasErrors('code');
    }

    public function test_admin_can_access_dashboard_after_verifying_otp(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)->get(route('admin.2fa.challenge'));
        $this->actingAs($admin)
            ->post(route('admin.2fa.verify'), ['code' => '123456'])
            ->assertRedirect(route('admin.dashboard'));

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk();
    }

    public function test_admin_verification_rejects_invalid_otp(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)->get(route('admin.2fa.challenge'));
        $this->actingAs($admin)
            ->post(route('admin.2fa.verify'), ['code' => '000000'])
            ->assertSessionHasErrors('code');
    }

    public function test_admin_without_phone_sees_setup_prompt(): void
    {
        $admin = $this->createAdmin(withPhone: false);

        $this->actingAs($admin)
            ->get(route('admin.2fa.challenge'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/AdminTwoFactor')
                ->where('needsPhone', true)
            );
    }

    public function test_logout_clears_admin_two_factor_session(): void
    {
        $admin = $this->createAdmin();

        $this->withSession([
            AdminTwoFactorSession::VERIFIED_AT => now()->toIso8601String(),
            AdminTwoFactorSession::DELIVERY_METHOD => AdminTwoFactorDeliveryMethod::Sms->value,
        ])
            ->actingAs($admin)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    private function createAdmin(bool $withPhone = true): User
    {
        $admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin-2fa'.uniqid().'@example.com',
            'password' => Hash::make('Password123!'),
            'phone' => $withPhone ? '+15551234567' : null,
            'onboarding_completed' => true,
            'onboarding_completed_at' => now(),
            'is_active' => true,
        ]);

        $admin->assignRole(UserRole::ADMIN->value);

        return $admin;
    }
}
