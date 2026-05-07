<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_type_options', function (Blueprint $table) {
            if (! Schema::hasColumn('service_type_options', 'requires_background_check')) {
                $table->boolean('requires_background_check')->default(false)->after('include_certificate');
            }
        });

        DB::table('service_type_options')
            ->whereIn('value', ['pet_sitter', 'babysitter', 'tutor'])
            ->update(['requires_background_check' => true]);
    }

    public function down(): void
    {
        Schema::table('service_type_options', function (Blueprint $table) {
            if (Schema::hasColumn('service_type_options', 'requires_background_check')) {
                $table->dropColumn('requires_background_check');
            }
        });
    }
};
