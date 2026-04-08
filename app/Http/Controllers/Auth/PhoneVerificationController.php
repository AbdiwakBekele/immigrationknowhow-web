<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\PhoneDialOptions;
use App\Services\PhoneVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PhoneVerificationController extends Controller
{
    public function __construct(
        protected PhoneVerificationService $phoneVerification
    ) {}

    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user->phone_verified_at) {
            return redirect()->route('onboarding.index');
        }

        return Inertia::render('Auth/VerifyPhone', [
            'phone' => $user->phone ?? '',
            'phoneDialOptions' => PhoneDialOptions::selectOptions(),
        ]);
    }

    public function sendOtp(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->phone_verified_at) {
            return redirect()->route('onboarding.index');
        }

        $validated = $request->validate([
            'phone' => ['required', 'string', 'min:10', 'max:32'],
        ]);

        $this->phoneVerification->sendOtp($user, $validated['phone']);

        return back()->with('otp_sent', true);
    }

    public function verify(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->phone_verified_at) {
            return redirect()->route('onboarding.index');
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32'],
        ]);

        if (! $this->phoneVerification->verify($user, $validated['code'])) {
            return back()->withErrors(['code' => 'Invalid or expired code. Try again or request a new code.']);
        }

        return redirect()->route('onboarding.index')->with('success', 'Phone number verified.');
    }
}
