<?php

namespace App\Console\Commands;

use App\Enums\BackgroundCheckStatus;
use App\Models\BackgroundCheck;
use App\Services\CheckrService;
use Illuminate\Console\Command;

class SyncBackgroundCheck extends Command
{
    protected $signature = 'checkr:sync {--candidate= : Checkr candidate ID} {--report= : Checkr report ID}';
    protected $description = 'Manually sync a background check from Checkr';

    public function handle(CheckrService $checkrService)
    {
        $candidateId = $this->option('candidate');
        $reportId = $this->option('report');

        if ($candidateId) {
            $check = BackgroundCheck::where('checkr_candidate_id', $candidateId)->first();
        } elseif ($reportId) {
            $check = BackgroundCheck::where('checkr_report_id', $reportId)->first();
        } else {
            $this->error('Provide --candidate or --report');
            return 1;
        }

        if (!$check) {
            $this->error('Background check not found');
            return 1;
        }

        $this->info("Found check: {$check->uuid} for {$check->full_name}");

        // Try to get report
        if ($check->checkr_report_id) {
            $report = $checkrService->getReport($check->checkr_report_id);
            $this->info("Report status: " . ($report['status'] ?? 'unknown'));

            $status = match ($report['status'] ?? null) {
                'clear' => BackgroundCheckStatus::CLEAR,
                'consider' => BackgroundCheckStatus::CONSIDER,
                'pending' => BackgroundCheckStatus::COMPLETED,
                default => $check->status,
            };

            $check->update([
                'status' => $status,
                'completed_at' => $status === BackgroundCheckStatus::CLEAR ? now() : $check->completed_at,
                'expires_at' => $status === BackgroundCheckStatus::CLEAR ? now()->addYear() : null,
                'report_summary' => $report,
            ]);

            // Update provider
            $check->serviceProvider->update([
                'background_check_status' => $status->value,
                'background_check_verified_at' => $status === BackgroundCheckStatus::CLEAR ? now() : null,
            ]);

            $this->info("Updated to: {$status->value}");
        } else {
            $this->warn('No report ID yet - check may still be pending');
        }

        return 0;
    }
}