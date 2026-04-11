<?php

namespace App\Http\Controllers\Affiliate\Auth;

use App\Enums\AffiliateStatus;
use App\Http\Controllers\Controller;
use App\Notifications\AffiliateWelcomeNotification;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class EmailVerificationController extends Controller
{
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();

        $affiliate = $request->user()->affiliateProfile;

        if ($affiliate) {
            $affiliate->update([
                'status' => AffiliateStatus::ACTIVE,
                'activated_at' => now(),
            ]);
        }

        $request->user()->notify(new AffiliateWelcomeNotification());

        return redirect()
            ->route('affiliate.dashboard')
            ->with('success', 'Your email has been verified and your affiliate account is active.');
    }
}
