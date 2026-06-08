<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_payments', function (Blueprint $table) {
            $table->string('purchase_source', 32)->nullable()->after('currency');
            $table->string('apple_transaction_id', 64)->nullable()->unique()->after('purchase_source');
        });
    }

    public function down(): void
    {
        Schema::table('ad_payments', function (Blueprint $table) {
            $table->dropColumn([
                'purchase_source',
                'apple_transaction_id',
            ]);
        });
    }
};
