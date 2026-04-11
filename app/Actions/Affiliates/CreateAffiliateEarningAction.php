<?php

namespace App\Actions\Affiliates;

use App\Actions\Affiliates\ResolveAffiliateCommissionRuleAction;
use App\Enums\AffiliateCommissionTrigger;
use App\Enums\AffiliateCommissionType;
use App\Models\AffiliateEarning;
use App\Models\AffiliateReferral;

class CreateAffiliateEarningAction
{
    public function __construct(
        protected ResolveAffiliateCommissionRuleAction $resolveRule,
    ) {}

    public function handle(
        AffiliateReferral $referral,
        AffiliateCommissionTrigger $trigger,
        string $sourceType,
        int $sourceId,
        float $baseAmount = 0,
        ?string $notes = null,
        array $meta = [],
    ): ?AffiliateEarning {
        $resolution = $this->resolveRule->handle($referral->affiliate, $trigger, $referral->referredUser);

        if (! $resolution) {
            return null;
        }

        $commissionAmount = $resolution->commissionType === AffiliateCommissionType::PERCENTAGE->value
            ? round($baseAmount * ($resolution->commissionValue / 100), 2)
            : round($resolution->commissionValue, 2);

        return AffiliateEarning::firstOrCreate([
            'affiliate_id' => $referral->affiliate_id,
            'event_type' => $trigger->value,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
        ], [
            'referred_user_id' => $referral->referred_user_id,
            'affiliate_referral_id' => $referral->id,
            'commission_rule_id' => $resolution->ruleId,
            'rule_scope_snapshot' => $resolution->scope,
            'commission_type_snapshot' => $resolution->commissionType,
            'commission_value_snapshot' => $resolution->commissionValue,
            'base_amount' => round($baseAmount, 2),
            'commission_amount' => $commissionAmount,
            'currency' => $resolution->currency,
            'notes' => $notes,
            'meta' => $meta,
        ]);
    }
}
