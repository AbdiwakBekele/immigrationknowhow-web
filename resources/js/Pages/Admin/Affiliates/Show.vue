<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({
    affiliate: Object,
    stats: Object,
    referredUsers: Object,
    earnings: Object,
    payouts: Object,
});
</script>

<template>
    <Head :title="`Affiliate ${affiliate.user?.full_name || affiliate.code}`" />

    <AdminLayout>
        <div class="space-y-6">
            <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">{{ affiliate.user?.full_name || affiliate.code }}</h1>
                    <p class="mt-1 text-sm text-slate-500">{{ affiliate.user?.email }} · Code {{ affiliate.code }}</p>
                </div>
                <Link :href="route('admin.affiliates.edit', affiliate.id)" class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-700">Edit affiliate</Link>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm" v-for="item in [
                    { label: 'Clicks', value: stats.clicks },
                    { label: 'Unique clicks', value: stats.uniqueClicks },
                    { label: 'Registrations', value: stats.registrations },
                    { label: 'Conversions', value: stats.conversions },
                    { label: 'Pending', value: `$${stats.pendingEarnings}` },
                    { label: 'Approved', value: `$${stats.approvedEarnings}` },
                    { label: 'Paid', value: `$${stats.paidEarnings}` },
                ]" :key="item.label">
                    <p class="text-sm text-slate-500">{{ item.label }}</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">{{ item.value }}</p>
                </div>
            </div>

            <div class="grid gap-4 xl:grid-cols-2">
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h2 class="text-base font-semibold text-slate-900">Referred users</h2>
                    <table class="mt-4 w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-left text-slate-500">
                                <th class="pb-3">User</th>
                                <th class="pb-3">Email</th>
                                <th class="pb-3">Registered</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="referral in referredUsers.data" :key="referral.id" class="border-b border-slate-100">
                                <td class="py-3 font-medium text-slate-900">{{ referral.referred_user?.first_name }} {{ referral.referred_user?.last_name }}</td>
                                <td class="py-3 text-slate-600">{{ referral.referred_user?.email }}</td>
                                <td class="py-3 text-slate-600">{{ referral.registered_at }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h2 class="text-base font-semibold text-slate-900">Recent payouts</h2>
                    <table class="mt-4 w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-left text-slate-500">
                                <th class="pb-3">Date</th>
                                <th class="pb-3">Method</th>
                                <th class="pb-3">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="payout in payouts.data" :key="payout.id" class="border-b border-slate-100">
                                <td class="py-3 text-slate-600">{{ payout.payout_date }}</td>
                                <td class="py-3 text-slate-600">{{ payout.payment_method }}</td>
                                <td class="py-3 font-medium text-slate-900">{{ payout.currency }} {{ payout.amount }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="text-base font-semibold text-slate-900">Earnings ledger</h2>
                <table class="mt-4 w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-slate-500">
                            <th class="pb-3">Event</th>
                            <th class="pb-3">Referred user</th>
                            <th class="pb-3">Amount</th>
                            <th class="pb-3">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="earning in earnings.data" :key="earning.id" class="border-b border-slate-100">
                            <td class="py-3 text-slate-600">{{ earning.event_type }}</td>
                            <td class="py-3 text-slate-600">{{ earning.referred_user?.email }}</td>
                            <td class="py-3 font-medium text-slate-900">{{ earning.currency }} {{ earning.commission_amount }}</td>
                            <td class="py-3 capitalize text-slate-600">{{ earning.status }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>
