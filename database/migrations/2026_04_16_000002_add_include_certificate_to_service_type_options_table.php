<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_type_options', function (Blueprint $table) {
            if (! Schema::hasColumn('service_type_options', 'include_certificate')) {
                $table->boolean('include_certificate')->default(false)->after('for_provider');
            }
        });
    }

    public function down(): void
    {
        Schema::table('service_type_options', function (Blueprint $table) {
            if (Schema::hasColumn('service_type_options', 'include_certificate')) {
                $table->dropColumn('include_certificate');
            }
        });
    }
};
