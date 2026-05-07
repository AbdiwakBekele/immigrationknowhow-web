<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_providers', function (Blueprint $table) {
            if (! Schema::hasColumn('service_providers', 'state_license_document_path')) {
                $table->string('state_license_document_path')->nullable()->after('license_expiry');
            }

            if (! Schema::hasColumn('service_providers', 'state_license_document_name')) {
                $table->string('state_license_document_name')->nullable()->after('state_license_document_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('service_providers', function (Blueprint $table) {
            if (Schema::hasColumn('service_providers', 'state_license_document_name')) {
                $table->dropColumn('state_license_document_name');
            }

            if (Schema::hasColumn('service_providers', 'state_license_document_path')) {
                $table->dropColumn('state_license_document_path');
            }
        });
    }
};
