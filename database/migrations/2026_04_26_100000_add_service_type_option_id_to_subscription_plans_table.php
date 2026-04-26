<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('subscription_plans', 'service_type_option_id')) {
                $table->foreignId('service_type_option_id')
                    ->nullable()
                    ->after('sort_order')
                    ->constrained('service_type_options')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            if (Schema::hasColumn('subscription_plans', 'service_type_option_id')) {
                $table->dropConstrainedForeignId('service_type_option_id');
            }
        });
    }
};
