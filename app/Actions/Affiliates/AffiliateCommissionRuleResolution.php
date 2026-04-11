<?php

namespace App\Actions\Affiliates;

use App\Models\AffiliateCommissionRule;

class AffiliateCommissionRuleResolution
{
    public function __construct(
        public readonly string $scope,
        public readonly string $commissionType,
        public readonly float $commissionValue,
        public readonly string $currency,
        public readonly ?int $ruleId,
    ) {}

    public static function fromRule(AffiliateCommissionRule $rule): self
    {
        return new self(
            scope: $rule->scope->value,
            commissionType: $rule->commission_type->value,
            commissionValue: (float) $rule->commission_value,
            currency: $rule->currency,
            ruleId: $rule->id,
        );
    }
}
