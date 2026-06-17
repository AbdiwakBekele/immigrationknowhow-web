<?php

use App\Models\SubscriptionPlan;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        SubscriptionPlan::query()
            ->where('slug', 'professional-yearly')
            ->update(['price_cents' => 9999]);
    }

    public function down(): void
    {
        SubscriptionPlan::query()
            ->where('slug', 'professional-yearly')
            ->update(['price_cents' => 9990]);
    }
};
