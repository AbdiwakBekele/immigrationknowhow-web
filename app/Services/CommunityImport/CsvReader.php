<?php

namespace App\Services\CommunityImport;

use App\Services\CommunityImport\CommunityImportValidator;
use Generator;
use Illuminate\Support\Facades\Log;
use League\Csv\Reader;
use RuntimeException;
use Throwable;

class CsvReader
{
    private const POST_LEADING_FIELD_COUNT = 6;
    private const POST_TRAILING_FIELD_COUNT = 9;

    public const FILE_POSTS = 'posts';

    /** @var array<string, array<int, array{row_number:int,data:array<string, string>}>> */
    private static array $cachedPostRowsByPath = [];

    public static function clearCachedPostRows(): void
    {
        self::$cachedPostRowsByPath = [];
    }

    public function headers(string $path, ?string $fileType = null): array
    {
        try {
            return $this->normalizedHeaders($this->reader($path)->getHeader());
        } catch (Throwable $throwable) {
            throw new RuntimeException("Unable to read CSV headers from {$path}: {$throwable->getMessage()}", 0, $throwable);
        }
    }

    /**
     * @return Generator<int, array{row_number:int,data:array<string, string>}>
     */
    public function rows(string $path, ?string $fileType = null): Generator
    {
        $rows = $fileType === self::FILE_POSTS
            ? $this->readPostRows($path)
            : $this->readRawRows($path);

        foreach ($rows as $row) {
            yield $row;
        }
    }

    /**
     * @return array<int, array{row_number:int,data:array<string, string>}>
     */
    public function all(string $path, ?string $fileType = null): array
    {
        return iterator_to_array($this->rows($path, $fileType), false);
    }

    /**
     * @return array<int, array{row_number:int,data:array<string, string>}>
     */
    private function readPostRows(string $path): array
    {
        if (isset(self::$cachedPostRowsByPath[$path])) {
            return self::$cachedPostRowsByPath[$path];
        }

        $leagueRows = $this->mergeSplitPostRows($path, $this->readRawRows($path));
        $leagueCount = count($leagueRows);
        $misalignedCount = 0;

        foreach ($leagueRows as $row) {
            if ($this->postRowLooksMisaligned($row['data'])) {
                $misalignedCount++;
            }
        }

        $records = $leagueRows;

        if ($misalignedCount > 0) {
            Log::info('community.import.csv.posts.column_aware_reparse', [
                'path' => $path,
                'league_record_count' => $leagueCount,
                'misaligned_count' => $misalignedCount,
            ]);

            $reparsedRows = $this->mergeSplitPostRows(
                $path,
                $this->readPostRowsWithColumnAwareParser($path)
            );
            $reparseCount = count($reparsedRows);

            if ($reparseCount === $leagueCount) {
                $records = $reparsedRows;
            } else {
                Log::warning('community.import.csv.posts.reparse_discarded', [
                    'path' => $path,
                    'league_record_count' => $leagueCount,
                    'reparse_record_count' => $reparseCount,
                    'misaligned_count' => $misalignedCount,
                ]);

                $records = $this->mergeRepairedMisalignedPostRows($leagueRows, $reparsedRows);
            }
        }

        $this->assertPostRecordCountIntegrity($path, $records, $leagueCount);

        self::$cachedPostRowsByPath[$path] = $records;

        return $records;
    }

    /**
     * Keep every League row; replace only rows that are misaligned when a better parse exists.
     *
     * @param  array<int, array{row_number:int,data:array<string, string>}>  $leagueRows
     * @param  array<int, array{row_number:int,data:array<string, string>}>  $reparsedRows
     * @return array<int, array{row_number:int,data:array<string, string>}>
     */
    private function mergeRepairedMisalignedPostRows(array $leagueRows, array $reparsedRows): array
    {
        $repairedByPostId = [];

        foreach ($reparsedRows as $row) {
            $oldWpPostId = trim((string) ($row['data']['old_wp_post_id'] ?? ''));

            if ($oldWpPostId !== '' && ctype_digit($oldWpPostId)) {
                $repairedByPostId[$oldWpPostId] = $row['data'];
            }
        }

        $merged = [];

        foreach ($leagueRows as $row) {
            $oldWpPostId = trim((string) ($row['data']['old_wp_post_id'] ?? ''));

            if ($oldWpPostId !== ''
                && ctype_digit($oldWpPostId)
                && $this->postRowLooksMisaligned($row['data'])
                && isset($repairedByPostId[$oldWpPostId])
                && ! $this->postRowLooksMisaligned($repairedByPostId[$oldWpPostId])
            ) {
                $merged[] = [
                    'row_number' => $row['row_number'],
                    'data' => $repairedByPostId[$oldWpPostId],
                ];

                continue;
            }

            $merged[] = $row;
        }

        return $merged;
    }

