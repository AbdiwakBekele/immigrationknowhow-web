<?php

use App\Models\SubscriptionPlan;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        SubscriptionPlan::query()
            ->where('slug', 'professional-monthly')
            ->update(['price_cents' => 999]);

        SubscriptionPlan::query()
            ->where('slug', 'professional-yearly')
            ->update(['price_cents' => 9900]);
    }

    public function down(): void
    {
        SubscriptionPlan::query()
            ->where('slug', 'professional-monthly')
            ->update(['price_cents' => 4900]);

        SubscriptionPlan::query()
            ->where('slug', 'professional-yearly')
            ->update(['price_cents' => 49900]);
    }
};
