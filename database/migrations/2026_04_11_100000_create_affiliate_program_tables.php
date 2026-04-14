<?php

use App\Enums\AffiliateEarningStatus;
use App\Enums\AffiliatePayoutStatus;
use App\Enums\AffiliateStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('affiliates')) {
            Schema::create('affiliates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('status')->default(AffiliateStatus::PENDING_VERIFICATION->value);
            $table->string('phone')->nullable();
            $table->string('company_name')->nullable();
            $table->string('website_url')->nullable();
            $table->string('social_profile_url')->nullable();
            $table->text('notes')->nullable();
            $table->string('commission_type_override')->nullable();
            $table->decimal('commission_value_override', 10, 2)->nullable();
            $table->string('payout_method')->nullable();
            $table->json('payout_details')->nullable();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamp('last_attribution_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
            });
        }

        if (! Schema::hasTable('affiliate_invites')) {
            Schema::create('affiliate_invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->nullable()->constrained('affiliates')->nullOnDelete();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('email')->index();
            $table->string('phone')->nullable();
            $table->text('notes')->nullable();
            $table->string('commission_type_override')->nullable();
            $table->decimal('commission_value_override', 10, 2)->nullable();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['email', 'accepted_at']);
            });
        }

        if (! Schema::hasTable('affiliate_referral_visits')) {
            Schema::create('affiliate_referral_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained('affiliates')->cascadeOnDelete();
            $table->string('affiliate_code');
            $table->string('session_id')->nullable();
            $table->string('fingerprint_hash', 64)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('referrer_url')->nullable();
            $table->text('landing_url')->nullable();
            $table->string('landing_path')->nullable();
            $table->json('query_params')->nullable();
            $table->boolean('is_unique')->default(false);
            $table->timestamp('visited_at');
            $table->timestamp('attribution_expires_at')->nullable();
            $table->foreignId('registered_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['affiliate_id', 'visited_at']);
            $table->index(['fingerprint_hash', 'affiliate_id']);
            $table->index(['registered_user_id', 'visited_at']);
            });
        }

        if (! Schema::hasTable('affiliate_referrals')) {
            Schema::create('affiliate_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained('affiliates')->cascadeOnDelete();
            $table->foreignId('referred_user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('first_visit_id')->nullable()->constrained('affiliate_referral_visits')->nullOnDelete();
            $table->foreignId('last_visit_id')->nullable()->constrained('affiliate_referral_visits')->nullOnDelete();
            $table->foreignId('attributed_visit_id')->nullable()->constrained('affiliate_referral_visits')->nullOnDelete();
            $table->string('attribution_model')->default('last_click');
            $table->timestamp('registered_at');
            $table->timestamp('last_conversion_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['affiliate_id', 'registered_at']);
            });
        }

        if (! Schema::hasTable('affiliate_commission_rules')) {
            Schema::create('affiliate_commission_rules', function (Blueprint $table) {
            $table->id();
            $table->string('scope');
            $table->string('trigger_event');
            $table->foreignId('affiliate_id')->nullable()->constrained('affiliates')->nullOnDelete();
            $table->foreignId('referred_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('commission_type');
            $table->decimal('commission_value', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->unsignedInteger('priority')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['scope', 'trigger_event', 'is_active']);
            $table->index(['affiliate_id', 'trigger_event']);
            $table->index(['referred_user_id', 'trigger_event']);
            });
        }

        if (! Schema::hasTable('affiliate_earnings')) {
            Schema::create('affiliate_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained('affiliates')->cascadeOnDelete();
            $table->foreignId('referred_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('affiliate_referral_id')->nullable()->constrained('affiliate_referrals')->nullOnDelete();
            $table->string('source_type');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('event_type');
            $table->foreignId('commission_rule_id')->nullable()->constrained('affiliate_commission_rules')->nullOnDelete();
            $table->string('rule_scope_snapshot');
            $table->string('commission_type_snapshot');
            $table->decimal('commission_value_snapshot', 10, 2);
            $table->decimal('base_amount', 10, 2)->default(0);
            $table->decimal('commission_amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default(AffiliateEarningStatus::PENDING->value);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['affiliate_id', 'event_type', 'source_type', 'source_id'], 'affiliate_earnings_unique_event');
            $table->index(['affiliate_id', 'status', 'created_at']);
            $table->index(['referred_user_id', 'status']);
            });
        }

        if (! Schema::hasTable('affiliate_payouts')) {
            Schema::create('affiliate_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained('affiliates')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->date('payout_date');
            $table->string('payment_method');
            $table->string('payment_reference')->nullable();
            $table->json('payment_details')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default(AffiliatePayoutStatus::COMPLETED->value);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['affiliate_id', 'payout_date']);
            $table->index(['status', 'payout_date']);
            });
        }

        if (! Schema::hasTable('affiliate_payout_items')) {
            Schema::create('affiliate_payout_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_payout_id')->constrained('affiliate_payouts')->cascadeOnDelete();
            $table->foreignId('affiliate_earning_id')->constrained('affiliate_earnings')->cascadeOnDelete();
            $table->decimal('amount_paid', 10, 2);
            $table->timestamps();

            $table->unique(['affiliate_payout_id', 'affiliate_earning_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_payout_items');
        Schema::dropIfExists('affiliate_payouts');
        Schema::dropIfExists('affiliate_earnings');
        Schema::dropIfExists('affiliate_commission_rules');
        Schema::dropIfExists('affiliate_referrals');
        Schema::dropIfExists('affiliate_referral_visits');
        Schema::dropIfExists('affiliate_invites');
        Schema::dropIfExists('affiliates');
    }
};
