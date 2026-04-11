<?php

namespace App\Http\Controllers\Affiliate;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayoutController extends Controller
{
    public function index(Request $request): Response
    {
        $affiliate = $request->user()->affiliateProfile()->firstOrFail();

        $payouts = $affiliate->payouts()
            ->with('items.earning')
            ->latest('payout_date')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Affiliate/Payouts/Index', [
            'payouts' => $payouts,
        ]);
    }
}
