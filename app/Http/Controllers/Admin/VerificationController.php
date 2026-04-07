<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\IdentityVerification;
use App\Notifications\VerificationApprovedNotification;
use App\Notifications\VerificationRejectedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class VerificationController extends Controller
{
    public function index(Request $request): Response
    {
        $query = IdentityVerification::query()
            ->with(['serviceProvider.user:id,first_name,last_name,email,avatar']);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            // Default to pending
            $query->whereIn('status', [VerificationStatus::PENDING, VerificationStatus::UNDER_REVIEW]);
        }

        // Sort
        $query->orderBy('created_at', 'asc'); // Oldest first (FIFO)

        $verifications = $query->paginate(20)->withQueryString();

        // Stats
        $stats = [
            'pending' => IdentityVerification::where('status', VerificationStatus::PENDING)->count(),
            'under_review' => IdentityVerification::where('status', VerificationStatus::UNDER_REVIEW)->count(),
            'approved_today' => IdentityVerification::where('status', VerificationStatus::APPROVED)
                ->whereDate('reviewed_at', today())
                ->count(),
            'rejected_today' => IdentityVerification::where('status', VerificationStatus::REJECTED)
                ->whereDate('reviewed_at', today())
                ->count(),
        ];

        return Inertia::render('Admin/Verifications/Index', [
            'verifications' => $verifications,
            'filters' => $request->only(['status']),
            'stats' => $stats,
            'statuses' => collect(VerificationStatus::cases())->map(fn($s) => ['value' => $s->value, 'label' => $s->label()]),
        ]);
    }

    public function show(IdentityVerification $verification): Response
    {
        $verification->load(['serviceProvider.user', 'reviewer:id,first_name,last_name']);

        // Generate temporary signed URLs for documents
        $documentUrls = [
            'front' => $verification->document_front_path 
                ? Storage::disk('s3-private')->temporaryUrl($verification->document_front_path, now()->addMinutes(30))
                : null,
            'back' => $verification->document_back_path 
                ? Storage::disk('s3-private')->temporaryUrl($verification->document_back_path, now()->addMinutes(30))
                : null,
            'selfie' => $verification->selfie_path 
                ? Storage::disk('s3-private')->temporaryUrl($verification->selfie_path, now()->addMinutes(30))
                : null,
            'additional' => collect($verification->additional_documents ?? [])->map(fn($path) => 
                Storage::disk('s3-private')->temporaryUrl($path, now()->addMinutes(30))
            )->toArray(),
        ];

        // Mark as under review if pending
        if ($verification->status === VerificationStatus::PENDING) {
            $verification->update([
                'status' => VerificationStatus::UNDER_REVIEW,
                'reviewer_id' => auth()->id(),
            ]);
        }

        return Inertia::render('Admin/Verifications/Show', [
            'verification' => $verification,
            'documentUrls' => $documentUrls,
            'provider' => $verification->serviceProvider,
        ]);
    }

    public function approve(Request $request, IdentityVerification $verification): RedirectResponse
    {
        if (!in_array($verification->status, [VerificationStatus::PENDING, VerificationStatus::UNDER_REVIEW])) {
            return back()->withErrors(['error' => 'This verification has already been processed.']);
        }

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        // Update verification
        $verification->update([
            'status' => VerificationStatus::APPROVED,
            'reviewed_at' => now(),
            'reviewer_id' => auth()->id(),
            'admin_notes' => $validated['notes'] ?? null,
        ]);

        // Update provider verification status
        $verification->serviceProvider->update([
            'is_verified' => true,
            'verified_at' => now(),
            'verified_by' => auth()->id(),
        ]);

        // Notify provider
        $verification->serviceProvider->user->notify(new VerificationApprovedNotification($verification));

        return redirect()->route('admin.verifications.index')
            ->with('success', 'Verification approved successfully.');
    }

    public function reject(Request $request, IdentityVerification $verification): RedirectResponse
    {
        if (!in_array($verification->status, [VerificationStatus::PENDING, VerificationStatus::UNDER_REVIEW])) {
            return back()->withErrors(['error' => 'This verification has already been processed.']);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        // Update verification
        $verification->update([
            'status' => VerificationStatus::REJECTED,
            'reviewed_at' => now(),
            'reviewer_id' => auth()->id(),
            'rejection_reason' => $validated['reason'],
        ]);

        // Notify provider
        $verification->serviceProvider->user->notify(new VerificationRejectedNotification($verification));

        return redirect()->route('admin.verifications.index')
            ->with('success', 'Verification rejected.');
    }

    public function requestMoreInfo(Request $request, IdentityVerification $verification): RedirectResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        // Update verification with request
        $verification->update([
            'admin_notes' => $validated['message'],
            'info_requested_at' => now(),
        ]);

        // TODO: Send notification to provider requesting more info

        return back()->with('success', 'Information request sent to provider.');
    }

    public function bulkApprove(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:identity_verifications,id'],
        ]);

        $verifications = IdentityVerification::whereIn('id', $validated['ids'])
            ->whereIn('status', [VerificationStatus::PENDING, VerificationStatus::UNDER_REVIEW])
            ->get();

        foreach ($verifications as $verification) {
            $verification->update([
                'status' => VerificationStatus::APPROVED,
                'reviewed_at' => now(),
                'reviewer_id' => auth()->id(),
            ]);

            $verification->serviceProvider->update([
                'is_verified' => true,
                'verified_at' => now(),
                'verified_by' => auth()->id(),
            ]);

            $verification->serviceProvider->user->notify(new VerificationApprovedNotification($verification));
        }

        return back()->with('success', count($verifications) . ' verifications approved.');
    }
}
