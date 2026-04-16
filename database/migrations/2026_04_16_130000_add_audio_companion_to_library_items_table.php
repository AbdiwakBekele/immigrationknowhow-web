<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('library_items', function (Blueprint $table) {
            $table->string('audio_file_path')->nullable()->after('file_type');
            $table->string('audio_file_name')->nullable()->after('audio_file_path');
            $table->bigInteger('audio_file_size')->nullable()->after('audio_file_name');
            $table->string('audio_file_type')->nullable()->after('audio_file_size');
        });
    }

    public function down(): void
    {
        Schema::table('library_items', function (Blueprint $table) {
            $table->dropColumn([
                'audio_file_path',
                'audio_file_name',
                'audio_file_size',
                'audio_file_type',
            ]);
        });
    }
};