    /**
     * @param  array<int, array{row_number:int,data:array<string, string>}>  $records
     */
    private function assertPostRecordCountIntegrity(string $path, array $records, int $leagueCount): void
    {
        $count = count($records);

        if ($leagueCount < 100) {
            return;
        }

        if ($count < 100) {
            throw new RuntimeException(
                "Posts CSV parser returned too few records ({$count}) for {$path}. Parser logic is broken."
            );
        }

        if ($count < (int) floor($leagueCount * 0.9)) {
            throw new RuntimeException(
                "Posts CSV parser returned fewer records ({$count}) than League CSV ({$leagueCount}) for {$path}."
            );
        }
    }

    /**
     * @return array<int, array{row_number:int,data:array<string, string>}>
     */
    private function readRawRows(string $path): array
    {
        try {
            $csv = $this->reader($path);
            $headers = $this->normalizedHeaders($csv->getHeader());
            $rows = [];

            foreach ($csv->getRecords() as $offset => $record) {
                $values = array_fill_keys($headers, '');

                foreach ($record as $key => $value) {
                    $cleanKey = $this->normalizeHeader((string) $key);
                    $values[$cleanKey] = is_string($value) ? trim($value) : '';
                }

                if (! $this->rowHasContent($values)) {
                    continue;
                }

                $rows[] = [
                    'row_number' => (int) $offset + 2,
                    'data' => $values,
                ];
            }

            return $rows;
        } catch (Throwable $throwable) {
            throw new RuntimeException("Unable to parse CSV rows from {$path}: {$throwable->getMessage()}", 0, $throwable);
        }
    }

