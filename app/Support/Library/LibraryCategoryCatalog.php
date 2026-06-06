<?php

namespace App\Support\Library;

use App\Models\LibraryCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class LibraryCategoryCatalog
{
    /**
     * Ensure all canonical library categories exist and return them keyed by normalized name.
     *
     * @return array<string, LibraryCategory>
     */
    public static function ensureCategoriesExist(): array
    {
        $indexed = [];

        foreach (LibraryMatchNormalizer::CANONICAL_CATEGORIES as $sortOrder => $canonicalName) {
            $category = static::findOrCreateCategory($canonicalName, $sortOrder + 1);
            $indexed[LibraryMatchNormalizer::normalizeCategoryKey($canonicalName)] = $category;
        }

        return $indexed;
    }

    public static function findByName(string $name): ?LibraryCategory
    {
        $canonical = LibraryMatchNormalizer::canonicalCategoryName($name);
        $key = LibraryMatchNormalizer::normalizeCategoryKey($canonical);

        return static::allCategoriesByNormalizedName()->get($key);
    }

    /**
     * @return Collection<string, LibraryCategory>
     */
    public static function allCategoriesByNormalizedName(): Collection
    {
        return LibraryCategory::query()
            ->get()
            ->keyBy(fn (LibraryCategory $category) => LibraryMatchNormalizer::normalizeCategoryKey($category->name));
    }

    private static function findOrCreateCategory(string $canonicalName, int $sortOrder): LibraryCategory
    {
        $key = LibraryMatchNormalizer::normalizeCategoryKey($canonicalName);
        $existing = static::allCategoriesByNormalizedName()->get($key);

        if ($existing) {
            if ($existing->name !== $canonicalName) {
                $existing->update(['name' => $canonicalName]);
            }

            if ((int) $existing->sort_order === 0) {
                $existing->update(['sort_order' => $sortOrder]);
            }

            return $existing->fresh();
        }

        return LibraryCategory::query()->create([
            'name' => $canonicalName,
            'slug' => static::uniqueSlug($canonicalName),
            'sort_order' => $sortOrder,
            'is_active' => true,
        ]);
    }

    private static function uniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        while (LibraryCategory::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$counter++;
        }

        return $slug;
    }
}
