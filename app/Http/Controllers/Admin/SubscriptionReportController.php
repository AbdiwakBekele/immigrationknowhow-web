<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AffiliateEarning;
use App\Models\ProviderSubscription;
use App\Models\ProviderSubscriptionPayment;
use App\Models\SubscriptionPlan;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionReportController extends Controller
{
    public function index(): Response
    {
        $activeSubscriptions = ProviderSubscription::query()
            ->whereIn('status', ['trialing', 'active', 'past_due'])
            ->count();

        $paidAmountCents = (int) ProviderSubscriptionPayment::query()
            ->where('status', 'paid')
            ->sum('amount_paid_cents');

        $commissionAmount = (float) AffiliateEarning::query()
            ->whereIn('event_type', ['provider_subscription_initial', 'provider_subscription_recurring'])
            ->sum('commission_amount');

        $planBreakdown = SubscriptionPlan::query()
            ->withCount(['subscriptions as active_subscribers' => function ($query) {
                $query->whereIn('status', ['trialing', 'active', 'past_due']);
            }])
            ->orderBy('sort_order')
            ->get(['id', 'name', 'billing_cycle', 'price_cents', 'currency', 'status']);

        return Inertia::render('Admin/Subscriptions/Reports', [
            'metrics' => [
                'total_plans' => SubscriptionPlan::query()->count(),
                'active_subscriptions' => $activeSubscriptions,
                'total_revenue_cents' => $paidAmountCents,
                'total_commissions' => round($commissionAmount, 2),
            ],
            'planBreakdown' => $planBreakdown,
            'recentPayments' => ProviderSubscriptionPayment::query()
                ->with(['provider.user:id,first_name,last_name', 'plan:id,name'])
                ->latest('paid_at')
                ->latest('id')
                ->limit(20)
                ->get(),
        ]);
    }
}
