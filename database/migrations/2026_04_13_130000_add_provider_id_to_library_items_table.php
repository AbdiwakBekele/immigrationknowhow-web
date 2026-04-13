<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('library_items', function (Blueprint $table) {
            $table->foreignId('provider_id')
                ->nullable()
                ->after('uuid')
                ->constrained('service_providers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('library_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('provider_id');
        });
    }
};
