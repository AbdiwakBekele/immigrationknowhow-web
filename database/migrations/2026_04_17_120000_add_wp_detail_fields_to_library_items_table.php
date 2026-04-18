<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('library_items', function (Blueprint $table) {
            if (! Schema::hasColumn('library_items', 'published_at')) {
                $table->date('published_at')->nullable()->after('publication_year');
            }

            if (! Schema::hasColumn('library_items', 'page_count')) {
                $table->unsignedInteger('page_count')->nullable()->after('isbn');
            }

            if (! Schema::hasColumn('library_items', 'language')) {
                $table->string('language')->nullable()->after('page_count');
            }

            if (! Schema::hasColumn('library_items', 'estimated_reading_minutes')) {
                $table->unsignedInteger('estimated_reading_minutes')->nullable()->after('duration_seconds');
            }

            if (! Schema::hasColumn('library_items', 'difficulty_level')) {
                $table->string('difficulty_level')->nullable()->after('estimated_reading_minutes');
            }

            if (! Schema::hasColumn('library_items', 'recommended_age_group')) {
                $table->string('recommended_age_group')->nullable()->after('difficulty_level');
            }
        });
    }

    public function down(): void
    {
        $columns = [
            'published_at',
            'page_count',
            'language',
            'estimated_reading_minutes',
            'difficulty_level',
            'recommended_age_group',
        ];

        Schema::table('library_items', function (Blueprint $table) use ($columns) {
            $existing = array_values(array_filter(
                $columns,
                fn (string $column): bool => Schema::hasColumn('library_items', $column)
            ));

            if ($existing !== []) {
                $table->dropColumn($existing);
            }
        });
    }
};
