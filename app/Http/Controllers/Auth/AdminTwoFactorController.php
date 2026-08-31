<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AdminTwoFactorService;
use App\Support\AdminTwoFactorSession;
use App\Support\UserHomeUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminTwoFactorController extends Controller
{
    public function __construct(
        protected AdminTwoFactorService $adminTwoFactor,
    ) {}

    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user || ! $this->adminTwoFactor->requiresChallenge($user)) {
            return redirect()->to(UserHomeUrl::afterAuthentication($user));
        }

        if (AdminTwoFactorSession::isVerified($request)) {
            return redirect()->to(AdminTwoFactorSession::pullIntendedUrl($request, route('admin.dashboard')));
        }

        $phone = $this->adminTwoFactor->adminPhone($user);
        $needsPhone = $phone === null;

        $status = session('status');

        if (! $needsPhone && ! $this->adminTwoFactor->attemptsExceeded($user->id)) {
            $issuedStatus = $this->adminTwoFactor->issueInitialOtp($user, $request);
            $status ??= $issuedStatus;
        }

        return Inertia::render('Auth/AdminTwoFactor', [
            'maskedPhone' => $this->maskPhone($phone),
            'needsPhone' => $needsPhone,
            'codeSent' => AdminTwoFactorSession::hasCodeIssued($request),
            'attemptsRemaining' => $this->adminTwoFactor->remainingAttempts($user->id),
            'lockedOut' => $this->adminTwoFactor->attemptsExceeded($user->id),
            'status' => $status,
            'usesFakeSms' => $this->adminTwoFactor->usesFakeSms(),
            'fakeOtpCode' => $this->adminTwoFactor->fakeOtpCode(),
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user || ! $this->adminTwoFactor->requiresChallenge($user)) {
            abort(403);
        }

        if ($this->adminTwoFactor->attemptsExceeded($user->id)) {
            throw ValidationException::withMessages([
                'code' => 'Too many failed attempts. Please try again later or sign out and sign back in.',
            ]);
        }

        $status = $this->adminTwoFactor->resendOtp($user, $request);

        AdminTwoFactorSession::markPending($request);

        return back()->with('status', $status);
    }

    public function verify(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user || ! $this->adminTwoFactor->requiresChallenge($user)) {
            abort(403);
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'min:4', 'max:10'],
        ]);

        if (AdminTwoFactorSession::deliveryMethod($request) === null) {
            throw ValidationException::withMessages([
                'code' => 'Request a verification code before trying to verify.',
            ]);
        }

        if ($this->adminTwoFactor->attemptsExceeded($user->id)) {
            throw ValidationException::withMessages([
                'code' => 'Too many failed attempts. Please try again later or sign out and sign back in.',
            ]);
        }

        if (! $this->adminTwoFactor->verify($user, $request, $validated['code'])) {
            $remaining = $this->adminTwoFactor->remainingAttempts($user->id);

            $message = $remaining > 0
                ? "Invalid or expired verification code. {$remaining} attempt(s) remaining."
                : 'Too many failed attempts. Please try again later or sign out and sign back in.';

            throw ValidationException::withMessages([
                'code' => $message,
            ]);
        }

        AdminTwoFactorSession::markVerified($request);

        return redirect()->to(
            AdminTwoFactorSession::pullIntendedUrl($request, route('admin.dashboard'))
        );
    }

    private function maskPhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (strlen($digits) < 4) {
            return null;
        }

        return '••••••'.substr($digits, -4);
    }
}
