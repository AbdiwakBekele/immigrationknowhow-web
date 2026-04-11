<?php

namespace App\Actions\Affiliates;

use App\Actions\Affiliates\AffiliateCommissionRuleResolution;
use App\Enums\AffiliateCommissionScope;
use App\Enums\AffiliateCommissionTrigger;
use App\Models\Affiliate;
use App\Models\AffiliateCommissionRule;
use App\Models\User;

class ResolveAffiliateCommissionRuleAction
{
    public function handle(Affiliate $affiliate, AffiliateCommissionTrigger $trigger, ?User $referredUser = null): ?AffiliateCommissionRuleResolution
    {
        if ($referredUser) {
            $userRule = AffiliateCommissionRule::query()
                ->active()
                ->where('scope', AffiliateCommissionScope::REFERRED_USER)
                ->where('trigger_event', $trigger)
                ->where('referred_user_id', $referredUser->id)
                ->orderByDesc('priority')
                ->latest('id')
                ->first();

            if ($userRule) {
                return AffiliateCommissionRuleResolution::fromRule($userRule);
            }
        }

        if ($affiliate->commission_type_override && $affiliate->commission_value_override !== null) {
            return new AffiliateCommissionRuleResolution(
                scope: AffiliateCommissionScope::AFFILIATE->value,
                commissionType: $affiliate->commission_type_override->value,
                commissionValue: (float) $affiliate->commission_value_override,
                currency: config('affiliates.default_currency', 'USD'),
                ruleId: null,
            );
        }

        $affiliateRule = AffiliateCommissionRule::query()
            ->active()
            ->where('scope', AffiliateCommissionScope::AFFILIATE)
            ->where('trigger_event', $trigger)
            ->where('affiliate_id', $affiliate->id)
            ->orderByDesc('priority')
            ->latest('id')
            ->first();

        if ($affiliateRule) {
            return AffiliateCommissionRuleResolution::fromRule($affiliateRule);
        }

        $globalRule = AffiliateCommissionRule::query()
            ->active()
            ->where('scope', AffiliateCommissionScope::GLOBAL)
            ->where('trigger_event', $trigger)
            ->orderByDesc('priority')
            ->latest('id')
            ->first();

        return $globalRule ? AffiliateCommissionRuleResolution::fromRule($globalRule) : null;
    }
}
