<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('old_wp_user_id')->nullable()->unique();
            $table->string('import_source')->nullable();
            $table->timestamp('imported_at')->nullable();
        });

        Schema::table('community_posts', function (Blueprint $table) {
            $table->unsignedBigInteger('old_wp_post_id')->nullable()->unique();
            $table->unsignedBigInteger('old_wp_author_id')->nullable();
            $table->unsignedBigInteger('old_wp_space_id')->nullable();
            $table->foreignId('contributor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('slug')->nullable()->unique();
            $table->string('import_source')->nullable();
            $table->json('import_meta')->nullable();
            $table->timestamp('imported_at')->nullable();
        });

        Schema::table('community_comments', function (Blueprint $table) {
            $table->unsignedBigInteger('old_wp_comment_id')->nullable()->unique();
            $table->unsignedBigInteger('old_wp_post_id')->nullable();
            $table->unsignedBigInteger('old_wp_user_id')->nullable();
            $table->unsignedBigInteger('old_wp_parent_comment_id')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('community_comments')->nullOnDelete();
            $table->string('import_source')->nullable();
            $table->timestamp('imported_at')->nullable();
        });

        Schema::table('community_post_reactions', function (Blueprint $table) {
            $table->unsignedBigInteger('old_wp_reaction_id')->nullable()->unique();
            $table->unsignedBigInteger('old_wp_post_id')->nullable();
            $table->unsignedBigInteger('old_wp_user_id')->nullable();
            $table->string('import_source')->nullable();
            $table->timestamp('imported_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('community_post_reactions', function (Blueprint $table) {
            $table->dropUnique('community_post_reactions_old_wp_reaction_id_unique');
            $table->dropColumn([
                'old_wp_reaction_id',
                'old_wp_post_id',
                'old_wp_user_id',
                'import_source',
                'imported_at',
            ]);
        });

        Schema::table('community_comments', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropUnique('community_comments_old_wp_comment_id_unique');
            $table->dropColumn([
                'old_wp_comment_id',
                'old_wp_post_id',
                'old_wp_user_id',
                'old_wp_parent_comment_id',
                'parent_id',
                'import_source',
                'imported_at',
            ]);
        });

        Schema::table('community_posts', function (Blueprint $table) {
            $table->dropForeign(['contributor_user_id']);
            $table->dropUnique('community_posts_old_wp_post_id_unique');
            $table->dropUnique('community_posts_slug_unique');
            $table->dropColumn([
                'old_wp_post_id',
                'old_wp_author_id',
                'old_wp_space_id',
                'contributor_user_id',
                'slug',
                'import_source',
                'import_meta',
                'imported_at',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_old_wp_user_id_unique');
            $table->dropColumn([
                'old_wp_user_id',
                'import_source',
                'imported_at',
            ]);
        });
    }
};
