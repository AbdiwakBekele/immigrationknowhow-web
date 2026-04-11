<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AffiliateCommissionScope;
use App\Enums\AffiliateCommissionTrigger;
use App\Enums\AffiliateCommissionType;
use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\AffiliateCommissionRule;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AffiliateCommissionController extends Controller
{
    public function index(Request $request): Response
    {
        $rules = AffiliateCommissionRule::query()
            ->with(['affiliate.user:id,first_name,last_name,email', 'referredUser:id,first_name,last_name,email'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Affiliates/Commissions/Index', [
            'rules' => $rules,
            'scopeOptions' => collect(AffiliateCommissionScope::cases())->map(fn ($scope) => [
                'value' => $scope->value,
                'label' => $scope->label(),
            ])->values(),
            'triggerOptions' => collect(AffiliateCommissionTrigger::cases())->map(fn ($trigger) => [
                'value' => $trigger->value,
                'label' => $trigger->label(),
            ])->values(),
            'typeOptions' => collect(AffiliateCommissionType::cases())->map(fn ($type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ])->values(),
            'affiliates' => Affiliate::query()->with('user:id,first_name,last_name,email,deleted_at')->get()->map(fn ($affiliate) => [
                'id' => $affiliate->id,
                'label' => (($affiliate->user?->full_name ?: $affiliate->user?->email ?: 'Unknown affiliate user')).' ('.$affiliate->code.')',
            ]),
            'users' => User::query()->select('id', 'first_name', 'last_name', 'email')->latest()->limit(100)->get()->map(fn ($user) => [
                'id' => $user->id,
                'label' => $user->full_name.' ('.$user->email.')',
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'scope' => ['required', 'in:'.implode(',', AffiliateCommissionScope::values())],
            'trigger_event' => ['required', 'in:'.implode(',', AffiliateCommissionTrigger::values())],
            'affiliate_id' => ['nullable', 'exists:affiliates,id'],
            'referred_user_id' => ['nullable', 'exists:users,id'],
            'commission_type' => ['required', 'in:'.implode(',', AffiliateCommissionType::values())],
            'commission_value' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'priority' => ['nullable', 'integer', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        AffiliateCommissionRule::create([
            ...$validated,
            'priority' => $validated['priority'] ?? 0,
            'is_active' => $request->boolean('is_active', true),
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Commission rule saved successfully.');
    }

    public function update(Request $request, AffiliateCommissionRule $affiliateCommission): RedirectResponse
    {
        $validated = $request->validate([
            'commission_type' => ['required', 'in:'.implode(',', AffiliateCommissionType::values())],
            'commission_value' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'priority' => ['nullable', 'integer', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $affiliateCommission->update([
            ...$validated,
            'priority' => $validated['priority'] ?? 0,
            'is_active' => $request->boolean('is_active', false),
        ]);

        return back()->with('success', 'Commission rule updated.');
    }

    public function destroy(AffiliateCommissionRule $affiliateCommission): RedirectResponse
    {
        $affiliateCommission->delete();

        return back()->with('success', 'Commission rule removed.');
    }
}
