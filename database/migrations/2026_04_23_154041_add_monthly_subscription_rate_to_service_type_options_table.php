<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_type_options', function (Blueprint $table) {
            if (! Schema::hasColumn('service_type_options', 'monthly_subscription_rate')) {
                $table->decimal('monthly_subscription_rate', 8, 2)->default(0)->after('sort_order');
            }
        });
    }

    public function down(): void
    {
        Schema::table('service_type_options', function (Blueprint $table) {
            if (Schema::hasColumn('service_type_options', 'monthly_subscription_rate')) {
                $table->dropColumn('monthly_subscription_rate');
            }
        });
    }
};
