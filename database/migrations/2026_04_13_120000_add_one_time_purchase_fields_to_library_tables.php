<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('library_items', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->nullable()->after('is_premium');
            $table->string('currency', 3)->default('USD')->after('price');
        });

        Schema::table('library_user_access', function (Blueprint $table) {
            $table->timestamp('purchased_at')->nullable()->after('is_favorite');
            $table->decimal('purchase_amount', 10, 2)->nullable()->after('purchased_at');
            $table->string('purchase_currency', 3)->nullable()->after('purchase_amount');
        });
    }

    public function down(): void
    {
        Schema::table('library_user_access', function (Blueprint $table) {
            $table->dropColumn(['purchased_at', 'purchase_amount', 'purchase_currency']);
        });

        Schema::table('library_items', function (Blueprint $table) {
            $table->dropColumn(['price', 'currency']);
        });
    }
};
