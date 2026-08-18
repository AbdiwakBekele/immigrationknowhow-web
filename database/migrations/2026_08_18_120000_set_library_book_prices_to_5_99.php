<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('library_items') || ! Schema::hasColumn('library_items', 'price')) {
            return;
        }

        DB::table('library_items')
            ->whereIn('type', ['ebook', 'audiobook'])
            ->where('price', 4.99)
            ->update([
                'price' => 5.99,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('library_items') || ! Schema::hasColumn('library_items', 'price')) {
            return;
        }

        DB::table('library_items')
            ->whereIn('type', ['ebook', 'audiobook'])
            ->where('price', 5.99)
            ->update([
                'price' => 4.99,
                'updated_at' => now(),
            ]);
    }
};
