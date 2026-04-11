<?php

namespace App\Http\Controllers\Affiliate\Auth;

use App\Actions\Affiliates\RegisterAffiliateAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Affiliates\StoreAffiliateRegistrationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RegistrationController extends Controller
{
    public function __construct(
        protected RegisterAffiliateAction $registerAffiliate,
    ) {}

    public function create(): Response
    {
        return Inertia::render('Affiliate/Register');
    }

    public function store(StoreAffiliateRegistrationRequest $request): RedirectResponse
    {
        $user = $this->registerAffiliate->handle($request->validated());
        $user->sendEmailVerificationNotification();

        Auth::login($user);

        return redirect()
            ->route('verification.notice')
            ->with('success', 'Your affiliate account has been created. Please verify your email to continue.');
    }
}
