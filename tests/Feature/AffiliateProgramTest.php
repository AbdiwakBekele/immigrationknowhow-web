<?php

namespace Tests\Feature;

use App\Enums\AffiliateCommissionScope;
use App\Enums\AffiliateCommissionTrigger;
use App\Enums\AffiliateCommissionType;
use App\Enums\AffiliateEarningStatus;
use App\Enums\AffiliateStatus;
use App\Enums\UserRole;
use App\Models\Affiliate;
use App\Models\AffiliateCommissionRule;
use App\Models\AffiliateEarning;
use App\Models\AffiliateInvite;
use App\Models\AffiliateReferral;
use App\Models\Lead;
use App\Models\ServiceProvider;
use App\Models\User;
use App\Notifications\AffiliateInvitationNotification;
use App\Notifications\AffiliatePayoutRecordedNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AffiliateProgramTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_affiliate_can_self_register_and_receives_verification_email(): void
    {
        Notification::fake();

        $response = $this->post(route('affiliate.register.store'), [
            'first_name' => 'Ava',
            'last_name' => 'Partner',
            'email' => 'ava@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'phone' => '555-0100',
            'company_name' => 'Ava Media',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $this->assertAuthenticated();

        $user = User::where('email', 'ava@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole(UserRole::AFFILIATE->value));
        $this->assertDatabaseHas('affiliates', [
            'user_id' => $user->id,
            'status' => AffiliateStatus::PENDING_VERIFICATION->value,
        ]);

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_admin_can_invite_an_affiliate(): void
    {
        Notification::fake();

        $admin = $this->createUser(['email' => 'admin@example.com']);
        $admin->assignRole(UserRole::ADMIN->value);

        $response = $this->actingAs($admin)->post(route('admin.affiliates.invite.store'), [
            'name' => 'Invited Partner',
            'email' => 'invitee@example.com',
            'phone' => '555-0200',
            'commission_type_override' => AffiliateCommissionType::FIXED->value,
            'commission_value_override' => 45,
            'notes' => 'Top of funnel creator',
        ]);

        $response->assertRedirect(route('admin.affiliates.index'));

        $this->assertDatabaseHas('affiliate_invites', [
            'email' => 'invitee@example.com',
            'commission_type_override' => AffiliateCommissionType::FIXED->value,
        ]);

        Notification::assertSentOnDemand(AffiliateInvitationNotification::class);
    }

    public function test_admin_can_resend_a_pending_affiliate_invitation(): void
    {
        Notification::fake();

        $admin = $this->createUser(['email' => 'admin2@example.com']);
        $admin->assignRole(UserRole::ADMIN->value);

        $invite = AffiliateInvite::create([
            'invited_by' => $admin->id,
            'name' => 'Retry Partner',
            'email' => 'retry@example.com',
            'token_hash' => hash('sha256', 'old-token'),
            'expires_at' => now()->subDay(),
            'sent_at' => now()->subDays(2),
        ]);

        $originalHash = $invite->token_hash;

        $response = $this->actingAs($admin)
            ->post(route('admin.affiliates.invite.resend', $invite));

        $response->assertSessionHas('success');

        $invite->refresh();

        $this->assertNotSame($originalHash, $invite->token_hash);
        $this->assertTrue($invite->expires_at->isFuture());
        Notification::assertSentOnDemand(AffiliateInvitationNotification::class);
    }

    public function test_invited_affiliate_sets_password_then_redirects_to_profile_completion(): void
    {
        Notification::fake();

        $admin = $this->createUser(['email' => 'admin3@example.com']);
        $admin->assignRole(UserRole::ADMIN->value);

        $invite = AffiliateInvite::create([
            'invited_by' => $admin->id,
            'name' => 'James Carter',
            'email' => 'james@example.com',
            'phone' => '555-8888',
            'token_hash' => hash('sha256', 'invite-token'),
            'expires_at' => now()->addDays(7),
            'sent_at' => now(),
        ]);

        $response = $this->post(route('affiliate.invites.store', ['token' => 'invite-token']), [
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect(route('affiliate.profile.edit'));
        $this->assertAuthenticated();

        $user = User::where('email', 'james@example.com')->firstOrFail();
        $this->assertSame('James', $user->first_name);
        $this->assertSame('Carter', $user->last_name);
        $this->assertSame('555-8888', $user->phone);
        $this->assertTrue($user->hasRole(UserRole::AFFILIATE->value));

        $invite->refresh();
        $this->assertNotNull($invite->accepted_at);

        Notification::assertSentTo($user, VerifyEmail::class);

        $this->actingAs($user)
            ->get(route('affiliate.profile.edit'))
            ->assertOk();
    }

    public function test_referred_signup_creates_referral_and_signup_earning(): void
    {
        $affiliateUser = $this->createUser(['email' => 'affiliate@example.com', 'email_verified_at' => now()]);
        $affiliateUser->assignRole(UserRole::AFFILIATE->value);
        $affiliate = Affiliate::create([
            'user_id' => $affiliateUser->id,
            'code' => 'AFFCODE1',
            'status' => AffiliateStatus::ACTIVE->value,
        ]);

        AffiliateCommissionRule::create([
            'scope' => AffiliateCommissionScope::GLOBAL->value,
            'trigger_event' => AffiliateCommissionTrigger::SIGNUP->value,
            'commission_type' => AffiliateCommissionType::FIXED->value,
            'commission_value' => 25,
            'currency' => 'USD',
            'created_by' => $affiliateUser->id,
        ]);

        $this->get('/?ref=AFFCODE1')->assertOk();

        $response = $this->post(route('register'), [
            'first_name' => 'Referred',
            'last_name' => 'User',
            'email' => 'referred@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => UserRole::USER->value,
        ]);

        $response->assertRedirect(route('onboarding.index'));

        $referredUser = User::where('email', 'referred@example.com')->firstOrFail();
        $referral = AffiliateReferral::where('referred_user_id', $referredUser->id)->firstOrFail();

        $this->assertSame($affiliate->id, $referral->affiliate_id);
        $this->assertDatabaseHas('affiliate_earnings', [
            'affiliate_id' => $affiliate->id,
            'referred_user_id' => $referredUser->id,
            'event_type' => AffiliateCommissionTrigger::SIGNUP->value,
            'commission_amount' => 25.00,
            'status' => AffiliateEarningStatus::PENDING->value,
        ]);
    }

    public function test_provider_converting_referred_lead_creates_conversion_earning(): void
    {
        $affiliateUser = $this->createUser(['email' => 'partner@example.com', 'email_verified_at' => now()]);
        $affiliateUser->assignRole(UserRole::AFFILIATE->value);
        $affiliate = Affiliate::create([
            'user_id' => $affiliateUser->id,
            'code' => 'AFFCODE2',
            'status' => AffiliateStatus::ACTIVE->value,
        ]);

        $referredUser = $this->createUser(['email' => 'leadowner@example.com']);
        $referral = AffiliateReferral::create([
            'affiliate_id' => $affiliate->id,
            'referred_user_id' => $referredUser->id,
            'attribution_model' => 'last_click',
            'registered_at' => now(),
        ]);
        $referredUser->update([
            'referred_by_affiliate_id' => $affiliate->id,
            'affiliate_referral_id' => $referral->id,
        ]);

        AffiliateCommissionRule::create([
            'scope' => AffiliateCommissionScope::GLOBAL->value,
            'trigger_event' => AffiliateCommissionTrigger::LEAD_CONVERTED->value,
            'commission_type' => AffiliateCommissionType::FIXED->value,
            'commission_value' => 75,
            'currency' => 'USD',
            'created_by' => $affiliateUser->id,
        ]);

        $providerUser = $this->createUser(['email' => 'provider@example.com']);
        $providerUser->assignRole(UserRole::PROVIDER->value);
        $provider = ServiceProvider::create([
            'user_id' => $providerUser->id,
            'business_name' => 'Provider Firm',
            'business_email' => 'provider@example.com',
            'service_types' => ['other'],
            'languages_offered' => ['en'],
        ]);

        $lead = Lead::create([
            'user_id' => $referredUser->id,
            'service_provider_id' => $provider->id,
            'service_type' => 'other',
            'status' => 'new',
            'message' => 'I need help with immigration paperwork and consultation.',
            'urgency' => 'normal',
            'budget_range' => '500-1000',
            'source' => 'affiliate',
            'referral_code' => $affiliate->code,
            'affiliate_referral_id' => $referral->id,
        ]);

        $response = $this->actingAs($providerUser)
            ->patch(route('provider.leads.status', $lead), ['status' => 'converted']);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('affiliate_earnings', [
            'affiliate_id' => $affiliate->id,
            'referred_user_id' => $referredUser->id,
            'event_type' => AffiliateCommissionTrigger::LEAD_CONVERTED->value,
            'source_type' => Lead::class,
            'source_id' => $lead->id,
            'commission_amount' => 75.00,
        ]);
    }

    public function test_admin_can_record_payout_and_mark_earning_paid(): void
    {
        Notification::fake();

        $admin = $this->createUser(['email' => 'boss@example.com']);
        $admin->assignRole(UserRole::ADMIN->value);

        $affiliateUser = $this->createUser(['email' => 'cashout@example.com', 'email_verified_at' => now()]);
        $affiliateUser->assignRole(UserRole::AFFILIATE->value);
        $affiliate = Affiliate::create([
            'user_id' => $affiliateUser->id,
            'code' => 'AFFCODE3',
            'status' => AffiliateStatus::ACTIVE->value,
        ]);

        $earning = AffiliateEarning::create([
            'affiliate_id' => $affiliate->id,
            'event_type' => AffiliateCommissionTrigger::SIGNUP->value,
            'source_type' => User::class,
            'source_id' => 999,
            'rule_scope_snapshot' => AffiliateCommissionScope::GLOBAL->value,
            'commission_type_snapshot' => AffiliateCommissionType::FIXED->value,
            'commission_value_snapshot' => 35,
            'base_amount' => 0,
            'commission_amount' => 35,
            'currency' => 'USD',
            'status' => AffiliateEarningStatus::APPROVED->value,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.affiliates.payouts.store'), [
            'affiliate_id' => $affiliate->id,
            'earning_ids' => [$earning->id],
            'amount' => 35,
            'currency' => 'USD',
            'payout_date' => now()->toDateString(),
            'payment_method' => 'PayPal',
            'payment_reference' => 'txn_123',
            'notes' => 'Manual payout',
        ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('affiliate_payouts', [
            'affiliate_id' => $affiliate->id,
            'amount' => 35.00,
            'payment_reference' => 'txn_123',
        ]);

        $earning->refresh();
        $this->assertSame(AffiliateEarningStatus::PAID, $earning->status);

        Notification::assertSentTo($affiliateUser, AffiliatePayoutRecordedNotification::class);
    }

    protected function createUser(array $overrides = []): User
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
