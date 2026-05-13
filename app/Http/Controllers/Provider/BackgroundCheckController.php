<?php

namespace App\Http\Controllers\Provider;

use App\Enums\BackgroundCheckStatus;
use App\Http\Controllers\Controller;
use App\Models\BackgroundCheck;
use App\Services\CheckrService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class BackgroundCheckController extends Controller
{
    public function __construct(
        protected CheckrService $checkrService
    ) {}

    /**
     * Display background check status and form.
     */
    public function index(): Response
    {
        $user = auth()->user();
        $provider = $user->serviceProvider;

        // Get latest background check
        $backgroundCheck = BackgroundCheck::where('service_provider_id', $provider->id)
            ->latest()
            ->first();

        // Get history
        $history = BackgroundCheck::where('service_provider_id', $provider->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($check) => [
                'id' => $check->uuid,
                'status' => $check->status->value,
                'status_display' => $check->status_display,
                'initiated_at' => $check->created_at->format('M d, Y'),
                'completed_at' => $check->completed_at?->format('M d, Y'),
                'expires_at' => $check->expires_at?->format('M d, Y'),
                'days_until_expiry' => $check->days_until_expiry,
            ]);

        return Inertia::render('Provider/BackgroundCheck/Index', [
            'provider' => [
                'id' => $provider->id,
                'business_name' => $provider->business_name,
                'background_check_status' => $provider->background_check_status,
            ],
            'backgroundCheck' => $backgroundCheck ? [
                'id' => $backgroundCheck->uuid,
                'status' => $backgroundCheck->status->value,
                'status_display' => $backgroundCheck->status_display,
                'initiated_at' => $backgroundCheck->created_at->format('M d, Y'),
                'completed_at' => $backgroundCheck->completed_at?->format('M d, Y'),
                'expires_at' => $backgroundCheck->expires_at?->format('M d, Y'),
                'days_until_expiry' => $backgroundCheck->days_until_expiry,
                'is_valid' => $backgroundCheck->is_valid,
                'can_initiate' => $backgroundCheck->canInitiate(),
            ] : null,
            'history' => $history,
            'canInitiate' => !$backgroundCheck || $backgroundCheck->canInitiate(),
            'user' => [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone' => $user->phone,
            ],
        ]);
    }

    /**
     * Initiate a new background check.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $provider = $user->serviceProvider;

        // Check if there's already an active check
        $existing = BackgroundCheck::where('service_provider_id', $provider->id)
            ->whereNotIn('status', [
                BackgroundCheckStatus::EXPIRED,
                BackgroundCheckStatus::PENDING,
            ])
            ->latest()
            ->first();

        if ($existing && !$existing->is_expired) {
            return back()->withErrors([
                'error' => 'You already have an active background check.',
            ]);
        }

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'zipcode' => ['required', 'string', 'max:10'],
            'dob' => ['required', 'date', 'before:' . now()->subYears(18)->toDateString()],
            'consent' => ['required', 'accepted'],
        ], [
            'dob.before' => 'You must be at least 18 years old.',
            'consent.accepted' => 'You must consent to the background check.',
        ]);

        try {
            $backgroundCheck = $this->checkrService->initiateBackgroundCheck($provider, $validated);

            return redirect()->route('provider.background-check.index')
                ->with('success', 'Background check initiated! Check your email to complete the process.');

        } catch (\Exception $e) {
            Log::error('Background check initiation failed', [
                'provider_id' => $provider->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'error' => 'Failed to initiate background check. Please try again or contact support.',
            ])->withInput();
        }
    }

    /**
     * Show details of a specific background check.
     */
    public function show(BackgroundCheck $backgroundCheck): Response
    {
        $user = auth()->user();
        $provider = $user->serviceProvider;

        // Ensure the check belongs to this provider
        abort_unless($backgroundCheck->service_provider_id === $provider->id, 403);

        return Inertia::render('Provider/BackgroundCheck/Show', [
            'backgroundCheck' => [
                'id' => $backgroundCheck->uuid,
                'status' => $backgroundCheck->status->value,
                'status_display' => $backgroundCheck->status_display,
                'full_name' => $backgroundCheck->full_name,
                'email' => $backgroundCheck->email,
                'masked_ssn' => $backgroundCheck->masked_ssn,
                'initiated_at' => $backgroundCheck->created_at->format('M d, Y \a\t g:i A'),
                'completed_at' => $backgroundCheck->completed_at?->format('M d, Y \a\t g:i A'),
                'expires_at' => $backgroundCheck->expires_at?->format('M d, Y'),
                'days_until_expiry' => $backgroundCheck->days_until_expiry,
                'is_valid' => $backgroundCheck->is_valid,
                'package' => $backgroundCheck->package,
            ],
        ]);
    }

    /**
     * Refresh status from Checkr (manual sync).
     */
    public function refresh(BackgroundCheck $backgroundCheck): RedirectResponse
    {
        $user = auth()->user();
        $provider = $user->serviceProvider;

        abort_unless($backgroundCheck->service_provider_id === $provider->id, 403);

        try {
            // Fetch latest from Checkr
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
                        'report_response' => $report,
                    ]),
                ]);
            }

            return back()->with('success', 'Status refreshed.');

        } catch (\Exception $e) {
            Log::error('Failed to refresh background check status', [
                'check_id' => $backgroundCheck->uuid,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Failed to refresh status.']);
        }
    }
}
