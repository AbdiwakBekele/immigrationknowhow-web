<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->string('dv_lottery_page_title')->nullable()->after('footer_tagline');
            $table->string('dv_lottery_page_subtitle')->nullable()->after('dv_lottery_page_title');
            $table->text('dv_lottery_description')->nullable()->after('dv_lottery_page_subtitle');
            $table->string('dv_lottery_official_url')->nullable()->after('dv_lottery_description');
            $table->string('dv_lottery_cta_label')->nullable()->after('dv_lottery_official_url');
            $table->text('dv_lottery_warning_text')->nullable()->after('dv_lottery_cta_label');
        });
    }

    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->dropColumn([
                'dv_lottery_page_title',
                'dv_lottery_page_subtitle',
                'dv_lottery_description',
                'dv_lottery_official_url',
                'dv_lottery_cta_label',
                'dv_lottery_warning_text',
            ]);
        });
    }
};
