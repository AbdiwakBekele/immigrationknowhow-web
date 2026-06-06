<?php

namespace App\Support\Library;

use App\Models\LibraryItem;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Output\OutputInterface;

class LibraryItemCategoryRegionUpdater
{
    private int $processed = 0;

    private int $updated = 0;

    private int $unchanged = 0;

    private int $notFound = 0;

    private int $ambiguous = 0;

    /** @var array<int, true> */
    private array $touchedItemIds = [];

    public function __construct(private readonly ?OutputInterface $output = null) {}

    /**
     * @param  list<array{match_title: string, category: string, region: string}>  $entries
     * @return array{processed: int, updated: int, unchanged: int, not_found: int, ambiguous: int}
     */
    public function apply(array $entries): array
    {
        LibraryCategoryCatalog::ensureCategoriesExist();
        $titleIndex = $this->buildTitleIndex();

        foreach ($entries as $index => $entry) {
            $this->processEntry($entry, $index + 1, $titleIndex);
        }

        return [
            'processed' => $this->processed,
            'updated' => $this->updated,
            'unchanged' => $this->unchanged,
            'not_found' => $this->notFound,
            'ambiguous' => $this->ambiguous,
        ];
    }

    /**
     * @param  array{match_title: string, category: string, region: string}  $entry
     * @param  array{exact: array<string, array<int, LibraryItem>>, compact: array<string, array<int, LibraryItem>>}  $titleIndex
     */
    private function processEntry(array $entry, int $lineNumber, array $titleIndex): void
    {
        $this->processed++;

        $title = trim($entry['match_title']);
        $categoryName = trim($entry['category']);
        $regionWritten = trim($entry['region']);

        if ($title === '') {
            $this->log("Entry {$lineNumber}: skipped — empty match_title.");
            $this->notFound++;

            return;
        }

        $match = $this->findUniqueItemByTitle($title, $titleIndex);
        if ($match === null) {
            $this->log("Entry {$lineNumber}: not found — \"{$title}\".");
            $this->notFound++;

            return;
        }

        if (! empty($match['ambiguous'])) {
            $this->log("Entry {$lineNumber}: ambiguous match for \"{$title}\" ({$match['count']} items).");
            $this->ambiguous++;

            return;
        }

        /** @var LibraryItem $item */
        $item = $match['item'];
        $category = LibraryCategoryCatalog::findByName($categoryName);

        if (! $category) {
            $this->log("Entry {$lineNumber}: unknown category \"{$categoryName}\" for \"{$item->title}\".");
            $this->notFound++;

            return;
        }

        $regionPayload = LibraryMatchNormalizer::parseRegionWritten($regionWritten);
        // Full replacement of library_items.regions — never merged with previous checkboxes.
        $newRegions = $regionPayload['regions'];

        $categoryChanged = (int) $item->category_id !== (int) $category->id;
        $regionsChanged = ! LibraryMatchNormalizer::regionsAreEqual($item->regions, $newRegions);

        if (! $categoryChanged && ! $regionsChanged) {
            $this->unchanged++;

            return;
        }

        if (isset($this->touchedItemIds[$item->id])) {
            $this->log("Entry {$lineNumber}: duplicate mapping for item #{$item->id} \"{$item->title}\".");
        }

        DB::transaction(function () use ($item, $category, $newRegions): void {
            $item->forceFill([
                'category_id' => $category->id,
                'regions' => $newRegions,
            ])->save();
        });

        $regionLabel = $regionPayload['all']
            ? 'All (regions = null)'
            : json_encode($newRegions);

        $this->log(
            "Entry {$lineNumber}: updated #{$item->id} \"{$item->title}\""
            ." → category \"{$category->name}\", regions {$regionLabel}"
        );

        $this->touchedItemIds[$item->id] = true;
        $this->updated++;
    }

    /**
     * @param  array{exact: array<string, array<int, LibraryItem>>, compact: array<string, array<int, LibraryItem>>}  $titleIndex
     * @return array{item: LibraryItem}|array{ambiguous: true, count: int}|null
     */
    private function findUniqueItemByTitle(string $title, array $titleIndex): ?array
    {
        $normalized = LibraryMatchNormalizer::normalizeTitle($title);
        if ($normalized !== '') {
            $exact = $this->resolveMatches($titleIndex['exact'][$normalized] ?? []);
            if ($exact !== null) {
                return $exact;
            }
        }

        $compact = LibraryMatchNormalizer::compactTitleKey($title);
        if ($compact !== '') {
            return $this->resolveMatches($titleIndex['compact'][$compact] ?? []);
        }

        return null;
    }

    /**
     * @param  array<int, LibraryItem>  $matches
     * @return array{item: LibraryItem}|array{ambiguous: true, count: int}|null
     */
    private function resolveMatches(array $matches): ?array
    {
        if (count($matches) === 1) {
            return ['item' => $matches[0]];
        }

        if (count($matches) > 1) {
            return ['ambiguous' => true, 'count' => count($matches)];
        }

        return null;
    }

    /**
     * @return array{exact: array<string, array<int, LibraryItem>>, compact: array<string, array<int, LibraryItem>>}
     */
    private function buildTitleIndex(): array
    {
        $exact = [];
        $compact = [];

        LibraryItem::query()
            ->ebooks()
            ->select(['id', 'title', 'category_id', 'regions'])
            ->orderBy('id')
            ->each(function (LibraryItem $item) use (&$exact, &$compact): void {
                $key = LibraryMatchNormalizer::normalizeTitle($item->title);
                if ($key !== '') {
                    $exact[$key][] = $item;
                }

                $compactKey = LibraryMatchNormalizer::compactTitleKey($item->title);
                if ($compactKey !== '') {
                    $compact[$compactKey][] = $item;
                }
            });

        return [
            'exact' => $exact,
            'compact' => $compact,
        ];
    }

    private function log(string $message): void
    {
        $this->output?->writeln($message);
    }
}
