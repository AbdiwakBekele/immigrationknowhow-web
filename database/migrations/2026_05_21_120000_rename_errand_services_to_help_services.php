<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('service_type_options')) {
            return;
        }

        DB::table('service_type_options')
            ->where('value', 'errand_services')
            ->update([
                'label' => 'Help services',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('service_type_options')) {
            return;
        }

        DB::table('service_type_options')
            ->where('value', 'errand_services')
            ->update([
                'label' => 'Errand services',
                'updated_at' => now(),
            ]);
    }
};
