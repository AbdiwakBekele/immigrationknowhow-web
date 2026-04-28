<?php

namespace App\Data;

use App\Models\Contract;

class ContractSettlementInput
{
    public function __construct(
        public readonly int $contractId,
        public readonly int $userId,
        public readonly int $serviceProviderId,
        public readonly ?float $agreedRate,
        public readonly ?string $pricingModel,
        public readonly string $currency,
    ) {}

    public static function fromContract(Contract $contract): self
    {
        return new self(
            contractId: $contract->id,
            userId: $contract->user_id,
            serviceProviderId: $contract->service_provider_id,
            agreedRate: $contract->agreed_rate !== null ? (float) $contract->agreed_rate : null,
            pricingModel: $contract->pricing_model,
            currency: $contract->currency ?: 'USD',
        );
    }
}
