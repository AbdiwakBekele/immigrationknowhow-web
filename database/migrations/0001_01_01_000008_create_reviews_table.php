<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('service_provider_id')->constrained()->onDelete('cascade');
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            
            // Rating
            $table->tinyInteger('rating'); // 1-5
            $table->text('comment')->nullable();
            
            // Detailed Ratings (optional)
            $table->tinyInteger('communication_rating')->nullable();
            $table->tinyInteger('expertise_rating')->nullable();
            $table->tinyInteger('value_rating')->nullable();
            $table->tinyInteger('responsiveness_rating')->nullable();
            
            // Response
            $table->text('provider_response')->nullable();
            $table->timestamp('provider_responded_at')->nullable();
            
            // Moderation
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_approved')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->text('moderation_notes')->nullable();
            
            // Helpful votes
            $table->integer('helpful_count')->default(0);
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['service_provider_id', 'is_approved']);
            $table->index(['user_id', 'service_provider_id']);
        });

        // Review helpful votes
        Schema::create('review_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->boolean('is_helpful');
            $table->timestamps();
            
            $table->unique(['review_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_votes');
        Schema::dropIfExists('reviews');
    }
};
