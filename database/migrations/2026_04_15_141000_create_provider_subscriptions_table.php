<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('service_provider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['incomplete', 'trialing', 'active', 'past_due', 'unpaid', 'canceled', 'expired'])
                ->default('incomplete');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('renewal_count')->default(0);
            $table->string('stripe_customer_id')->nullable();
            $table->string('stripe_subscription_id')->nullable()->index();
            $table->string('stripe_checkout_session_id')->nullable();
            $table->foreignId('affiliate_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('affiliate_referral_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('affiliate_attribution_type', ['first_touch', 'last_touch'])->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['service_provider_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_subscriptions');
    }
};