    /**
     * @param  array<int, string>  $headers
     * @return array<int, array{row_number:int,data:array<string, string>}>
     */
    private function readPostRowsWithColumnAwareParser(string $path): array
    {
        $handle = fopen($path, 'rb');

        if (! $handle) {
            throw new RuntimeException("Unable to open CSV file: {$path}");
        }

        try {
            $headerRow = fgetcsv($handle, 0, ',', '"', '\\');
            $headers = $headerRow === false ? [] : $this->normalizedHeaders($headerRow);

            $buffer = null;
            $bufferRowNumber = null;
            $physicalLineNumber = 1;
            $rows = [];

            while (($line = fgets($handle)) !== false) {
                $physicalLineNumber++;
                $line = rtrim($line, "\r\n");

                if ($line === '') {
                    continue;
                }

                if ($buffer === null) {
                    $buffer = $line;
                    $bufferRowNumber = $physicalLineNumber;

                    continue;
                }

                if ($this->isPostRowStartLine($line)) {
                    $row = $this->buildPostRowFromRecord($buffer, $headers, $bufferRowNumber ?? $physicalLineNumber);

                    if ($this->rowHasContent($row['data'])) {
                        $rows[] = $row;
                    }

                    $buffer = $line;
                    $bufferRowNumber = $physicalLineNumber;

                    continue;
                }

                $buffer .= "\n".$line;
            }

            if ($buffer !== null) {
                $row = $this->buildPostRowFromRecord($buffer, $headers, $bufferRowNumber ?? $physicalLineNumber);

                if ($this->rowHasContent($row['data'])) {
                    $rows[] = $row;
                }
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  array<int, string>  $headers
     * @return array{row_number:int,data:array<string, string>}
     */
    private function buildPostRowFromRecord(string $record, array $headers, int $rowNumber): array
    {
        $values = $this->parsePostRecord($record, $headers);

        return [
            'row_number' => $rowNumber,
            'data' => $values,
        ];
    }

    /**
     * @param  array<int, string>  $headers
     * @return array<string, string>
     */
    private function parsePostRecord(string $record, array $headers): array
    {
        $record = rtrim($record, "\r\n");
        $headerCount = count($headers);
        $allFields = $this->extractAllDelimitedFields($record);

        if (count($allFields) === $headerCount) {
            return $this->mapFieldsToHeaders($allFields, $headers);
        }

        [$leadingFields, $offset] = $this->extractDelimitedFields($record, self::POST_LEADING_FIELD_COUNT);
        $remainder = substr($record, $offset);
        $trailingFields = $this->extractTrailingFieldsQuoteAware($remainder, self::POST_TRAILING_FIELD_COUNT);
        $description = $this->normalizeLooseField(
            substr($remainder, 0, $this->findDescriptionEndOffset($remainder, self::POST_TRAILING_FIELD_COUNT)),
            preserveNewlines: true
        );

        $fields = array_pad([
            ...$leadingFields,
            $description,
            ...$trailingFields,
        ], $headerCount, '');

        return $this->mapFieldsToHeaders($fields, $headers);
    }

    /**
     * @param  array<int, string>  $fields
     * @param  array<int, string>  $headers
     * @return array<string, string>
     */
    private function mapFieldsToHeaders(array $fields, array $headers): array
    {
        $values = [];

        foreach ($headers as $index => $header) {
            $raw = isset($fields[$index]) ? (string) $fields[$index] : '';
            $values[$header] = $header === 'description'
                ? $this->normalizeLooseField($raw, preserveNewlines: true)
                : $this->normalizeLooseField($raw);
        }

        return $values;
    }

    /**
     * @return array<int, string>
     */
    private function extractAllDelimitedFields(string $record): array
    {
        $fields = [];
        $field = '';
        $inQuotes = false;
        $length = strlen($record);

        for ($i = 0; $i < $length; $i++) {
            $char = $record[$i];

            if ($char === '"') {
                if ($inQuotes && $i + 1 < $length && $record[$i + 1] === '"') {
                    $field .= '"';
                    $i++;

                    continue;
                }

                $inQuotes = ! $inQuotes;

                continue;
            }

            if ($char === ',' && ! $inQuotes) {
                $fields[] = $field;
                $field = '';

                continue;
            }

            $field .= $char;
        }

        $fields[] = $field;

        return $fields;
    }

    /**
     * @return array{0:array<int, string>,1:int}
     */
    private function extractDelimitedFields(string $record, int $count): array
    {
        $fields = [];
        $field = '';
        $inQuotes = false;
        $length = strlen($record);

        for ($i = 0; $i < $length; $i++) {
            $char = $record[$i];

            if ($char === '"') {
                if ($inQuotes && $i + 1 < $length && $record[$i + 1] === '"') {
                    $field .= '"';
                    $i++;

                    continue;
                }

                $inQuotes = ! $inQuotes;

                continue;
            }

            if ($char === ',' && ! $inQuotes) {
                $fields[] = trim($field);
                $field = '';

                if (count($fields) === $count) {
                    return [$fields, $i + 1];
                }

                continue;
            }

            $field .= $char;
        }

        return [array_pad($fields, $count, ''), $length];
    }

    /**
     * @return array<int, string>
     */
    private function extractTrailingFieldsQuoteAware(string $value, int $trailingFieldCount): array
    {
        $trailingFields = [];
        $end = strlen($value);

        for ($i = 0; $i < $trailingFieldCount; $i++) {
            $boundary = $this->findPreviousFieldBoundary($value, $end);
            $start = $boundary < 0 ? 0 : $boundary + 1;
            $trailingFields[] = substr($value, $start, $end - $start);
            $end = max(0, $boundary);
        }

        return array_pad(
            array_map(fn (string $field) => $this->normalizeLooseField($field), array_reverse($trailingFields)),
            $trailingFieldCount,
            ''
        );
    }

    private function findDescriptionEndOffset(string $value, int $trailingFieldCount): int
    {
        $end = strlen($value);

        for ($i = 0; $i < $trailingFieldCount; $i++) {
            $boundary = $this->findPreviousFieldBoundary($value, $end);

            if ($boundary < 0) {
                return 0;
            }

            $end = $boundary;
        }

        return $end;
    }

    private function findPreviousFieldBoundary(string $record, int $endExclusive): int
    {
        if ($endExclusive <= 0) {
            return -1;
        }

        $inQuotes = false;

        for ($i = $endExclusive - 1; $i >= 0; $i--) {
            if ($record[$i] !== '"') {
                if ($record[$i] === ',' && ! $inQuotes) {
                    return $i;
                }

                continue;
            }

            $run = 1;

            while ($i - $run >= 0 && $record[$i - $run] === '"') {
                $run++;
            }

            if ($run % 2 === 1) {
                $inQuotes = ! $inQuotes;
            }

            $i -= $run - 1;
        }

        return -1;
    }

    private function normalizeLooseField(string $value, bool $preserveNewlines = false): string
    {
        $value = $preserveNewlines
            ? trim($value, " \t\n\r\0\x0B")
            : trim($value);

        if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
            $value = substr($value, 1, -1);
        }

        return str_replace('""', '"', $value);
    }

    /**
     * @param  array<string, string>  $data
     */
    private function postRowLooksMisaligned(array $data): bool
    {
        $category = trim((string) ($data['category'] ?? ''));
        $spaceId = trim((string) ($data['old_wp_space_id'] ?? ''));

        if ($category !== '' && ! in_array($category, CommunityImportValidator::CATEGORY_VALUES, true)) {
            return true;
        }

        if ($spaceId !== '' && ! ctype_digit($spaceId)) {
            return true;
        }

        return false;
    }

    private function isPostRowStartLine(string $line): bool
    {
        $line = ltrim($line);

        if (str_starts_with($line, "\xEF\xBB\xBF")) {
            $line = substr($line, 3);
        }

        return preg_match('/^\d+,\d*,\d*,/', $line) === 1;
    }

    /**
     * When a posts CSV has unescaped quotes inside description, League CSV splits one post
     * into multiple records. Merge continuation rows back into the previous valid post.
     *
     * @param  array<int, array{row_number:int,data:array<string, string>}>  $rows
     * @return array<int, array{row_number:int,data:array<string, string>}>
     */
    private function mergeSplitPostRows(string $path, array $rows): array
    {
        $merged = [];
        $current = null;
        $orphanCount = 0;

        foreach ($rows as $row) {
            $oldWpId = trim((string) ($row['data']['old_wp_post_id'] ?? ''));

            if ($oldWpId !== '' && ctype_digit($oldWpId)) {
                if ($current !== null) {
                    $merged[] = $current;
                }

                $current = $row;

                continue;
            }

            if ($current === null) {
                continue;
            }

            $fragment = $this->orphanPostRowToDescriptionFragment($row['data']);

            if ($fragment === '') {
                continue;
            }

            $orphanCount++;
            $existing = trim((string) ($current['data']['description'] ?? ''));
            $current['data']['description'] = $existing === ''
                ? $fragment
                : $existing."\n".$fragment;
        }

        if ($current !== null) {
            $merged[] = $current;
        }

        if ($orphanCount > 0) {
            Log::info('community.import.csv.posts.merged_orphan_rows', [
                'path' => $path,
                'raw_record_count' => count($rows),
                'merged_record_count' => count($merged),
                'orphan_rows_merged' => $orphanCount,
            ]);
        }

        return $merged;
    }

    /**
     * @param  array<string, string>  $data
     */
    private function orphanPostRowToDescriptionFragment(array $data): string
    {
        $parts = [];

        foreach ([
            'old_wp_post_id',
            'old_wp_author_id',
            'contributor_old_wp_user_id',
            'contributor_email',
            'title',
            'slug',
            'description',
            'tag',
            'category',
            'image_url',
            'video_url',
            'old_wp_space_id',
            'is_published',
            'published_at',
            'created_at',
            'updated_at',
        ] as $column) {
            $value = trim((string) ($data[$column] ?? ''));

            if ($value !== '') {
                $parts[] = $value;
            }
        }

        return implode("\n", $parts);
    }

    private function reader(string $path): Reader
    {
        $csv = Reader::createFromPath($path, 'r');
        $csv->setHeaderOffset(0);

        return $csv;
    }

    /**
     * @param  array<string, string>  $values
     */
    private function rowHasContent(array $values): bool
    {
        return count(array_filter($values, fn ($value) => trim((string) $value) !== '')) > 0;
    }

    /**
     * @param  array<int, string>  $headers
     * @return array<int, string>
     */
    private function normalizedHeaders(array $headers): array
    {
        return array_map([$this, 'normalizeHeader'], $headers);
    }

    private function normalizeHeader(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;

        return trim($value);
    }
}
