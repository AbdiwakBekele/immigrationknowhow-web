<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Affiliate Links
        Schema::create('affiliate_links', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('url');
            $table->string('tracking_code')->unique();
            
            // Categorization
            $table->string('category')->nullable(); // attorney, accountant, service, product
            $table->json('service_types')->nullable(); // Related service types
            
            // Display
            $table->string('logo')->nullable();
            $table->string('button_text')->default('Learn More');
            $table->json('placement')->nullable(); // Where to show: service_pages, content, resources
            
            // Tracking
            $table->integer('click_count')->default(0);
            $table->integer('conversion_count')->default(0);
            
            // Commission (if tracking)
            $table->string('commission_type')->nullable(); // flat, percentage
            $table->decimal('commission_value', 10, 2)->nullable();
            
            // Status
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->integer('sort_order')->default(0);
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['category', 'is_active']);
            $table->index('is_featured');
        });

        // Affiliate click tracking
        Schema::create('affiliate_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_link_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('referrer')->nullable();
            $table->string('page_source')->nullable();
            $table->timestamps();
            
            $table->index(['affiliate_link_id', 'created_at']);
        });

        // Video Embeds
        Schema::create('video_embeds', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            
            // Video Details
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('platform'); // youtube, tiktok, vimeo
            $table->string('video_url');
            $table->string('video_id'); // Extracted video ID
            $table->string('embed_code')->nullable();
            $table->string('thumbnail_url')->nullable();
            
            // Categorization
            $table->string('category')->nullable();
            $table->json('tags')->nullable();
            
            // Stats
            $table->integer('view_count')->default(0);
            
            // Status
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['platform', 'is_active']);
            $table->index('is_featured');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_embeds');
        Schema::dropIfExists('affiliate_clicks');
        Schema::dropIfExists('affiliate_links');
    }
};
