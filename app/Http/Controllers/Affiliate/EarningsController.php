<?php

namespace App\Http\Controllers\Affiliate;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EarningsController extends Controller
{
    public function index(Request $request): Response
    {
        $affiliate = $request->user()->affiliateProfile()->firstOrFail();

        $earnings = $affiliate->earnings()
            ->with('referredUser:id,first_name,last_name,email')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Affiliate/Earnings/Index', [
            'earnings' => $earnings,
        ]);
    }
}
