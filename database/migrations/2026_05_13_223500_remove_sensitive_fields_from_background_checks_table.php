<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('background_checks')
            ->select(['id', 'metadata', 'report_summary'])
            ->orderBy('id')
            ->lazy()
            ->each(function ($backgroundCheck): void {
                $metadata = $this->normalizeJsonColumn($backgroundCheck->metadata);
                $reportSummary = $this->normalizeJsonColumn($backgroundCheck->report_summary);

                $sanitizedMetadata = $this->scrubSensitiveKeys($metadata);
                $sanitizedReportSummary = $this->scrubSensitiveKeys($reportSummary);

                if ($sanitizedMetadata === $metadata && $sanitizedReportSummary === $reportSummary) {
                    return;
                }

                DB::table('background_checks')
                    ->where('id', $backgroundCheck->id)
                    ->update([
                        'metadata' => $sanitizedMetadata !== null ? json_encode($sanitizedMetadata) : null,
                        'report_summary' => $sanitizedReportSummary !== null ? json_encode($sanitizedReportSummary) : null,
                        'updated_at' => now(),
                    ]);
            });

        if (Schema::hasColumn('background_checks', 'ssn_last_four')) {
            Schema::table('background_checks', function (Blueprint $table) {
                $table->dropColumn('ssn_last_four');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('background_checks', 'ssn_last_four')) {
            Schema::table('background_checks', function (Blueprint $table) {
                $table->string('ssn_last_four')->nullable()->after('dob');
            });
        }
    }

    private function normalizeJsonColumn(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function scrubSensitiveKeys(?array $value): ?array
    {
        if ($value === null) {
            return null;
        }

        $keysToRemove = [
            'ssn',
            'ssn_last_four',
            'masked_ssn',
            'driver_license_number',
            'driver_license_state',
        ];

        $sanitized = [];

        foreach ($value as $key => $item) {
            if (in_array((string) $key, $keysToRemove, true)) {
                continue;
            }

            $sanitized[$key] = is_array($item)
                ? $this->scrubSensitiveKeys($item)
                : $item;
        }

        return $sanitized;
    }
};
