<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import ProviderLayout from '@/Layouts/ProviderLayout.vue';

const props = defineProps({
    plans: { type: Array, default: () => [] },
    currentSubscription: { type: Object, default: null },
    subscriptionHistory: { type: Array, default: () => [] },
    stripeBillingConfigured: { type: Boolean, default: true },
    showStripeSetupHints: { type: Boolean, default: false },
});

const currentPlanId = computed(() => props.currentSubscription?.subscription_plan_id ?? null);

const formatDate = (value) => {
    if (!value) return '—';
    try {
        return new Date(value).toLocaleDateString();
    } catch {
        return '—';
    }
};

const computePeriodEnd = (row) => {
    if (row?.current_period_end) return formatDate(row.current_period_end);
    const start = row?.current_period_start || row?.started_at;
    const cycle = String(row?.plan?.billing_cycle || '').toLowerCase();
    if (!start || !cycle) return '—';
    const d = new Date(start);
    if (Number.isNaN(d.getTime())) return '—';
    if (cycle === 'monthly') d.setMonth(d.getMonth() + 1);
    else if (cycle === 'yearly' || cycle === 'annual') d.setFullYear(d.getFullYear() + 1);
    else if (cycle === 'quarterly') d.setMonth(d.getMonth() + 3);
    else return '—';
    return d.toLocaleDateString();
};

const paidPaymentsCount = (row) => (row?.payments || []).filter((p) => p?.status === 'paid').length;

const totalPaidLabel = (row) => {
    const payments = Array.isArray(row?.payments) ? row.payments : [];
    const totalCents = payments.reduce((sum, p) => sum + Number(p?.amount_paid_cents || 0), 0);
    if (!totalCents) return '—';
    const currency = String(payments[0]?.currency || row?.plan?.currency || 'USD').toUpperCase();
    const amount = (totalCents / 100).toFixed(2);
    return `${amount} ${currency}`;
};

const freePlans = computed(() =>
    (props.plans || []).filter((plan) => Number(plan?.price_cents || 0) <= 0),
);
const paidPlans = computed(() =>
    (props.plans || []).filter((plan) => Number(plan?.price_cents || 0) > 0),
);
const showStripePaidNotice = computed(
    () => !props.stripeBillingConfigured && paidPlans.value.length > 0,
);

const checkoutLoadingPlanUuid = ref(null);

const paidPlanCheckoutBlocked = (plan) => {
    if (!plan || (plan.price_cents ?? 0) <= 0) {
        return null;
    }
    if (!props.stripeBillingConfigured) {
        return 'stripe';
    }
    if (!plan.stripe_price_id) {
        return 'missing_price_id';
    }
    return null;
};

