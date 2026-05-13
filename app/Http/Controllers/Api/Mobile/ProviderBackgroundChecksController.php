<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Enums\BackgroundCheckStatus;
use App\Http\Controllers\Controller;
use App\Models\BackgroundCheck;
use App\Services\CheckrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProviderBackgroundChecksController extends Controller
{
    public function __construct(
        protected CheckrService $checkrService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $provider = $request->user()->serviceProvider;
        abort_unless($provider, 403);

        $backgroundCheck = BackgroundCheck::where('service_provider_id', $provider->id)
            ->latest()
            ->first();

        $history = BackgroundCheck::where('service_provider_id', $provider->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (BackgroundCheck $check) => [
                'uuid' => $check->uuid,
                'status' => $check->status->value,
                'status_display' => $check->status_display,
                'initiated_at' => $check->created_at?->toIso8601String(),
                'completed_at' => $check->completed_at?->toIso8601String(),
                'expires_at' => $check->expires_at?->toIso8601String(),
                'days_until_expiry' => $check->days_until_expiry,
                'is_valid' => $check->is_valid,
            ]);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'provider' => [
                    'business_name' => $provider->business_name,
                    'background_check_status' => $provider->background_check_status,
                ],
                'background_check' => $backgroundCheck ? [
                    'uuid' => $backgroundCheck->uuid,
                    'status' => $backgroundCheck->status->value,
                    'status_display' => $backgroundCheck->status_display,
                    'initiated_at' => $backgroundCheck->created_at?->toIso8601String(),
                    'completed_at' => $backgroundCheck->completed_at?->toIso8601String(),
                    'expires_at' => $backgroundCheck->expires_at?->toIso8601String(),
                    'days_until_expiry' => $backgroundCheck->days_until_expiry,
                    'is_valid' => $backgroundCheck->is_valid,
                    'can_initiate' => $backgroundCheck->canInitiate(),
                ] : null,
                'history' => $history,
                'can_initiate' => ! $backgroundCheck || $backgroundCheck->canInitiate(),
                'user' => [
                    'first_name' => $request->user()->first_name,
                    'last_name' => $request->user()->last_name,
                    'email' => $request->user()->email,
                    'phone' => $request->user()->phone,
                ],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $provider = $request->user()->serviceProvider;
        abort_unless($provider, 403);

        $existing = BackgroundCheck::where('service_provider_id', $provider->id)
            ->whereNotIn('status', [
                BackgroundCheckStatus::EXPIRED,
                BackgroundCheckStatus::PENDING,
            ])
            ->latest()
            ->first();

        if ($existing && ! $existing->is_expired) {
            return response()->json([
                'success' => false,
                'message' => 'You already have an active background check.',
                'errors' => (object) [],
            ], 422);
        }

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'zipcode' => ['required', 'string', 'max:10'],
            'dob' => ['required', 'date', 'before:'.now()->subYears(18)->toDateString()],
            'ssn' => ['required', 'string', 'regex:/^\d{3}-?\d{2}-?\d{4}$/'],
            'driver_license_number' => ['nullable', 'string', 'max:50'],
            'driver_license_state' => ['nullable', 'required_with:driver_license_number', 'string', 'max:2'],
            'consent' => ['required', 'accepted'],
        ]);

        $validated['ssn'] = str_replace('-', '', (string) $validated['ssn']);

        try {
            $backgroundCheck = $this->checkrService->initiateBackgroundCheck($provider, $validated);
        } catch (\Throwable $e) {
            Log::error('Mobile background check initiation failed', [
                'provider_id' => $provider->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate background check. Please try again or contact support.',
                'errors' => (object) [],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Background check initiated! Check your email to complete the process.',
            'data' => [
                'background_check' => [
                    'uuid' => $backgroundCheck->uuid,
                    'status' => $backgroundCheck->status->value,
                    'status_display' => $backgroundCheck->status_display,
                    'initiated_at' => $backgroundCheck->created_at?->toIso8601String(),
                ],
            ],
        ], 201);
    }

    public function show(Request $request, BackgroundCheck $backgroundCheck): JsonResponse
    {
        $provider = $request->user()->serviceProvider;
        abort_unless($provider, 403);
        abort_unless($backgroundCheck->service_provider_id === $provider->id, 403);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'background_check' => [
                    'uuid' => $backgroundCheck->uuid,
                    'status' => $backgroundCheck->status->value,
                    'status_display' => $backgroundCheck->status_display,
                    'full_name' => $backgroundCheck->full_name,
                    'email' => $backgroundCheck->email,
                    'masked_ssn' => null,
                    'initiated_at' => $backgroundCheck->created_at?->toIso8601String(),
                    'completed_at' => $backgroundCheck->completed_at?->toIso8601String(),
                    'expires_at' => $backgroundCheck->expires_at?->toIso8601String(),
                    'days_until_expiry' => $backgroundCheck->days_until_expiry,
                    'is_valid' => $backgroundCheck->is_valid,
                    'package' => $backgroundCheck->package,
                ],
            ],
        ]);
    }

    public function refresh(Request $request, BackgroundCheck $backgroundCheck): JsonResponse
    {
        $provider = $request->user()->serviceProvider;
        abort_unless($provider, 403);
        abort_unless($backgroundCheck->service_provider_id === $provider->id, 403);

        try {
            if ($backgroundCheck->checkr_report_id) {
                $report = $this->checkrService->getReport($backgroundCheck->checkr_report_id);

                $status = match ($report['status'] ?? null) {
                    'clear' => BackgroundCheckStatus::CLEAR,
                    'consider' => BackgroundCheckStatus::CONSIDER,
                    'suspended' => BackgroundCheckStatus::SUSPENDED,
                    'dispute' => BackgroundCheckStatus::DISPUTE,
                    default => $backgroundCheck->status,
                };

                $backgroundCheck->update([
                    'status' => $status,
                    'metadata' => array_merge($backgroundCheck->metadata ?? [], [
                        'last_refresh' => now()->toISOString(),
                        'report_response' => $this->checkrService->sanitizePersistedData($report),
                    ]),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Mobile background check refresh failed', [
                'check_uuid' => $backgroundCheck->uuid,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh status.',
                'errors' => (object) [],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'background_check' => [
                    'uuid' => $backgroundCheck->fresh()->uuid,
                    'status' => $backgroundCheck->fresh()->status->value,
                    'status_display' => $backgroundCheck->fresh()->status_display,
                    'completed_at' => $backgroundCheck->fresh()->completed_at?->toIso8601String(),
                    'expires_at' => $backgroundCheck->fresh()->expires_at?->toIso8601String(),
                    'days_until_expiry' => $backgroundCheck->fresh()->days_until_expiry,
                    'is_valid' => $backgroundCheck->fresh()->is_valid,
                ],
            ],
        ]);
    }
}

