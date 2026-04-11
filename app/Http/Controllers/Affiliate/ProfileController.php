<?php

namespace App\Http\Controllers\Affiliate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Affiliates\UpdateAffiliateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $affiliate = $user->affiliateProfile()->with('user')->firstOrFail();

        return Inertia::render('Affiliate/Profile/Edit', [
            'affiliate' => $affiliate,
        ]);
    }

    public function update(UpdateAffiliateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $affiliate = $user->affiliateProfile()->firstOrFail();
        $validated = $request->validated();

        $user->update([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'phone' => $validated['phone'] ?? null,
        ]);

        $affiliate->update([
            'phone' => $validated['phone'] ?? null,
            'company_name' => $validated['company_name'] ?? null,
            'website_url' => $validated['website_url'] ?? null,
            'social_profile_url' => $validated['social_profile_url'] ?? null,
            'payout_method' => $validated['payout_method'] ?? null,
            'payout_details' => array_filter([
                'paypal_email' => $validated['paypal_email'] ?? null,
                'bank_account_name' => $validated['bank_account_name'] ?? null,
            ]),
        ]);

        return back()->with('success', 'Affiliate profile updated successfully.');
    }
}
