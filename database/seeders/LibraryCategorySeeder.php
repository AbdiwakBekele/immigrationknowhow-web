<?php

namespace Database\Seeders;

use App\Support\Library\LibraryCategoryCatalog;
use Illuminate\Database\Seeder;

class LibraryCategorySeeder extends Seeder
{
    /**
     * Seed canonical ebook/library categories (also applied by migration 2026_06_06_120000_seed_canonical_library_categories).
     *
     * Safe to run multiple times — categories are found or created by normalized name.
     */
    public function run(): void
    {
        $categories = LibraryCategoryCatalog::ensureCategoriesExist();

        $this->command?->info('Library categories ready: '.count($categories));
    }
}