const checkout = (planUuid) => {
    checkoutLoadingPlanUuid.value = planUuid;
    router.post(route('provider.subscriptions.checkout', planUuid), {}, {
        preserveScroll: true,
        onFinish: () => {
            checkoutLoadingPlanUuid.value = null;
        },
    });
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
    if (!id) {
        // eslint-disable-next-line no-console
        console.log('[Subscriptions][changePlan] blocked: missing currentSubscription.uuid', {
            currentSubscription: props.currentSubscription,
            planUuid,
        });
        return;
    }
    // Inertia expects a path ("/provider/..."), not a fully-qualified URL.
    // If we pass "http://...", it may prefix it again (http://app/http://app/...).
    const rawUrl = String(route('provider.subscriptions.change-plan', [id, planUuid]) || '');
    let path = rawUrl;
    try {
        // If rawUrl is absolute, strip origin → path+query.
        if (rawUrl.startsWith('http://') || rawUrl.startsWith('https://')) {
            const parsed = new URL(rawUrl);
            path = `${parsed.pathname}${parsed.search}${parsed.hash}`;
        }
    } catch {
        // ignore parse failures; fall back to rawUrl
        path = rawUrl;
    }
    if (!path.startsWith('/')) {
        path = `/${path}`;
    }

    // eslint-disable-next-line no-console
    console.log('[Subscriptions][changePlan] posting', {
        subscriptionUuid: id,
        planUuid,
        rawUrl,
        computedPath: path,
        windowOrigin: window.location.origin,
        windowHref: window.location.href,
    });

    router.post(path, {}, {
        preserveScroll: true,
        onStart: () => {
            // eslint-disable-next-line no-console
            console.log('[Subscriptions][changePlan] request start', { path });
        },
        onSuccess: (page) => {
            // eslint-disable-next-line no-console
            console.log('[Subscriptions][changePlan] request success', {
                path,
                nextUrl: page?.url,
            });
        },
        onError: (errors) => {
            // eslint-disable-next-line no-console
            console.log('[Subscriptions][changePlan] request error', {
                path,
                errors,
            });
        },
        onFinish: () => {
            // eslint-disable-next-line no-console
            console.log('[Subscriptions][changePlan] request finish', { path });
        },
    });
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
                    <p class="admin-subtitle">
                        Choose, upgrade, downgrade, or cancel your provider subscription.
                        <template v-if="stripeBillingConfigured">
                            For paid plans, you will open Stripe's secure checkout and pay with a debit or credit card (no separate invoice step).
                        </template>
                        <template v-else>
                            Free plans activate on this site. Paid plans use Stripe when billing is configured.
                        </template>
                    </p>
                </div>
                <div v-if="currentSubscription" class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                    <p class="font-semibold text-slate-900">Current: {{ currentSubscription.plan?.name ?? 'Active plan' }}</p>
                    <p class="text-slate-500">Status: {{ currentSubscription.status }}</p>
                </div>
                </div>
            </section>

            <div v-if="freePlans.length" class="space-y-3">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Free</h2>
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <div v-for="plan in freePlans" :key="plan.uuid" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-base font-semibold text-slate-900">{{ plan.name }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ plan.description }}</p>
                        <p class="mt-3 text-2xl font-bold text-emerald-600">Free</p>

                        <ul class="mt-3 space-y-1 text-sm text-slate-600">
                            <li v-for="(feature, idx) in (plan.features || [])" :key="`${plan.uuid}-${idx}`">• {{ feature }}</li>
                        </ul>

                        <div class="mt-4 flex flex-col gap-2">
                            <template v-if="!currentSubscription">
                                <button
                                    type="button"
                                    class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="checkoutLoadingPlanUuid === plan.uuid"
                                    @click="checkout(plan.uuid)"
                                >
                                    <span v-if="checkoutLoadingPlanUuid === plan.uuid">Activating…</span>
                                    <span v-else>Activate free plan</span>
                                </button>
                            </template>
                            <span
                                v-else-if="currentPlanId === plan.id"
                                class="rounded-lg bg-slate-100 px-3 py-2 text-sm font-medium text-slate-700"
                            >
                                Current Plan
                            </span>
                            <template v-else>
                                <button
                                    type="button"
                                    class="rounded-lg border border-sky-300 bg-sky-50 px-3 py-2 text-sm font-medium text-sky-700 hover:bg-sky-100"
                                    :disabled="Boolean(paidPlanCheckoutBlocked(plan))"
                                    @click="!paidPlanCheckoutBlocked(plan) ? changePlan(plan.uuid) : null"
                                >
                                    Switch Plan
                                </button>
                                <p
                                    v-if="paidPlanCheckoutBlocked(plan) === 'missing_price_id'"
                                    class="text-xs text-amber-700"
                                >
                                    This plan isn’t fully configured for Stripe yet.
                                </p>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <div
                v-if="showStripePaidNotice"
                class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950 shadow-sm"
            >
                <p class="font-semibold text-amber-900">Stripe is not configured for this application</p>
                <p class="mt-1 text-amber-800">
                    <template v-if="freePlans.length">
                        Paid plans cannot start checkout until Stripe billing is enabled. You can still use a free plan above.
                    </template>
                    <template v-else>
                        Paid plans cannot start checkout until Stripe billing is enabled for this application.
                    </template>
                </p>
                <p v-if="showStripeSetupHints" class="mt-2 font-mono text-xs text-amber-900/90">
                    Set <span class="font-semibold">STRIPE_SECRET</span> (and <span class="font-semibold">STRIPE_KEY</span> where needed) in
                    <span class="font-semibold">.env</span>, then run <span class="font-semibold">php artisan config:clear</span>.
                </p>
            </div>

            <div v-if="paidPlans.length" class="space-y-3">
                <h2 v-if="freePlans.length" class="text-sm font-semibold uppercase tracking-wide text-slate-500">Paid</h2>
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <div v-for="plan in paidPlans" :key="plan.uuid" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-base font-semibold text-slate-900">{{ plan.name }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ plan.description }}</p>
                        <p class="mt-3 text-2xl font-bold text-slate-900">${{ (plan.price_cents / 100).toFixed(2) }}</p>
                        <p class="text-xs uppercase tracking-wide text-slate-500">{{ plan.billing_cycle }}</p>

                        <ul class="mt-3 space-y-1 text-sm text-slate-600">
                            <li v-for="(feature, idx) in (plan.features || [])" :key="`${plan.uuid}-${idx}`">• {{ feature }}</li>
                        </ul>

                        <div class="mt-4 flex flex-col gap-2">
                            <template v-if="!currentSubscription">
                                <button
                                    type="button"
                                    class="rounded-lg bg-sky-600 px-3 py-2 text-sm font-medium text-white hover:bg-sky-700 disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="Boolean(paidPlanCheckoutBlocked(plan)) || checkoutLoadingPlanUuid === plan.uuid"
                                    @click="checkout(plan.uuid)"
                                >
                                    <span v-if="checkoutLoadingPlanUuid === plan.uuid">Opening secure checkout…</span>
                                    <span v-else>Subscribe with Stripe</span>
                                </button>
                            </template>
                            <span
                                v-else-if="currentPlanId === plan.id"
                                class="rounded-lg bg-slate-100 px-3 py-2 text-sm font-medium text-slate-700"
                            >
                                Current Plan
                            </span>
                            <template v-else>
                                <button
                                    type="button"
                                    class="rounded-lg border border-sky-300 bg-sky-50 px-3 py-2 text-sm font-medium text-sky-700 hover:bg-sky-100 disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="Boolean(paidPlanCheckoutBlocked(plan))"
                                    @click="changePlan(plan.uuid)"
                                >
                                    Switch Plan
                                </button>
                                <p
                                    v-if="paidPlanCheckoutBlocked(plan) === 'missing_price_id'"
                                    class="text-xs text-amber-700"
                                >
                                    This plan isn’t fully configured for Stripe yet.
                                </p>
                            </template>
                        </div>
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
                            <th class="px-4 py-2 font-medium">Started</th>
                            <th class="px-4 py-2 font-medium">Period End</th>
                            <th class="px-4 py-2 font-medium">Payments</th>
                            <th class="px-4 py-2 font-medium">Paid</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="row in subscriptionHistory" :key="row.uuid">
                            <td class="px-4 py-2">{{ row.plan?.name ?? 'Unknown Plan' }}</td>
                            <td class="px-4 py-2 capitalize">{{ row.status }}</td>
                            <td class="px-4 py-2">{{ formatDate(row.current_period_start || row.started_at || row.created_at) }}</td>
                            <td class="px-4 py-2">{{ computePeriodEnd(row) }}</td>
                            <td class="px-4 py-2">{{ paidPaymentsCount(row) }}</td>
                            <td class="px-4 py-2">{{ totalPaidLabel(row) }}</td>
                        </tr>
                        <tr v-if="subscriptionHistory.length === 0">
                            <td colspan="6" class="px-4 py-6 text-center text-slate-500">No subscriptions yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p class="text-xs text-slate-500">
                <template v-if="stripeBillingConfigured">
                    Card payments run on Stripe Checkout; your card details stay with Stripe.
                </template>
                <template v-else> Free plans activate without Stripe. </template>
                <Link :href="route('provider.dashboard')" class="text-sky-600 hover:text-sky-700">Back to dashboard</Link>
            </p>
        </div>
    </ProviderLayout>
</template>
