<?php

namespace App\Http\Controllers\Affiliate\Auth;

use App\Enums\AffiliateStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('affiliate.dashboard');
        }

        $request->user()->sendEmailVerificationNotification();
        $request->user()->affiliateProfile?->update([
            'status' => AffiliateStatus::EMAIL_SENT,
        ]);

        return back()->with('success', 'A fresh verification email has been sent.');
    }
}
