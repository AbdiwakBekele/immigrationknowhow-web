<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Contract Data Source Rollout
    |--------------------------------------------------------------------------
    |
    | use_contract_reads toggles whether UI/API should prefer the new contracts
    | table as source of truth. keep_legacy_dual_write keeps lead columns updated
    | during phased rollout so old consumers remain stable.
    |
    */
    'use_contract_reads' => env('CONTRACTS_USE_CONTRACT_READS', true),
    'keep_legacy_dual_write' => env('CONTRACTS_KEEP_LEGACY_DUAL_WRITE', true),
];
