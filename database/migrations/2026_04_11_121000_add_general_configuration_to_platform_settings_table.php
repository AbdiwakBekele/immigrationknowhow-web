<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->string('support_email')->nullable()->after('company_name');
            $table->string('support_phone')->nullable()->after('support_email');
            $table->string('support_address')->nullable()->after('support_phone');
            $table->string('site_tagline')->nullable()->after('admin_logo_path');
            $table->text('footer_tagline')->nullable()->after('site_tagline');
        });
    }

    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->dropColumn([
                'support_email',
                'support_phone',
                'support_address',
                'site_tagline',
                'footer_tagline',
            ]);
        });
    }
};