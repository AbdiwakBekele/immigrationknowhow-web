<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->date('dv_lottery_open_from')->nullable()->after('dv_lottery_warning_text');
            $table->date('dv_lottery_open_to')->nullable()->after('dv_lottery_open_from');
            $table->boolean('dv_lottery_show_in_menu_after_close')->default(true)->after('dv_lottery_open_to');
        });
    }

    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->dropColumn([
                'dv_lottery_open_from',
                'dv_lottery_open_to',
                'dv_lottery_show_in_menu_after_close',
            ]);
        });
    }
};
