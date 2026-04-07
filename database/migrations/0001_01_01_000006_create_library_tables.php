<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Library categories
        Schema::create('library_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Library items (ebooks, audiobooks)
        Schema::create('library_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('category_id')->nullable()->constrained('library_categories')->nullOnDelete();
            
            // Basic Info
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('type'); // ebook, audiobook
            $table->text('description')->nullable();
            $table->string('author')->nullable();
            $table->string('publisher')->nullable();
            $table->year('publication_year')->nullable();
            $table->string('isbn')->nullable();
            $table->json('tags')->nullable();
            
            // Files
            $table->string('file_path');
            $table->string('file_name');
            $table->bigInteger('file_size')->nullable();
            $table->string('file_type')->nullable(); // pdf, mp3, etc
            $table->string('cover_image')->nullable();
            
            // Audio specific
            $table->integer('duration_seconds')->nullable();
            $table->string('narrator')->nullable();
            
            // Access Control
            $table->boolean('is_premium')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            
            // Stats
            $table->integer('download_count')->default(0);
            $table->integer('view_count')->default(0);
            
            // SEO
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['type', 'is_active']);
            $table->index(['category_id', 'is_active']);
            $table->index('is_featured');
            $table->fullText(['title', 'description', 'author']);
        });

        // User library access/downloads
        Schema::create('library_user_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('library_item_id')->constrained()->onDelete('cascade');
            $table->timestamp('last_accessed_at')->nullable();
            $table->integer('access_count')->default(0);
            $table->json('progress')->nullable(); // For audio: position, for ebook: page
            $table->boolean('is_favorite')->default(false);
            $table->timestamps();
            
            $table->unique(['user_id', 'library_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_user_access');
        Schema::dropIfExists('library_items');
        Schema::dropIfExists('library_categories');
    }
};
