<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_embeds', function (Blueprint $table) {
            $table->string('source', 16)->default('embed')->after('created_by');
            $table->string('file_path')->nullable()->after('thumbnail_url');
            $table->string('file_name')->nullable();
            $table->string('file_mime', 191)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('video_embeds', function (Blueprint $table) {
            $table->dropColumn([
                'source',
                'file_path',
                'file_name',
                'file_mime',
                'file_size',
            ]);
        });
    }
};
