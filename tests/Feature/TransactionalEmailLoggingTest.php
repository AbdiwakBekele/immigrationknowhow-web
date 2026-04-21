<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AffiliateInvite;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Notifications\AffiliateInvitationNotification;
use App\Notifications\RoleAwareTransactionalEmailNotification;
use Database\Seeders\EmailTemplateSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TransactionalEmailLoggingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(EmailTemplateSeeder::class);
    }

    public function test_role_aware_welcome_email_is_logged_with_template_and_user(): void
    {
        $user = User::create([
            'first_name' => 'Maya',
            'last_name' => 'User',
            'email' => 'maya@example.com',
            'password' => Hash::make('Password123!'),
            'onboarding_completed' => true,
            'onboarding_completed_at' => now(),
            'is_active' => true,
        ]);
        $user->assignRole(UserRole::USER->value);

        $user->notify(new RoleAwareTransactionalEmailNotification(
            EmailTemplate::EVENT_WELCOME,
            $user,
            [
                'role' => UserRole::USER->value,
                'dashboard_link' => route('dashboard'),
            ]
        ));

        $log = EmailLog::query()->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame('outgoing', $log->direction);
        $this->assertSame('sent', $log->status);
        $this->assertSame($user->email, $log->to_email);
        $this->assertSame($user->id, $log->user_id);
        $this->assertNotNull($log->template_id);
        $this->assertSame(EmailTemplate::EVENT_WELCOME, $log->payload['event_key'] ?? null);
    }

    public function test_affiliate_invite_email_is_logged_with_transactional_template(): void
    {
        $admin = User::create([
            'first_name' => 'Ari',
            'last_name' => 'Admin',
            'email' => 'admin-invite@example.com',
            'password' => Hash::make('Password123!'),
            'onboarding_completed' => true,
            'onboarding_completed_at' => now(),
            'is_active' => true,
        ]);
        $admin->assignRole(UserRole::ADMIN->value);

        $invite = AffiliateInvite::create([
            'invited_by' => $admin->id,
            'name' => 'Jordan Partner',
            'email' => 'jordan.partner@example.com',
            'token_hash' => hash('sha256', 'invite-token'),
            'expires_at' => now()->addDays(7),
            'sent_at' => now(),
        ]);

        Notification::route('mail', $invite->email)
            ->notify(new AffiliateInvitationNotification($invite, 'invite-token'));

        $log = EmailLog::query()->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame('outgoing', $log->direction);
        $this->assertSame($invite->email, $log->to_email);
        $this->assertNotNull($log->template_id);
        $this->assertSame(EmailTemplate::EVENT_INVITE, $log->payload['event_key'] ?? null);
    }
}
