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
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Subscription Reports</h1>
                    <p class="text-sm text-slate-500">Plans, subscriptions, payments, and affiliate commission overview.</p>
                </div>
                <Link :href="route('admin.subscription-plans.index')" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Manage Plans
                </Link>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Total Plans</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ metrics.total_plans ?? 0 }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Active Subs</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ metrics.active_subscriptions ?? 0 }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Revenue</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">${{ ((metrics.total_revenue_cents ?? 0) / 100).toFixed(2) }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Affiliate Commissions</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">${{ Number(metrics.total_commissions ?? 0).toFixed(2) }}</p>
                </div>
            </div>

            <div class="grid gap-6 xl:grid-cols-2">
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-4 py-3">
                        <h2 class="font-semibold text-slate-900">Plan Breakdown</h2>
                    </div>
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-slate-600">
                            <tr>
                                <th class="px-4 py-2 font-medium">Plan</th>
                                <th class="px-4 py-2 font-medium">Price</th>
                                <th class="px-4 py-2 font-medium">Status</th>
                                <th class="px-4 py-2 font-medium">Active Subscribers</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="plan in planBreakdown" :key="plan.id">
                                <td class="px-4 py-2">{{ plan.name }}</td>
                                <td class="px-4 py-2">{{ (plan.price_cents / 100).toFixed(2) }} {{ plan.currency }}</td>
                                <td class="px-4 py-2 capitalize">{{ plan.status }}</td>
                                <td class="px-4 py-2">{{ plan.active_subscribers ?? 0 }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-4 py-3">
                        <h2 class="font-semibold text-slate-900">Recent Payments</h2>
                    </div>
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-slate-600">
                            <tr>
                                <th class="px-4 py-2 font-medium">Provider</th>
                                <th class="px-4 py-2 font-medium">Plan</th>
                                <th class="px-4 py-2 font-medium">Amount</th>
                                <th class="px-4 py-2 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="payment in recentPayments" :key="payment.id">
                                <td class="px-4 py-2">{{ payment.provider?.user?.first_name }} {{ payment.provider?.user?.last_name }}</td>
                                <td class="px-4 py-2">{{ payment.plan?.name ?? 'N/A' }}</td>
                                <td class="px-4 py-2">{{ (payment.amount_paid_cents / 100).toFixed(2) }} {{ payment.currency }}</td>
                                <td class="px-4 py-2 capitalize">{{ payment.status }}</td>
                            </tr>
                            <tr v-if="recentPayments.length === 0">
                                <td colspan="4" class="px-4 py-5 text-center text-slate-500">No subscription payments yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
