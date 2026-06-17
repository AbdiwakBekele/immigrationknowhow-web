<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('service_providers')) {
            return;
        }

        Schema::table('service_providers', function (Blueprint $table): void {
            if (! Schema::hasColumn('service_providers', 'subscription_plan')) {
                $table->string('subscription_plan')->nullable()->after('accepting_clients');
            }

            if (! Schema::hasColumn('service_providers', 'subscription_expires_at')) {
                $table->timestamp('subscription_expires_at')->nullable()->after('subscription_plan');
            }

            if (! Schema::hasColumn('service_providers', 'stripe_customer_id')) {
                $table->string('stripe_customer_id')->nullable()->after('subscription_expires_at');
            }

            if (! Schema::hasColumn('service_providers', 'stripe_subscription_id')) {
                $table->string('stripe_subscription_id')->nullable()->after('stripe_customer_id');
            }

            if (! Schema::hasColumn('service_providers', 'stripe_subscription_status')) {
                $table->string('stripe_subscription_status')->nullable()->after('stripe_subscription_id');
            }

            if (! Schema::hasColumn('service_providers', 'stripe_current_period_end')) {
                $table->timestamp('stripe_current_period_end')->nullable()->after('stripe_subscription_status');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('service_providers')) {
            return;
        }

        Schema::table('service_providers', function (Blueprint $table): void {
            $columns = [
                'stripe_current_period_end',
                'stripe_subscription_status',
                'stripe_subscription_id',
                'stripe_customer_id',
                'subscription_expires_at',
                'subscription_plan',
            ];

            $toDrop = array_values(array_filter($columns, fn (string $column): bool => Schema::hasColumn('service_providers', $column)));

            if ($toDrop !== []) {
                $table->dropColumn($toDrop);
            }
        });
    }
};

