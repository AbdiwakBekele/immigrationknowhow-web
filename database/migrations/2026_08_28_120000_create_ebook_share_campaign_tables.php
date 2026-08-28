<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ebook_share_campaigns')) {
            Schema::create('ebook_share_campaigns', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('status', 32)->default('in_progress');
                $table->unsignedTinyInteger('required_shares')->default(5);
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('rewarded_at')->nullable();
                $table->foreignId('ebook_coupon_id')->nullable()->constrained('ebook_coupons')->nullOnDelete();
                $table->timestamps();

                $table->index(['user_id', 'status']);
            });
        }

        if (! Schema::hasTable('ebook_share_events')) {
            Schema::create('ebook_share_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ebook_share_campaign_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('library_item_id')->constrained('library_items')->cascadeOnDelete();
                $table->string('share_token', 64)->unique();
                $table->string('platform', 32)->nullable();
                $table->string('status', 32)->default('pending');
                $table->string('confirm_source', 32)->nullable();
                $table->timestamp('intent_at')->nullable();
                $table->timestamp('confirmed_at')->nullable();
                $table->string('referrer', 512)->nullable();
                $table->string('click_ip', 45)->nullable();
                $table->timestamps();

                $table->unique(['ebook_share_campaign_id', 'library_item_id'], 'ebook_share_events_campaign_item_uq');
                $table->index(['user_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ebook_share_events');
        Schema::dropIfExists('ebook_share_campaigns');
    }
};
