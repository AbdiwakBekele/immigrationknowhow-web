<?php

namespace Database\Seeders;

use App\Models\LibraryAuthor;
use App\Models\LibraryItem;
use Illuminate\Database\Seeder;

class LibraryAuthorSeeder extends Seeder
{
    public const TAYO_OBATUSIN_CANONICAL_NAME = 'Tayo Obatusin, MDFAPA, Assistant Clinical Professor of Psychiatry';

    /** @var string Case-insensitive LIKE pattern for author names containing "Tayo". */
    public const TAYO_NAME_MATCH = '%tayo%';

    /**
     * Ensure the canonical Tayo Obatusin author record exists and normalize legacy name variants.
     */
    public function run(): void
    {
        $canonicalName = self::TAYO_OBATUSIN_CANONICAL_NAME;

        $matchingAuthors = LibraryAuthor::query()
            ->whereRaw('LOWER(name) LIKE ?', [self::TAYO_NAME_MATCH])
            ->orderBy('id')
            ->get();

        if ($matchingAuthors->isEmpty()) {
            LibraryAuthor::create(['name' => $canonicalName]);

            return;
        }

        $primary = $matchingAuthors->first();
        $primary->update(['name' => $canonicalName]);

        foreach ($matchingAuthors->skip(1) as $duplicate) {
            LibraryItem::query()
                ->where('author_id', $duplicate->id)
                ->update(['author_id' => $primary->id]);

            $duplicate->delete();
        }
    }
}
