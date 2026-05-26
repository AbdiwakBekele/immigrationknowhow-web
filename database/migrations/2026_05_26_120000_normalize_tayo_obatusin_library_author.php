<?php

use Database\Seeders\LibraryAuthorSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('library_authors')) {
            return;
        }

        (new LibraryAuthorSeeder)->run();
    }

    public function down(): void
    {
        // Data normalization is not reversed automatically.
    }
};
