<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Affiliates\CreateAffiliateInviteAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Affiliates\StoreAffiliateInviteRequest;
use App\Models\AffiliateInvite;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class AffiliateInviteController extends Controller
{
    public function __construct(
        protected CreateAffiliateInviteAction $createAffiliateInvite,
    ) {}

    public function create(): Response
    {
        return Inertia::render('Admin/Affiliates/Invite', [
            'commissionTypes' => [
                ['value' => 'fixed', 'label' => 'Fixed'],
                ['value' => 'percentage', 'label' => 'Percentage'],
            ],
        ]);
    }

    public function store(StoreAffiliateInviteRequest $request): RedirectResponse
    {
        [$invite] = $this->createAffiliateInvite->handle($request->validated(), $request->user());

        return redirect()->route('admin.affiliates.index')
            ->with('success', 'Affiliate invitation sent to '.$invite->email.'.');
    }

    public function resend(AffiliateInvite $affiliateInvite): RedirectResponse
    {
        abort_if($affiliateInvite->accepted_at, 422, 'This invitation has already been accepted.');
        abort_if($affiliateInvite->cancelled_at, 422, 'This invitation has been cancelled.');

        $this->createAffiliateInvite->resend($affiliateInvite);

        return back()->with('success', 'Affiliate invitation resent to '.$affiliateInvite->email.'.');
    }
}
