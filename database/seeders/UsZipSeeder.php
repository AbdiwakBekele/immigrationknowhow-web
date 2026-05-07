<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class UsZipSeeder extends Seeder
{
    private const BATCH_SIZE = 1000;

    /**
     * Import a MariaDB/Adminer uszips SQL dump without replacing the migrated table schema.
     */
    public function run(): void
    {
        if (! Schema::hasTable('uszips')) {
            throw new RuntimeException('The uszips table does not exist. Run migrations before seeding.');
        }

        try {
            $path = $this->resolveDumpPath();
        } catch (RuntimeException $e) {
            // This dataset is optional for local development; skip without failing the entire seeding run.
            $this->command?->warn('US ZIP seed skipped: '.$e->getMessage());
            return;
        }
        $availableColumns = Schema::getColumnListing('uszips');
        $targetColumns = collect([
            'zip',
            'lat',
            'lng',
            'city',
            'state',
            'state_id',
            'state_name',
            'county_name',
            'county',
            'timezone',
        ])->filter(fn (string $column) => in_array($column, $availableColumns, true))->values()->all();

        if (! in_array('zip', $targetColumns, true) || ! in_array('city', $targetColumns, true)) {
            throw new RuntimeException('The uszips table is missing required zip or city columns.');
        }

        $this->command?->info("Importing US ZIP data from {$path}");

        DB::table('uszips')->truncate();

        $handle = gzopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Unable to open compressed US ZIP dump at {$path}.");
        }

        $sourceColumns = [];
        $batch = [];
        $imported = 0;

        try {
            while (! gzeof($handle)) {
                $line = trim((string) gzgets($handle));
                if ($line === '') {
                    continue;
                }

                if (str_starts_with($line, 'INSERT INTO')) {
                    $sourceColumns = $this->parseInsertColumns($line);
                    continue;
                }

                if ($sourceColumns === [] || ! str_starts_with($line, '(')) {
                    continue;
                }

                $row = $this->parseRow($line, $sourceColumns, $targetColumns);
                if ($row === null) {
                    continue;
                }

                $batch[] = $row;

                if (count($batch) >= self::BATCH_SIZE) {
                    DB::table('uszips')->insert($batch);
                    $imported += count($batch);
                    $batch = [];
                }
            }
        } finally {
            gzclose($handle);
        }

        if ($batch !== []) {
            DB::table('uszips')->insert($batch);
            $imported += count($batch);
        }

        $this->command?->info("Imported {$imported} US ZIP rows.");
    }

    private function resolveDumpPath(): string
    {
        $candidates = array_filter([
            $_SERVER['USZIPS_SQL_PATH'] ?? null,
            $_ENV['USZIPS_SQL_PATH'] ?? null,
            getenv('USZIPS_SQL_PATH') ?: null,
            database_path('seeders/data/uszips.sql.gz'),
            storage_path('app/uszips.sql.gz'),
            base_path('uszips.sql.gz'),
        ]);

        foreach ($candidates as $path) {
            if (is_string($path) && is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        $prettyCandidates = implode(PHP_EOL . '  - ', array_map(
            static fn (string $path) => $path,
            array_values(array_filter($candidates, 'is_string'))
        ));

        throw new RuntimeException(
            "Unable to find uszips.sql.gz." . PHP_EOL .
            "Set USZIPS_SQL_PATH to an absolute path (Windows example: C:\\Users\\you\\Downloads\\uszips.sql.gz) " .
            "or place the file in one of these locations:" . PHP_EOL .
            "  - {$prettyCandidates}"
        );
    }

    /**
     * @return list<string>
     */
    private function parseInsertColumns(string $line): array
    {
        if (! preg_match('/INSERT INTO [`"]?uszips[`"]?\s*\((.+)\)\s*VALUES/i', $line, $matches)) {
            return [];
        }

        preg_match_all('/`([^`]+)`/', $matches[1], $columnMatches);

        return $columnMatches[1] ?? [];
    }

    /**
     * @param list<string> $sourceColumns
     * @param list<string> $targetColumns
     */
    private function parseRow(string $line, array $sourceColumns, array $targetColumns): ?array
    {
        $line = rtrim($line, ',;');
        if (! str_ends_with($line, ')')) {
            return null;
        }

        $values = str_getcsv(substr($line, 1, -1), ',', "'", '\\');
        if (count($values) !== count($sourceColumns)) {
            return null;
        }

        $source = array_combine($sourceColumns, array_map([$this, 'normalizeSqlValue'], $values));
        if (! is_array($source)) {
            return null;
        }

        $row = [
            'zip' => $source['zip'] ?? null,
            'lat' => $source['lat'] ?? null,
            'lng' => $source['lng'] ?? null,
            'city' => $source['city'] ?? null,
            'state' => $source['state_id'] ?? null,
            'state_id' => $source['state_id'] ?? null,
            'state_name' => $source['state_name'] ?? null,
            'county_name' => $source['county_name'] ?? null,
            'county' => $source['county_name'] ?? null,
            'timezone' => $source['timezone'] ?? null,
        ];

        if (! $row['zip'] || ! $row['city']) {
            return null;
        }

        return array_intersect_key($row, array_flip($targetColumns));
    }

    private function normalizeSqlValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '' || strtoupper($value) === 'NULL') {
            return null;
        }

        return str_replace(["\\'", '\\"'], ["'", '"'], $value);
    }
}
