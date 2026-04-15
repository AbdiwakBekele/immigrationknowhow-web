<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({
    metrics: { type: Object, default: () => ({}) },
    planBreakdown: { type: Array, default: () => [] },
    recentPayments: { type: Array, default: () => [] },
});
</script>

<template>
    <Head title="Subscription Reports" />

    <AdminLayout>
        <div class="admin-page-container">
            <section class="admin-hero-card">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                            Subscription analytics
                        </p>
                        <h1 class="mt-2 admin-title">
                            Subscription Reports
                        </h1>
                        <p class="admin-subtitle">
                            Monitor plan performance, active subscriptions, and payment trends in one consolidated view.
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <Link :href="route('admin.subscription-plans.index')" class="inline-flex items-center rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                            Manage Plans
                        </Link>
                    </div>
                </div>
            </section>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="admin-panel-compact">
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Total Plans</p>
                    <p class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">{{ metrics.total_plans ?? 0 }}</p>
                </div>
                <div class="admin-panel-compact">
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Active Subs</p>
                    <p class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">{{ metrics.active_subscriptions ?? 0 }}</p>
                </div>
                <div class="admin-panel-compact">
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Revenue</p>
                    <p class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">${{ ((metrics.total_revenue_cents ?? 0) / 100).toFixed(2) }}</p>
                </div>
                <div class="admin-panel-compact">
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Affiliate Commissions</p>
                    <p class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">${{ Number(metrics.total_commissions ?? 0).toFixed(2) }}</p>
                </div>
            </div>

            <div class="grid gap-6 xl:grid-cols-2">
                <div class="overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-6 py-4">
                        <h2 class="text-lg font-semibold text-slate-900">Plan Breakdown</h2>
                    </div>
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50/80 text-left text-slate-600">
                            <tr>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Plan</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Price</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Status</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Active Subscribers</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="plan in planBreakdown" :key="plan.id">
                                <td class="px-6 py-3">{{ plan.name }}</td>
                                <td class="px-6 py-3">{{ (plan.price_cents / 100).toFixed(2) }} {{ plan.currency }}</td>
                                <td class="px-6 py-3 capitalize">{{ plan.status }}</td>
                                <td class="px-6 py-3">{{ plan.active_subscribers ?? 0 }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-6 py-4">
                        <h2 class="text-lg font-semibold text-slate-900">Recent Payments</h2>
                    </div>
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50/80 text-left text-slate-600">
                            <tr>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Provider</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Plan</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Amount</th>
                                <th class="px-6 py-3 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="payment in recentPayments" :key="payment.id">
                                <td class="px-6 py-3">{{ payment.provider?.user?.first_name }} {{ payment.provider?.user?.last_name }}</td>
                                <td class="px-6 py-3">{{ payment.plan?.name ?? 'N/A' }}</td>
                                <td class="px-6 py-3">{{ (payment.amount_paid_cents / 100).toFixed(2) }} {{ payment.currency }}</td>
                                <td class="px-6 py-3 capitalize">{{ payment.status }}</td>
                            </tr>
                            <tr v-if="recentPayments.length === 0">
                                <td colspan="4" class="px-6 py-12 text-center text-sm text-slate-500">No subscription payments yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
