<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('library_items', function (Blueprint $table) {
            $table->string('ai_summary_status', 40)->nullable()->after('ai_summary_generated_at');
            $table->timestamp('ai_summary_attempted_at')->nullable()->after('ai_summary_status');
            $table->text('ai_summary_last_error')->nullable()->after('ai_summary_attempted_at');
        });
    }

    public function down(): void
    {
        Schema::table('library_items', function (Blueprint $table) {
            $table->dropColumn([
                'ai_summary_status',
                'ai_summary_attempted_at',
                'ai_summary_last_error',
            ]);
        });
    }
};

