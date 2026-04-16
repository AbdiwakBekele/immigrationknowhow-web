<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import ProviderLayout from '@/Layouts/ProviderLayout.vue';

const props = defineProps({
    plans: { type: Array, default: () => [] },
    currentSubscription: { type: Object, default: null },
    subscriptionHistory: { type: Array, default: () => [] },
});

const currentPlanId = computed(() => props.currentSubscription?.subscription_plan_id ?? null);

const checkout = (planUuid) => {
    router.post(route('provider.subscriptions.checkout', planUuid));
};

const cancelSubscription = () => {
    const id = props.currentSubscription?.uuid;
    if (!id) return;
    router.post(route('provider.subscriptions.cancel', id));
};

const resumeSubscription = () => {
    const id = props.currentSubscription?.uuid;
    if (!id) return;
    router.post(route('provider.subscriptions.resume', id));
};

const changePlan = (planUuid) => {
    const id = props.currentSubscription?.uuid;
    if (!id) return;
    router.post(route('provider.subscriptions.change-plan', { subscription: id, plan: planUuid }));
};
</script>

<template>
    <Head title="Subscriptions" />

    <ProviderLayout>
        <div class="admin-page-container">
            <section class="admin-hero-card">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Billing</p>
                    <h1 class="mt-2 admin-title">Subscription Plans</h1>
                    <p class="admin-subtitle">Choose, upgrade, downgrade, or cancel your provider subscription.</p>
                </div>
                <div v-if="currentSubscription" class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                    <p class="font-semibold text-slate-900">Current: {{ currentSubscription.plan?.name ?? 'Active plan' }}</p>
                    <p class="text-slate-500">Status: {{ currentSubscription.status }}</p>
                </div>
                </div>
            </section>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div v-for="plan in plans" :key="plan.uuid" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-base font-semibold text-slate-900">{{ plan.name }}</p>
                    <p class="mt-1 text-sm text-slate-500">{{ plan.description }}</p>
                    <p class="mt-3 text-2xl font-bold text-slate-900">${{ (plan.price_cents / 100).toFixed(2) }}</p>
                    <p class="text-xs uppercase tracking-wide text-slate-500">{{ plan.billing_cycle }}</p>

                    <ul class="mt-3 space-y-1 text-sm text-slate-600">
                        <li v-for="(feature, idx) in (plan.features || [])" :key="`${plan.uuid}-${idx}`">• {{ feature }}</li>
                    </ul>

                    <div class="mt-4 flex items-center gap-2">
                        <button
                            v-if="!currentSubscription"
                            type="button"
                            class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700"
                            @click="checkout(plan.uuid)"
                        >
                            Subscribe
                        </button>
                        <span v-else-if="currentPlanId === plan.id" class="rounded-lg bg-slate-100 px-3 py-2 text-sm font-medium text-slate-700">
                            Current Plan
                        </span>
                        <button
                            v-else
                            type="button"
                            class="rounded-lg border border-sky-300 bg-sky-50 px-3 py-2 text-sm font-medium text-sky-700 hover:bg-sky-100"
                            @click="changePlan(plan.uuid)"
                        >
                            Switch Plan
                        </button>
                    </div>
                </div>
            </div>

            <div v-if="currentSubscription" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">Manage Current Subscription</h2>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <button
                        v-if="currentSubscription.cancel_at_period_end"
                        type="button"
                        class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700"
                        @click="resumeSubscription"
                    >
                        Resume
                    </button>
                    <button
                        v-else
                        type="button"
                        class="rounded-lg bg-rose-600 px-3 py-2 text-sm font-medium text-white hover:bg-rose-700"
                        @click="cancelSubscription"
                    >
                        Cancel at period end
                    </button>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-4 py-3">
                    <h2 class="font-semibold text-slate-900">Subscription History</h2>
                </div>
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-600">
                        <tr>
                            <th class="px-4 py-2 font-medium">Plan</th>
                            <th class="px-4 py-2 font-medium">Status</th>
                            <th class="px-4 py-2 font-medium">Period End</th>
                            <th class="px-4 py-2 font-medium">Payments</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="row in subscriptionHistory" :key="row.uuid">
                            <td class="px-4 py-2">{{ row.plan?.name ?? 'Unknown Plan' }}</td>
                            <td class="px-4 py-2 capitalize">{{ row.status }}</td>
                            <td class="px-4 py-2">{{ row.current_period_end ? new Date(row.current_period_end).toLocaleDateString() : '—' }}</td>
                            <td class="px-4 py-2">{{ row.payments?.length ?? 0 }}</td>
                        </tr>
                        <tr v-if="subscriptionHistory.length === 0">
                            <td colspan="4" class="px-4 py-6 text-center text-slate-500">No subscriptions yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p class="text-xs text-slate-500">
                Payments are processed securely by Stripe.
                <Link :href="route('provider.dashboard')" class="text-sky-600 hover:text-sky-700">Back to dashboard</Link>
            </p>
        </div>
    </ProviderLayout>
</template>
