<?php

namespace App\Http\Controllers\Provider;

use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\IdentityVerification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class VerificationController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();
        $provider = $user->serviceProvider;

        // Get latest verification request
        $verification = IdentityVerification::where('user_id', $user->id)
            ->latest()
            ->first();

        // Get verification history
        $history = IdentityVerification::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($v) => [
                'id' => $v->id,
                'status' => $v->status,
                'status_label' => $v->status->label(),
                'document_type' => $v->document_type,
                'submitted_at' => $v->created_at->format('M d, Y'),
                'reviewed_at' => $v->reviewed_at?->format('M d, Y'),
                'rejection_reason' => $v->rejection_reason,
            ]);

        return Inertia::render('Provider/Verification/Index', [
            'isVerified' => $provider->verification_status === VerificationStatus::APPROVED,
            'verification' => $verification ? [
                'id' => $verification->id,
                'status' => $verification->status,
                'status_label' => $verification->status->label(),
                'document_type' => $verification->document_type,
                'submitted_at' => $verification->created_at->format('M d, Y'),
                'reviewed_at' => $verification->reviewed_at?->format('M d, Y'),
                'rejection_reason' => $verification->rejection_reason,
            ] : null,
            'history' => $history,
            'canSubmit' => !$verification || 
                in_array($verification->status, [VerificationStatus::REJECTED, VerificationStatus::EXPIRED]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $provider = $user->serviceProvider;

        // Check if there's a pending verification
        $pending = IdentityVerification::where('user_id', $user->id)
            ->whereIn('status', [VerificationStatus::PENDING, VerificationStatus::UNDER_REVIEW])
            ->exists();

        if ($pending) {
            return back()->withErrors(['error' => 'You already have a pending verification request.']);
        }

        $validated = $request->validate([
            'document_type' => ['required', 'string', 'in:drivers_license,passport,state_id,professional_license'],
            'document_front' => ['required', 'image', 'max:5120'], // 5MB
            'document_back' => ['nullable', 'image', 'max:5120'],
            'selfie' => ['required', 'image', 'max:5120'],
        ]);

        // Store documents securely (private storage)
        $frontPath = $request->file('document_front')
            ->store("verifications/{$user->id}", 's3-private');

        $backPath = $request->hasFile('document_back')
            ? $request->file('document_back')->store("verifications/{$user->id}", 's3-private')
            : null;

        $selfiePath = $request->file('selfie')
            ->store("verifications/{$user->id}", 's3-private');

        IdentityVerification::create([
            'uuid' => Str::uuid(),
            'user_id' => $user->id,
            'document_type' => $validated['document_type'],
            'front_image' => $frontPath,
            'back_image' => $backPath,
            'selfie_image' => $selfiePath,
            'status' => VerificationStatus::PENDING,
        ]);

        return redirect()->route('provider.verification.index')
            ->with('success', 'Verification documents submitted. We will review them within 2-3 business days.');
    }

    public function resubmit(Request $request): RedirectResponse
    {
        $user = auth()->user();

        // Get the rejected verification
        $rejected = IdentityVerification::where('user_id', $user->id)
            ->where('status', VerificationStatus::REJECTED)
            ->latest()
            ->first();

        if (!$rejected) {
            return back()->withErrors(['error' => 'No rejected verification found.']);
        }

        $validated = $request->validate([
            'document_type' => ['required', 'string', 'in:drivers_license,passport,state_id,professional_license'],
            'document_front' => ['required', 'image', 'max:5120'],
            'document_back' => ['nullable', 'image', 'max:5120'],
            'selfie' => ['required', 'image', 'max:5120'],
        ]);

        $frontPath = $request->file('document_front')
            ->store("verifications/{$user->id}", 's3-private');

        $backPath = $request->hasFile('document_back')
            ? $request->file('document_back')->store("verifications/{$user->id}", 's3-private')
            : null;

        $selfiePath = $request->file('selfie')
            ->store("verifications/{$user->id}", 's3-private');

        IdentityVerification::create([
            'uuid' => Str::uuid(),
            'user_id' => $user->id,
            'document_type' => $validated['document_type'],
            'front_image' => $frontPath,
            'back_image' => $backPath,
            'selfie_image' => $selfiePath,
            'status' => VerificationStatus::PENDING,
        ]);

        return redirect()->route('provider.verification.index')
            ->with('success', 'Verification documents resubmitted.');
    }
}
