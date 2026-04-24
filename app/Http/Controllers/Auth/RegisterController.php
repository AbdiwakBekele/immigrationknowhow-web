<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Affiliates\AttachAffiliateReferralToUserAction;
use App\Actions\Affiliates\CreateAffiliateEarningAction;
use App\Enums\AffiliateCommissionTrigger;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\RoleAwareTransactionalEmailNotification;
use App\Models\EmailTemplate;
use App\Support\RoleHelper;
use App\Support\ServiceTypeOptions;
use App\Support\UserHomeUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class RegisterController extends Controller
{
    public function __construct(
        protected AttachAffiliateReferralToUserAction $attachAffiliateReferral,
        protected CreateAffiliateEarningAction $createAffiliateEarning,
    ) {}

    public function create(Request $request): Response
    {
        $initialRole = $request->string('role')->toString();
        if (! in_array($initialRole, [UserRole::USER->value, UserRole::PROVIDER->value, UserRole::ADVERTISER->value], true)) {
            $initialRole = UserRole::USER->value;
        }

        return Inertia::render('Auth/Register', [
            'roles' => [
                ['value' => UserRole::USER->value, 'label' => UserRole::USER->label(), 'description' => UserRole::USER->description()],
                ['value' => UserRole::PROVIDER->value, 'label' => UserRole::PROVIDER->label(), 'description' => UserRole::PROVIDER->description()],
                ['value' => UserRole::ADVERTISER->value, 'label' => UserRole::ADVERTISER->label(), 'description' => UserRole::ADVERTISER->description()],
            ],
            'initialRole' => $initialRole,
            // Provider dropdown: active rows from service_type_options with for_provider = true
            // (plus enum fallback when the table is empty — see ServiceTypeOptions::selectOptions).
            'serviceTypes' => ServiceTypeOptions::selectOptions('provider'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($request->input('service_type') === '') {
            $request->merge(['service_type' => null]);
        }

        $requestedRole = (string) $request->input('role', UserRole::USER->value);
        $normalizedRole = in_array($requestedRole, [UserRole::USER->value, UserRole::PROVIDER->value, UserRole::ADVERTISER->value], true)
            ? $requestedRole
            : UserRole::USER->value;
        $effectiveRole = $request->filled('service_type')
            ? UserRole::PROVIDER->value
            : $normalizedRole;

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => ['nullable', 'string', 'in:'.UserRole::USER->value.','.UserRole::PROVIDER->value.','.UserRole::ADVERTISER->value],
            'service_type' => [
                Rule::requiredIf($effectiveRole === UserRole::PROVIDER->value),
                'nullable',
                'string',
                Rule::in(ServiceTypeOptions::values('provider')),
            ],
        ]);

        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'onboarding_data' => [
                'registration' => [
                    'role' => $effectiveRole,
                    'service_type' => $validated['service_type'] ?? null,
                ],
            ],
        ]);

        RoleHelper::ensureExists($effectiveRole);
        $user->assignRole($effectiveRole);
        try {
            $user->notify(new RoleAwareTransactionalEmailNotification(EmailTemplate::EVENT_WELCOME, $user, [
                'role' => $effectiveRole,
                'dashboard_link' => UserHomeUrl::afterAuthentication($user),
            ]));
        } catch (Throwable $exception) {
            report($exception);
        }

        $referral = $this->attachAffiliateReferral->handle($user, $request);
        if ($referral) {
            $this->createAffiliateEarning->handle(
                $referral,
                AffiliateCommissionTrigger::SIGNUP,
                User::class,
                $user->id,
                0,
                'Signup referral commission generated automatically.',
            );
        }

        Auth::login($user);

        if ($effectiveRole === UserRole::ADVERTISER->value) {
            return redirect()->route('onboarding.advertiser', ['step' => 2]);
        }

        if ($effectiveRole === UserRole::USER->value) {
            return redirect()->route('onboarding.index', ['step' => 2]);
        }

        // Providers still complete coverage area before phone verification.
        return redirect()->route('address-detail');
    }
}
