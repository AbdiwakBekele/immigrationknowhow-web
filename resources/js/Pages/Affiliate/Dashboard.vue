<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useToast } from 'vue-toastification';
import AffiliateLayout from '@/Layouts/AffiliateLayout.vue';

const props = defineProps({
    affiliate: Object,
    stats: Object,
    trend: Array,
    recentReferrals: Array,
    recentPayouts: Array,
});

const toast = useToast();

const referralUrl = computed(() => {
    const origin = typeof window !== 'undefined' ? window.location.origin : '';

    return `${origin}/?ref=${props.affiliate.code}`;
});

const copyLink = async () => {
    try {
        if (typeof navigator !== 'undefined' && navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(referralUrl.value);
            toast.success('Referral link copied to clipboard.');

            return;
        }

        if (typeof document !== 'undefined') {
            const input = document.createElement('textarea');
            input.value = referralUrl.value;
            input.setAttribute('readonly', '');
            input.style.position = 'absolute';
            input.style.left = '-9999px';
            document.body.appendChild(input);
            input.select();
            document.execCommand('copy');
            document.body.removeChild(input);

            toast.success('Referral link copied to clipboard.');

            return;
        }

        toast.info(`Copy this link manually: ${referralUrl.value}`);
    } catch (error) {
        toast.error('Copy failed. Please copy the referral link manually.');
    }
};
</script>

<template>
    <Head title="Affiliate Dashboard" />

    <AffiliateLayout>
        <div class="space-y-6">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h1 class="text-2xl font-bold text-slate-900">Affiliate Dashboard</h1>
                <p class="mt-1 text-sm text-slate-500">Track clicks, referrals, conversions, and payouts from one place.</p>
                <div class="mt-4 flex flex-col gap-3 rounded-xl bg-slate-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Your referral link</p>
                        <p class="mt-1 break-all text-sm font-medium text-slate-900">{{ referralUrl }}</p>
                    </div>
                    <button class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700" @click="copyLink">Copy link</button>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm" v-for="item in [
                    { label: 'Total clicks', value: stats.totalClicks },
                    { label: 'Unique clicks', value: stats.uniqueClicks },
                    { label: 'Registrations', value: stats.registrations },
                    { label: 'Conversions', value: stats.conversions },
                    { label: 'Pending earnings', value: `$${stats.pendingEarnings ?? 0}` },
                    { label: 'Approved earnings', value: `$${stats.approvedEarnings ?? 0}` },
                    { label: 'Paid earnings', value: `$${stats.paidEarnings ?? 0}` },
                ]" :key="item.label">
                    <p class="text-sm text-slate-500">{{ item.label }}</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">{{ item.value }}</p>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h2 class="text-base font-semibold text-slate-900">30-day click trend</h2>
                    <div class="mt-4 space-y-2">
                        <div v-for="point in trend" :key="point.date" class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm">
                            <span>{{ point.date }}</span>
                            <span class="font-semibold text-slate-900">{{ point.count }}</span>
                        </div>
                        <p v-if="!trend.length" class="text-sm text-slate-500">No click activity yet.</p>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h2 class="text-base font-semibold text-slate-900">Recent payouts</h2>
                    <div class="mt-4 space-y-2">
                        <div v-for="payout in recentPayouts" :key="payout.id" class="rounded-lg bg-slate-50 px-3 py-2 text-sm">
                            <div class="flex items-center justify-between">
                                <span>{{ payout.payment_method }}</span>
                                <span class="font-semibold text-slate-900">{{ payout.currency }} {{ payout.amount }}</span>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">{{ payout.payout_date }}</p>
                        </div>
                        <p v-if="!recentPayouts.length" class="text-sm text-slate-500">No payouts recorded yet.</p>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="text-base font-semibold text-slate-900">Recent referred users</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-left text-slate-500">
                                <th class="pb-3">Name</th>
                                <th class="pb-3">Email</th>
                                <th class="pb-3">Registered</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="referral in recentReferrals" :key="referral.id" class="border-b border-slate-100">
                                <td class="py-3 font-medium text-slate-900">{{ referral.referred_user?.full_name || `${referral.referred_user?.first_name ?? ''} ${referral.referred_user?.last_name ?? ''}` }}</td>
                                <td class="py-3 text-slate-600">{{ referral.referred_user?.email }}</td>
                                <td class="py-3 text-slate-600">{{ referral.registered_at }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="!recentReferrals.length" class="py-6 text-sm text-slate-500">No referred registrations yet.</p>
                </div>
            </div>
        </div>
    </AffiliateLayout>
</template>
