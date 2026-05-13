<?php

namespace App\Services;

use App\Enums\BackgroundCheckStatus;
use App\Models\BackgroundCheck;
use App\Models\ServiceProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckrService
{
    protected string $apiKey;
    protected string $apiUrl;
    protected bool $sandbox;

    public function __construct()
    {
    $this->apiKey = config('checkr.api_key') ?? '';
    $this->apiUrl = config('checkr.api_url') ?? 'https://api.checkr-staging.com/v1';
    $this->sandbox = config('checkr.sandbox', true);
    }

    /**
     * Get configured HTTP client for Checkr API.
     */
    protected function client(): PendingRequest
    {
        return Http::withBasicAuth($this->apiKey, '')
            ->baseUrl($this->apiUrl)
            ->acceptJson();
    }

    /**
     * Create a candidate in Checkr.
     */
    public function createCandidate(array $data): array
    {
        $payload = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'zipcode' => $data['zipcode'] ?? null,
            'dob' => $data['dob'] ?? null, // YYYY-MM-DD format
            'ssn' => $data['ssn'] ?? null,
            'copy_requested' => true,
            'work_locations' => config('checkr.work_locations'),
        ];

        if (!empty($data['middle_name'])) {
            $payload['middle_name'] = $data['middle_name'];
        }

        if (!empty($data['driver_license_number'])) {
            $payload['driver_license_number'] = $data['driver_license_number'];
            $payload['driver_license_state'] = $data['driver_license_state'] ?? null;
        }

        $response = $this->client()->post('/candidates', $payload);

        if ($response->failed()) {
            Log::error('Checkr: Failed to create candidate', [
                'status' => $response->status(),
                'response' => $response->json(),
                'payload' => array_diff_key($payload, ['ssn' => '']), // Don't log SSN
            ]);

            $error = $response->json('error') ?? 'Unknown error';
            if (is_array($error)) {
                $error = implode(', ', $error);
            }

            throw new \Exception('Failed to create candidate: ' . $error);
        }

        return $response->json();
    }

    /**
     * Create an invitation for a candidate.
     * The candidate will receive an email to complete the background check.
     */
    public function createInvitation(string $candidateId, ?string $package = null): array
    {
        $payload = [
            'candidate_id' => $candidateId,
            'package' => $package ?? config('checkr.default_package'),
            'work_locations' => config('checkr.work_locations'),
        ];

        $response = $this->client()->post('/invitations', $payload);

        if ($response->failed()) {
            Log::error('Checkr: Failed to create invitation', [
                'status' => $response->status(),
                'response' => $response->json(),
                'candidate_id' => $candidateId,
            ]);

            $error = $response->json('error') ?? 'Unknown error';
            if (is_array($error)) {
                $error = implode(', ', $error);
            }

            throw new \Exception('Failed to create invitation: ' . $error);
        }

        return $response->json();
    }

    /**
     * Get a candidate's details.
     */
    public function getCandidate(string $candidateId): array
    {
        $response = $this->client()->get("/candidates/{$candidateId}");

        if ($response->failed()) {
            throw new \Exception('Failed to retrieve candidate');
        }

        return $response->json();
    }

    /**
     * Get a report's details.
     */
    public function getReport(string $reportId): array
    {
        $response = $this->client()->get("/reports/{$reportId}");

        if ($response->failed()) {
            throw new \Exception('Failed to retrieve report');
        }

        return $response->json();
    }

    /**
     * Get an invitation's details.
     */
    public function getInvitation(string $invitationId): array
    {
        $response = $this->client()->get("/invitations/{$invitationId}");

        if ($response->failed()) {
            throw new \Exception('Failed to retrieve invitation');
        }

        return $response->json();
    }

    /**
     * Initiate a background check for a service provider.
     */
    public function initiateBackgroundCheck(ServiceProvider $provider, array $candidateData): BackgroundCheck
    {
        // Create candidate in Checkr
        $candidate = $this->createCandidate($candidateData);

        // Create background check record
        $backgroundCheck = BackgroundCheck::create([
            'service_provider_id' => $provider->id,
            'checkr_candidate_id' => $candidate['id'],
            'status' => BackgroundCheckStatus::PENDING,
            'package' => config('checkr.default_package'),
            'first_name' => $candidateData['first_name'],
            'middle_name' => $candidateData['middle_name'] ?? null,
            'last_name' => $candidateData['last_name'],
            'email' => $candidateData['email'],
            'phone' => $candidateData['phone'] ?? null,
            'zipcode' => $candidateData['zipcode'] ?? null,
            'dob' => $candidateData['dob'] ?? null,
            'metadata' => [
                'candidate_response' => $this->sanitizePersistedData($candidate),
            ],
        ]);

        // Create invitation (candidate will receive email)
        $invitation = $this->createInvitation($candidate['id']);

        $backgroundCheck->update([
            'checkr_invitation_id' => $invitation['id'],
            'status' => BackgroundCheckStatus::INVITED,
            'metadata' => array_merge($backgroundCheck->metadata ?? [], [
                'invitation_response' => $this->sanitizePersistedData($invitation),
            ]),
        ]);

        // Update provider status
        $provider->update([
            'background_check_status' => BackgroundCheckStatus::INVITED->value,
        ]);

        return $backgroundCheck->fresh();
    }

    /**
     * Process a webhook event from Checkr.
     */
    public function processWebhook(array $payload): void
    {
        $type = $payload['type'] ?? null;
        $data = $payload['object_type'] ?? $payload['data'] ?? $payload;
        $object = $data['object'] ?? null;

        Log::info('Checkr: Processing webhook', [
            'type' => $type,
            'object_type' => $object,
        ]);

        match ($type) {
            'report.created' => $this->handleReportCreated($data),
            'report.upgraded' => $this->handleReportUpdated($data),
            'report.completed' => $this->handleReportCompleted($data),
            'report.suspended' => $this->handleReportSuspended($data),
            'report.disputed' => $this->handleReportDisputed($data),
            'report.resumed' => $this->handleReportResumed($data),
            'invitation.completed' => $this->handleInvitationCompleted($data),
            'invitation.expired' => $this->handleInvitationExpired($data),
            'candidate.updated' => $this->handleCandidateUpdated($data),
            default => Log::info('Checkr: Unhandled webhook type', ['type' => $type]),
        };
    }

    protected function handleReportCreated(array $data): void
    {
        $reportId = $data['id'] ?? null;
        $candidateId = $data['candidate_id'] ?? null;

        if (!$candidateId) {
            return;
        }

        $check = BackgroundCheck::where('checkr_candidate_id', $candidateId)->latest()->first();

        if ($check) {
            $check->update([
                'checkr_report_id' => $reportId,
                'status' => BackgroundCheckStatus::COMPLETED,
            ]);
            $check->recordWebhook('report.created', $data);
        }
    }

    protected function handleReportUpdated(array $data): void
    {
        $this->updateFromReportData($data);
    }

    protected function handleReportCompleted(array $data): void
{
    $reportId = $data['id'] ?? null;
    $candidateId = $data['candidate_id'] ?? null;
    $result = $data['result'] ?? null;
    $adjudication = $data['adjudication'] ?? null;

    // Try to find by report_id first, then by candidate_id
    $check = BackgroundCheck::where('checkr_report_id', $reportId)->first();
    if (!$check && $candidateId) {
        $check = BackgroundCheck::where('checkr_candidate_id', $candidateId)->latest()->first();
    }

    if (!$check) {
        Log::warning('Checkr: No background check found for report', ['report_id' => $reportId, 'candidate_id' => $candidateId]);
        return;
    }

    $status = match ($result) {
        'clear' => BackgroundCheckStatus::CLEAR,
        'consider' => BackgroundCheckStatus::CONSIDER,
        default => BackgroundCheckStatus::COMPLETED,
    };

    $check->update([
        'checkr_report_id' => $reportId,
        'status' => $status,
        'adjudication' => $adjudication,
        'completed_at' => now(),
        'expires_at' => now()->addDays(config('checkr.expiration_days', 365)),
        'report_summary' => $this->sanitizePersistedData($data),
    ]);

    // Update provider's background check status
    if ($check->serviceProvider) {
        $check->serviceProvider->update([
            'background_check_status' => $status->value,
            'background_check_verified_at' => $status === BackgroundCheckStatus::CLEAR ? now() : null,
        ]);
    }

    $check->recordWebhook('report.completed', $data);
}

    protected function handleReportSuspended(array $data): void
    {
        $reportId = $data['id'] ?? null;
        $check = BackgroundCheck::where('checkr_report_id', $reportId)->first();

        if ($check) {
            $check->update(['status' => BackgroundCheckStatus::SUSPENDED]);
            $check->recordWebhook('report.suspended', $data);
            $check->serviceProvider->update([
                'background_check_status' => BackgroundCheckStatus::SUSPENDED->value,
            ]);
        }
    }

    protected function handleReportDisputed(array $data): void
    {
        $reportId = $data['id'] ?? null;
        $check = BackgroundCheck::where('checkr_report_id', $reportId)->first();

        if ($check) {
            $check->update(['status' => BackgroundCheckStatus::DISPUTE]);
            $check->recordWebhook('report.disputed', $data);
        }
    }

    protected function handleReportResumed(array $data): void
    {
        $reportId = $data['id'] ?? null;
        $check = BackgroundCheck::where('checkr_report_id', $reportId)->first();

        if ($check) {
            $check->update(['status' => BackgroundCheckStatus::COMPLETED]);
            $check->recordWebhook('report.resumed', $data);
        }
    }

    protected function handleInvitationCompleted(array $data): void
    {
        $invitationId = $data['id'] ?? null;
        $check = BackgroundCheck::where('checkr_invitation_id', $invitationId)->first();

        if ($check) {
            $check->update(['status' => BackgroundCheckStatus::COMPLETED]);
            $check->recordWebhook('invitation.completed', $data);
        }
    }

    protected function handleInvitationExpired(array $data): void
    {
        $invitationId = $data['id'] ?? null;
        $check = BackgroundCheck::where('checkr_invitation_id', $invitationId)->first();

        if ($check) {
            $check->update(['status' => BackgroundCheckStatus::EXPIRED]);
            $check->recordWebhook('invitation.expired', $data);
        }
    }

    protected function handleCandidateUpdated(array $data): void
    {
        $candidateId = $data['id'] ?? null;
        $check = BackgroundCheck::where('checkr_candidate_id', $candidateId)->first();

        if ($check) {
            $check->recordWebhook('candidate.updated', $data);
        }
    }

    protected function updateFromReportData(array $data): void
    {
        $reportId = $data['id'] ?? null;
        $check = BackgroundCheck::where('checkr_report_id', $reportId)->first();

        if ($check) {
            $check->recordWebhook('report.updated', $data);
        }
    }

    /**
     * Verify webhook signature.
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $secret = config('checkr.webhook_secret');

        if (empty($secret)) {
            // No secret configured, skip verification (not recommended for production)
            return true;
        }

        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expectedSignature, $signature);
    }

    public function sanitizePersistedData(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
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

            $sanitized[$key] = $this->sanitizePersistedData($item);
        }

        return $sanitized;
    }
}
