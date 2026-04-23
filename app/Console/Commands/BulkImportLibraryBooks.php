<?php

namespace App\Console\Commands;

use App\Models\LibraryAuthor;
use App\Models\LibraryCategory;
use App\Models\LibraryItem;
use Illuminate\Console\Command;
use Illuminate\Http\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BulkImportLibraryBooks extends Command
{
    protected $signature = 'library:bulk-import
        {csv=storage/app/import/library_books.csv : CSV path relative to base path or absolute path}
        {source=storage/app/import/library-source : Folder containing PDFs, covers, and optional audio files}
        {--dry-run : Validate only; do not upload or write DB}
        {--overwrite : Replace existing files/DB values when slug already exists}
        {--disk=library_media : Destination filesystem disk}
        {--cover-dir=library/covers : Destination cover directory}
        {--file-dir=library/files : Destination PDF/audio directory}';

    protected $description = 'Bulk upload library covers/PDFs/audio to S3 and create/update LibraryItem rows.';

    public function handle(): int
    {
        $csvPath = $this->absolutePath((string) $this->argument('csv'));
        $sourceDir = rtrim($this->absolutePath((string) $this->argument('source')), DIRECTORY_SEPARATOR);

        if (! is_file($csvPath)) {
            $this->error("CSV not found: {$csvPath}");
            return self::FAILURE;
        }

        if (! is_dir($sourceDir)) {
            $this->error("Source folder not found: {$sourceDir}");
            return self::FAILURE;
        }

        $diskName = (string) $this->option('disk');
        $disk = Storage::disk($diskName);
        $dryRun = (bool) $this->option('dry-run');
        $overwrite = (bool) $this->option('overwrite');

        $rows = $this->readCsv($csvPath);
        $this->info('Rows found: '.count($rows));

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($rows as $index => $row) {
            $line = $index + 2;

            $title = trim((string) ($row['title'] ?? ''));
            $type = trim((string) ($row['type'] ?? 'ebook')) ?: 'ebook';
            $slug = Str::slug($title);

            if ($title === '' || $slug === '') {
                $this->warn("Line {$line}: skipped because title is missing.");
                $skipped++;
                continue;
            }

            if (! in_array($type, LibraryItem::supportedTypes(), true)) {
                $this->warn("Line {$line}: skipped {$title}; unsupported type {$type}.");
                $skipped++;
                continue;
            }

            $coverSource = $this->findSourceFile($sourceDir, (string) ($row['cover_filename'] ?? ''));
            $pdfSource = $this->findSourceFile($sourceDir, (string) ($row['pdf_filename'] ?? ''));
            $audioSource = $this->findSourceFile($sourceDir, (string) ($row['audio_filename'] ?? ''));

            if (! $coverSource) {
                $this->warn("Line {$line}: missing cover for {$title}");
                $skipped++;
                continue;
            }

            if ($type === 'ebook' && ! $pdfSource) {
                $this->warn("Line {$line}: missing PDF for {$title}");
                $skipped++;
                continue;
            }

            $coverExt = strtolower(pathinfo($coverSource, PATHINFO_EXTENSION));
            $pdfExt = $pdfSource ? strtolower(pathinfo($pdfSource, PATHINFO_EXTENSION)) : null;
            $audioExt = $audioSource ? strtolower(pathinfo($audioSource, PATHINFO_EXTENSION)) : null;

            if (! in_array($coverExt, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                $this->warn("Line {$line}: invalid cover extension for {$title}");
                $skipped++;
                continue;
            }

            if ($type === 'ebook' && $pdfExt !== 'pdf') {
                $this->warn("Line {$line}: ebook file must be PDF for {$title}");
                $skipped++;
                continue;
            }

            if ($audioSource && ! in_array($audioExt, LibraryItem::allowedExtensionsFor('audiobook'), true)) {
                $this->warn("Line {$line}: invalid audio extension for {$title}");
                $skipped++;
                continue;
            }

            $coverPath = trim((string) $this->option('cover-dir'), '/')."/{$slug}.{$coverExt}";
            $filePath = trim((string) $this->option('file-dir'), '/')."/{$slug}.{$pdfExt}";
            $audioPath = $audioSource ? trim((string) $this->option('file-dir'), '/')."/{$slug}-audio.{$audioExt}" : null;

            $existing = LibraryItem::where('slug', $slug)->first();

            if ($existing && ! $overwrite) {
                $this->line("Line {$line}: exists, skipped {$title}. Use --overwrite to update.");
                $skipped++;
                continue;
            }

            $this->line(($dryRun ? '[DRY RUN] ' : '').($existing ? 'Update: ' : 'Create: ').$title);

            if ($dryRun) {
                continue;
            }

            DB::transaction(function () use (
                $disk,
                $coverSource,
                $pdfSource,
                $audioSource,
                $coverPath,
                $filePath,
                $audioPath,
                $row,
                $title,
                $type,
                $slug,
                $pdfExt,
                $audioExt,
                $existing
            ) {
                $this->putLocalFile($disk, $coverPath, $coverSource);

                if ($pdfSource) {
                    $this->putLocalFile($disk, $filePath, $pdfSource);
                }

                if ($audioSource && $audioPath) {
                    $this->putLocalFile($disk, $audioPath, $audioSource);
                }

                $authorId = $this->resolveAuthorId((string) ($row['author'] ?? ''));
                $categoryId = $this->resolveCategoryId((string) ($row['category'] ?? ''));

                LibraryItem::updateOrCreate(
                    ['slug' => $slug],
                    [
                        'title' => $title,
                        'type' => $type,
                        'category_id' => $categoryId,
                        'author_id' => $authorId,
                        'description' => (string) ($row['description'] ?? ''),
                        'language' => (string) ($row['language'] ?? 'English'),
                        'cover_image' => $coverPath,
                        'file_path' => $filePath,
                        'file_name' => basename((string) $pdfSource),
                        'file_size' => $pdfSource ? filesize($pdfSource) : null,
                        'file_type' => $pdfExt,
                        'audio_file_path' => $audioPath,
                        'audio_file_name' => $audioSource ? basename($audioSource) : null,
                        'audio_file_size' => $audioSource ? filesize($audioSource) : null,
                        'audio_file_type' => $audioExt,
                        'price' => is_numeric($row['price'] ?? null) ? $row['price'] : 0,
                        'currency' => strtoupper((string) ($row['currency'] ?? 'USD')) ?: 'USD',
                        'is_active' => filter_var($row['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
                        'is_featured' => filter_var($row['is_featured'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    ]
                );
            });

            $existing ? $updated++ : $created++;
        }

        $this->newLine();
        $this->info("Done. Created: {$created}. Updated: {$updated}. Skipped: {$skipped}.");

        return self::SUCCESS;
    }

    private function absolutePath(string $path): string
    {
        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\/\\\\]/', $path)) {
            return $path;
        }

        return base_path($path);
    }

    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        $header = null;
        $rows = [];

        while (($data = fgetcsv($handle)) !== false) {
            if ($header === null) {
                $header = array_map(fn ($value) => trim((string) $value), $data);
                continue;
            }

            if ($data === [null] || count(array_filter($data, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $rows[] = array_combine($header, array_pad($data, count($header), ''));
        }

        fclose($handle);

        return $rows;
    }

    private function findSourceFile(string $sourceDir, string $filename): ?string
    {
        $filename = trim($filename);

        if ($filename === '') {
            return null;
        }

        $direct = $sourceDir.DIRECTORY_SEPARATOR.$filename;

        if (is_file($direct)) {
            return $direct;
        }

        // Fallback: Finder-safe search if macOS changed punctuation/spaces slightly.
        $target = $this->normalizeFileName($filename);

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($sourceDir)) as $file) {
            if (! $file->isFile()) {
                continue;
            }

            if ($this->normalizeFileName($file->getFilename()) === $target) {
                return $file->getPathname();
            }
        }

        return null;
    }

    private function normalizeFileName(string $name): string
    {
        $name = strtolower($name);
        $name = str_replace(['’', "'", '“', '”', '"'], '', $name);
        $name = preg_replace('/[^a-z0-9.]+/', '', $name);

        return $name ?: '';
    }

    private function putLocalFile($disk, string $destinationPath, string $sourcePath): void
    {
        $stream = fopen($sourcePath, 'rb');

        try {
            $disk->put($destinationPath, $stream, ['visibility' => 'private']);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    private function resolveAuthorId(string $name): ?int
    {
        $name = trim($name);

        if ($name === '') {
            return null;
        }

        return LibraryAuthor::firstOrCreate(['name' => $name])->id;
    }

    private function resolveCategoryId(string $name): ?int
    {
        $name = trim($name);

        if ($name === '') {
            return null;
        }

        return LibraryCategory::firstOrCreate([
            'slug' => Str::slug($name),
        ], [
            'name' => $name,
        ])->id;
    }
}
