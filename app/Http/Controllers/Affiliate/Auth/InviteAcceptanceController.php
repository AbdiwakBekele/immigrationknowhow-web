<?php

namespace App\Http\Controllers\Affiliate\Auth;

use App\Actions\Affiliates\AcceptAffiliateInviteAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Affiliates\AcceptAffiliateInviteRequest;
use App\Models\AffiliateInvite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class InviteAcceptanceController extends Controller
{
    public function __construct(
        protected AcceptAffiliateInviteAction $acceptAffiliateInvite,
    ) {}

    public function show(string $token): Response
    {
        $invite = $this->findInvite($token);

        abort_unless($invite->isPending(), 404);

        return Inertia::render('Affiliate/AcceptInvite', [
            'invite' => [
                'name' => $invite->name,
                'email' => $invite->email,
            ],
            'token' => $token,
        ]);
    }

    public function store(AcceptAffiliateInviteRequest $request, string $token): RedirectResponse
    {
        $invite = $this->findInvite($token);
        $user = $this->acceptAffiliateInvite->handle($invite, $request->validated());
        $user->sendEmailVerificationNotification();

        Auth::login($user);

        return redirect()
            ->route('affiliate.profile.edit')
            ->with('success', 'Your password is set. Finish your affiliate profile, then verify your email to activate your dashboard.');
    }

    protected function findInvite(string $token): AffiliateInvite
    {
        return AffiliateInvite::query()
            ->where('token_hash', hash('sha256', $token))
            ->firstOrFail();
    }
}
