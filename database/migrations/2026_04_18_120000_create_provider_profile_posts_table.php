<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_profile_posts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('service_provider_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->text('url');
            $table->string('title')->nullable();
            $table->text('caption')->nullable();
            $table->string('platform', 32)->nullable();
            $table->string('video_id', 128)->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->timestamps();

            $table->index(['service_provider_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_profile_posts');
    }
};
