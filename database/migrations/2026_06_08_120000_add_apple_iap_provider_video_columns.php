<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->string('apple_product_id', 191)->nullable()->after('stripe_price_id');
        });

        Schema::table('provider_subscriptions', function (Blueprint $table) {
            $table->string('apple_product_id', 191)->nullable()->after('stripe_checkout_session_id');
            $table->string('apple_original_transaction_id', 64)->nullable()->after('apple_product_id');
            $table->string('apple_transaction_id', 64)->nullable()->after('apple_original_transaction_id');
        });

        Schema::table('video_embeds', function (Blueprint $table) {
            $table->string('apple_product_id', 191)->nullable()->after('currency');
        });

        Schema::table('video_user_access', function (Blueprint $table) {
            $table->string('purchase_source', 32)->nullable()->after('purchase_currency');
            $table->string('apple_transaction_id', 64)->nullable()->after('purchase_source');
            $table->string('apple_original_transaction_id', 64)->nullable()->after('apple_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn('apple_product_id');
        });

        Schema::table('provider_subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'apple_product_id',
                'apple_original_transaction_id',
                'apple_transaction_id',
            ]);
        });

        Schema::table('video_embeds', function (Blueprint $table) {
            $table->dropColumn('apple_product_id');
        });

        Schema::table('video_user_access', function (Blueprint $table) {
            $table->dropColumn([
                'purchase_source',
                'apple_transaction_id',
                'apple_original_transaction_id',
            ]);
        });
    }
};
