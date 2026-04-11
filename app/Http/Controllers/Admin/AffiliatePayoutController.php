<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AffiliateEarningStatus;
use App\Notifications\AffiliatePayoutRecordedNotification;
use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\AffiliateEarning;
use App\Models\AffiliatePayout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AffiliatePayoutController extends Controller
{
    public function index(Request $request): Response
    {
        $payouts = AffiliatePayout::query()
            ->with(['affiliate.user:id,first_name,last_name,email', 'recorder:id,first_name,last_name'])
            ->latest('payout_date')
            ->paginate(15)
            ->withQueryString();

        $affiliates = Affiliate::query()
            ->with('user:id,first_name,last_name,email,deleted_at')
            ->get()
            ->map(fn ($affiliate) => [
                'id' => $affiliate->id,
                'label' => (($affiliate->user?->full_name ?: $affiliate->user?->email ?: 'Unknown affiliate user')).' ('.$affiliate->code.')',
            ]);

        return Inertia::render('Admin/Affiliates/Payouts/Index', [
            'payouts' => $payouts,
            'affiliates' => $affiliates,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'affiliate_id' => ['required', 'exists:affiliates,id'],
            'earning_ids' => ['required', 'array', 'min:1'],
            'earning_ids.*' => ['integer', 'exists:affiliate_earnings,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'size:3'],
            'payout_date' => ['required', 'date'],
            'payment_method' => ['required', 'string', 'max:255'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $affiliate = Affiliate::with('user')->findOrFail($validated['affiliate_id']);
        $earnings = AffiliateEarning::query()
            ->where('affiliate_id', $affiliate->id)
            ->whereIn('id', $validated['earning_ids'])
            ->whereIn('status', [AffiliateEarningStatus::APPROVED->value, AffiliateEarningStatus::PENDING->value])
            ->get();

        DB::transaction(function () use ($validated, $request, $affiliate, $earnings) {
            $payout = AffiliatePayout::create([
                'affiliate_id' => $affiliate->id,
                'amount' => $validated['amount'],
                'currency' => strtoupper($validated['currency']),
                'payout_date' => $validated['payout_date'],
                'payment_method' => $validated['payment_method'],
                'payment_reference' => $validated['payment_reference'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'recorded_by' => $request->user()->id,
            ]);

            $remaining = (float) $validated['amount'];

            foreach ($earnings as $earning) {
                if ($remaining <= 0) {
                    break;
                }

                $payAmount = min($remaining, (float) $earning->commission_amount);

                $payout->items()->create([
                    'affiliate_earning_id' => $earning->id,
                    'amount_paid' => $payAmount,
                ]);

                if ($payAmount >= (float) $earning->commission_amount) {
                    $earning->markPaid();
                } else {
                    $earning->approve($request->user());
                }

                $remaining -= $payAmount;
            }

            $affiliate->user?->notify(new AffiliatePayoutRecordedNotification($payout));
        });

        return back()->with('success', 'Affiliate payout recorded successfully.');
    }
}
