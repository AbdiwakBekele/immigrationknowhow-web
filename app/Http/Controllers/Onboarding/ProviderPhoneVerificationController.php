<?php

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Services\PhoneVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProviderPhoneVerificationController extends Controller
{
    public function __construct(
        protected PhoneVerificationService $phoneVerification
    ) {}

    public function send(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user || ! $user->followsProviderOnboarding()) {
            return redirect()->route('onboarding.user', ['step' => 2]);
        }

        $validated = $request->validate([
            'phone' => ['required', 'string', 'min:10', 'max:32'],
        ]);

        $this->phoneVerification->sendOtp($user, $validated['phone']);

        Log::channel('single')->info('ProviderOnboarding phone OTP sent.', [
            'user_id' => $user->id,
            'phone_last4' => substr((string) $validated['phone'], -4),
        ]);

        return back()->with('otp_sent', true);
    }

    public function verify(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user || ! $user->followsProviderOnboarding()) {
            return redirect()->route('onboarding.user', ['step' => 2]);
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32'],
        ]);

        if (! $this->phoneVerification->verify($user, $validated['code'])) {
            Log::channel('single')->warning('ProviderOnboarding phone OTP verify failed.', [
                'user_id' => $user->id,
            ]);

            return back()->withErrors(['code' => 'Invalid or expired code. Try again or request a new code.']);
        }

        Log::channel('single')->info('ProviderOnboarding phone OTP verified.', [
            'user_id' => $user->id,
        ]);

        return redirect()->route('onboarding.provider', ['step' => 4])->with('success', 'Phone number verified.');
    }
}

