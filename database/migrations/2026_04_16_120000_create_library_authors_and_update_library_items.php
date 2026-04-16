<?php

use App\Models\LibraryAuthor;
use App\Models\LibraryItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        Schema::create('library_authors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::table('library_items', function (Blueprint $table) {
            $table->foreignId('author_id')
                ->nullable()
                ->after('description')
                ->constrained('library_authors')
                ->nullOnDelete();
        });

        LibraryItem::query()
            ->whereNotNull('author')
            ->where('author', '!=', '')
            ->orderBy('id')
            ->chunkById(100, function ($items) {
                foreach ($items as $item) {
                    /** @var LibraryItem $item */
                    $name = trim((string) $item->getRawOriginal('author'));
                    if ($name === '') {
                        continue;
                    }

                    $author = LibraryAuthor::query()
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                        ->first();

                    if (! $author) {
                        $author = LibraryAuthor::create(['name' => $name]);
                    }

                    $item->forceFill(['author_id' => $author->id])->saveQuietly();
                }
            });

        Schema::table('library_items', function (Blueprint $table) use ($driver) {
            if ($driver === 'mysql') {
                $table->dropFullText(['title', 'description', 'author']);
            }

            if ($driver === 'mysql') {
                DB::statement('ALTER TABLE library_items MODIFY description LONGTEXT NULL');
            }

            $table->dropColumn('author');

            if ($driver === 'mysql') {
                $table->fullText(['title', 'description']);
            }
        });
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        Schema::table('library_items', function (Blueprint $table) use ($driver) {
            if ($driver === 'mysql') {
                $table->dropFullText(['title', 'description']);
            }

            $table->string('author')->nullable()->after('description');

            if ($driver === 'mysql') {
                $table->fullText(['title', 'description', 'author']);
            }

            $table->dropForeign(['author_id']);
            $table->dropColumn('author_id');
        });

        Schema::dropIfExists('library_authors');
    }
};
