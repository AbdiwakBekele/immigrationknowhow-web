<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('uploaded');
            $table->string('import_type')->default('wp_community');
            $table->string('users_file_path')->nullable();
            $table->string('posts_file_path')->nullable();
            $table->string('comments_file_path')->nullable();
            $table->string('reactions_file_path')->nullable();
            $table->unsignedInteger('total_users')->default(0);
            $table->unsignedInteger('imported_users')->default(0);
            $table->unsignedInteger('skipped_users')->default(0);
            $table->unsignedInteger('total_posts')->default(0);
            $table->unsignedInteger('imported_posts')->default(0);
            $table->unsignedInteger('skipped_posts')->default(0);
            $table->unsignedInteger('total_comments')->default(0);
            $table->unsignedInteger('imported_comments')->default(0);
            $table->unsignedInteger('skipped_comments')->default(0);
            $table->unsignedInteger('total_reactions')->default(0);
            $table->unsignedInteger('imported_reactions')->default(0);
            $table->unsignedInteger('skipped_reactions')->default(0);
            $table->unsignedInteger('errors_count')->default(0);
            $table->json('summary')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('import_type');
        });

        Schema::create('community_import_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_import_batch_id')->constrained('community_import_batches')->cascadeOnDelete();
            $table->string('file_type');
            $table->unsignedInteger('row_number')->nullable();
            $table->unsignedBigInteger('old_wp_id')->nullable();
            $table->string('severity')->default('error');
            $table->text('message');
            $table->json('row_data')->nullable();
            $table->timestamps();

            $table->index(['community_import_batch_id', 'severity']);
            $table->index(['file_type', 'row_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_import_errors');
        Schema::dropIfExists('community_import_batches');
    }
};
