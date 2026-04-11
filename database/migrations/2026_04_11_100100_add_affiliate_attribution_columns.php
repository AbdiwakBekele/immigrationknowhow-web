<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('referred_by_affiliate_id')->nullable()->after('onboarding_completed_at')->constrained('affiliates')->nullOnDelete();
            $table->foreignId('affiliate_referral_id')->nullable()->after('referred_by_affiliate_id')->constrained('affiliate_referrals')->nullOnDelete();
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('affiliate_referral_id')->nullable()->after('referral_code')->constrained('affiliate_referrals')->nullOnDelete();
            $table->index(['source', 'affiliate_referral_id']);
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['source', 'affiliate_referral_id']);
            $table->dropConstrainedForeignId('affiliate_referral_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('affiliate_referral_id');
            $table->dropConstrainedForeignId('referred_by_affiliate_id');
        });
    }
};
