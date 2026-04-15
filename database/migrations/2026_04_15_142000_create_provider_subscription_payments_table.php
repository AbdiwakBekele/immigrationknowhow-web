<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_provider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('stripe_invoice_id')->nullable()->unique();
            $table->string('stripe_payment_intent_id')->nullable();
            $table->unsignedInteger('amount_due_cents')->default(0);
            $table->unsignedInteger('amount_paid_cents')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->enum('status', ['open', 'paid', 'failed', 'void', 'refunded'])->default('open');
            $table->string('billing_reason')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('invoice_pdf_url')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
            $table->index(['service_provider_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_subscription_payments');
    }
};
