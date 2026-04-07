<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::table('service_providers', function (Blueprint $table) {
        if (!Schema::hasColumn('service_providers', 'is_verified')) {
            $table->boolean('is_verified')->default(false)->after('is_active');
        }
        if (!Schema::hasColumn('service_providers', 'is_featured')) {
            $table->boolean('is_featured')->default(false)->after('is_verified');
        }
        if (!Schema::hasColumn('service_providers', 'average_rating')) {
            $table->decimal('average_rating', 2, 1)->nullable()->after('is_featured');
        }
        if (!Schema::hasColumn('service_providers', 'reviews_count')) {
            $table->unsignedInteger('reviews_count')->default(0)->after('average_rating');
        }
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_providers', function (Blueprint $table) {
            //
        });
    }
};
