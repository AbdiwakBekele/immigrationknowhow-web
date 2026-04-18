<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Seed default provider subscription plans.
     *
     * Skips any plan whose slug already exists (including soft-deleted rows), so re-running
     * does not duplicate rows, overwrite admin edits, or delete existing data.
     */
    public function run(): void
    {
        $plans = [
            [
                'slug' => 'professional-monthly',
                'name' => 'Professional',
                'description' => 'Full profile visibility, lead intake, and messaging for immigration service providers. Billed monthly.',
                'price_cents' => 4900,
                'currency' => 'USD',
                'billing_cycle' => 'monthly',
                'features' => [
                    'Public provider profile and search placement',
                    'Lead inbox and client messaging',
                    'Reviews and library access',
                ],
                'status' => 'active',
                'is_featured' => false,
                'sort_order' => 0,
                'commission_type' => 'percentage',
                'commission_value' => 10,
                'recurring_commission_enabled' => true,
                'max_recurring_commission_cycles' => null,
            ],
            [
                'slug' => 'professional-yearly',
                'name' => 'Professional (Annual)',
                'description' => 'Same Professional features with annual billing and a lower effective monthly rate.',
                'price_cents' => 49900,
                'currency' => 'USD',
                'billing_cycle' => 'yearly',
                'features' => [
                    'Everything in Professional',
                    'Best value with annual billing',
                    'Priority listing when featured',
                ],
                'status' => 'active',
                'is_featured' => true,
                'sort_order' => 1,
                'commission_type' => 'percentage',
                'commission_value' => 10,
                'recurring_commission_enabled' => true,
                'max_recurring_commission_cycles' => null,
            ],
        ];

        foreach ($plans as $attributes) {
            $slug = $attributes['slug'];

            if (SubscriptionPlan::withTrashed()->where('slug', $slug)->exists()) {
                continue;
            }

            SubscriptionPlan::query()->create($attributes);
        }
    }
}
