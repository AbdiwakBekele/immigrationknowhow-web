<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_user_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('video_embed_id')->constrained('video_embeds')->cascadeOnDelete();
            $table->timestamp('purchased_at')->nullable();
            $table->decimal('purchase_amount', 10, 2)->nullable();
            $table->string('purchase_currency', 3)->nullable();
            $table->string('stripe_checkout_session_id')->nullable();
            $table->string('stripe_payment_intent_id')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'video_embed_id']);
            $table->index(['user_id', 'purchased_at']);
            $table->index('stripe_checkout_session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_user_access');
    }
};

