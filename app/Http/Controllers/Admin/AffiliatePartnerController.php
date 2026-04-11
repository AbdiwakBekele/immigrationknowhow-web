<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AffiliateStatus;
use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\AffiliateEarning;
use App\Models\AffiliateInvite;
use App\Models\AffiliatePayout;
use App\Models\AffiliateReferralVisit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AffiliatePartnerController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Affiliate::query()
            ->with('user:id,first_name,last_name,email,is_active')
            ->withCount(['visits', 'referrals', 'earnings']);

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($builder) use ($search) {
                $builder->where('code', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $affiliates = $query
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $stats = [
            'totalAffiliates' => Affiliate::count(),
            'activeAffiliates' => Affiliate::where('status', AffiliateStatus::ACTIVE->value)->count(),
            'invitedAffiliates' => AffiliateInvite::query()
                ->whereNull('accepted_at')
                ->whereNull('cancelled_at')
                ->count(),
            'totalClicks' => AffiliateReferralVisit::count(),
            'pendingCommissions' => (float) AffiliateEarning::where('status', 'pending')->sum('commission_amount'),
            'paidCommissions' => (float) AffiliateEarning::where('status', 'paid')->sum('commission_amount'),
            'payoutTotals' => (float) AffiliatePayout::sum('amount'),
        ];

        $topAffiliates = Affiliate::query()
            ->with('user:id,first_name,last_name,email')
            ->withCount('referrals')
            ->withSum('earnings', 'commission_amount')
            ->orderByDesc('referrals_count')
            ->limit(5)
            ->get();

        return Inertia::render('Admin/Affiliates/Index', [
            'affiliates' => $affiliates,
            'filters' => $request->only(['search', 'status']),
            'stats' => $stats,
            'topAffiliates' => $topAffiliates,
            'pendingInvites' => AffiliateInvite::query()
                ->with('inviter:id,first_name,last_name,email')
                ->whereNull('accepted_at')
                ->whereNull('cancelled_at')
                ->latest('sent_at')
                ->limit(10)
                ->get()
                ->map(fn (AffiliateInvite $invite) => [
                    'id' => $invite->id,
                    'name' => $invite->name,
                    'email' => $invite->email,
                    'phone' => $invite->phone,
                    'sent_at' => optional($invite->sent_at)?->toDateTimeString(),
                    'expires_at' => optional($invite->expires_at)?->toDateTimeString(),
                    'is_expired' => $invite->hasExpired(),
                    'inviter_name' => $invite->inviter?->full_name ?: $invite->inviter?->email,
                ]),
            'statuses' => collect(AffiliateStatus::cases())->map(fn ($status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ])->values(),
        ]);
    }

    public function show(Affiliate $affiliate): Response
    {
        $affiliate->load('user:id,first_name,last_name,email,phone,email_verified_at');

        $referredUsers = $affiliate->referrals()
            ->with('referredUser:id,first_name,last_name,email,created_at')
            ->latest('registered_at')
            ->paginate(10, ['*'], 'referredPage')
            ->withQueryString();

        $earnings = $affiliate->earnings()
            ->with('referredUser:id,first_name,last_name,email')
            ->latest()
            ->paginate(10, ['*'], 'earningsPage')
            ->withQueryString();

        $payouts = $affiliate->payouts()
            ->latest('payout_date')
            ->paginate(10, ['*'], 'payoutsPage')
            ->withQueryString();

        $stats = [
            'clicks' => $affiliate->visits()->count(),
            'uniqueClicks' => $affiliate->visits()->where('is_unique', true)->count(),
            'registrations' => $affiliate->referrals()->count(),
            'conversions' => $affiliate->earnings()->where('event_type', 'lead_converted')->count(),
            'pendingEarnings' => (float) $affiliate->earnings()->where('status', 'pending')->sum('commission_amount'),
            'approvedEarnings' => (float) $affiliate->earnings()->where('status', 'approved')->sum('commission_amount'),
            'paidEarnings' => (float) $affiliate->earnings()->where('status', 'paid')->sum('commission_amount'),
        ];

        return Inertia::render('Admin/Affiliates/Show', [
            'affiliate' => $affiliate,
            'stats' => $stats,
            'referredUsers' => $referredUsers,
            'earnings' => $earnings,
            'payouts' => $payouts,
        ]);
    }

    public function edit(Affiliate $affiliate): Response
    {
        $affiliate->load('user:id,first_name,last_name,email,phone');

        return Inertia::render('Admin/Affiliates/Edit', [
            'affiliate' => $affiliate,
            'statuses' => collect(AffiliateStatus::cases())->map(fn ($status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ])->values(),
            'commissionTypes' => [
                ['value' => 'fixed', 'label' => 'Fixed'],
                ['value' => 'percentage', 'label' => 'Percentage'],
            ],
        ]);
    }

    public function update(Request $request, Affiliate $affiliate): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:'.implode(',', \App\Enums\AffiliateStatus::values())],
            'phone' => ['nullable', 'string', 'max:50'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'social_profile_url' => ['nullable', 'url', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'commission_type_override' => ['nullable', 'in:fixed,percentage'],
            'commission_value_override' => ['nullable', 'numeric', 'min:0'],
        ]);

        $affiliate->update([
            ...$validated,
            'activated_at' => $validated['status'] === AffiliateStatus::ACTIVE->value ? ($affiliate->activated_at ?? now()) : $affiliate->activated_at,
            'deactivated_at' => $validated['status'] === AffiliateStatus::INACTIVE->value ? now() : null,
        ]);

        return redirect()->route('admin.affiliates.show', $affiliate)
            ->with('success', 'Affiliate updated successfully.');
    }
}
