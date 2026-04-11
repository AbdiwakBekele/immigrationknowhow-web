<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';

const props = defineProps({
    affiliates: Object,
    filters: Object,
    stats: Object,
    topAffiliates: Array,
    pendingInvites: Array,
    statuses: Array,
});

const searchValue = computed({
    get: () => props.filters?.search || '',
    set: (value) => {
        router.get(route('admin.affiliates.index'), { ...props.filters, search: value }, { preserveState: true, replace: true });
    },
});

const statusValue = computed({
    get: () => props.filters?.status || '',
    set: (value) => {
        router.get(route('admin.affiliates.index'), { ...props.filters, status: value }, { preserveState: true, replace: true });
    },
});

const resendInvite = (inviteId) => {
    router.post(route('admin.affiliates.invite.resend', inviteId), {}, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Affiliates" />

    <AdminLayout>
        <div class="space-y-6">
            <div class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Affiliate program</h1>
                    <p class="mt-1 text-sm text-slate-500">Manage invites, referrals, commissions, payouts, and partner performance.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link :href="route('admin.affiliates.invite.create')" class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-700">Invite affiliate</Link>
                    <Link :href="route('admin.affiliates.commissions.index')" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Commission rules</Link>
                    <Link :href="route('admin.affiliates.payouts.index')" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Payouts</Link>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm" v-for="item in [
                    { label: 'Total affiliates', value: stats.totalAffiliates },
                    { label: 'Active affiliates', value: stats.activeAffiliates },
                    { label: 'Invited / pending', value: stats.invitedAffiliates },
                    { label: 'Referral clicks', value: stats.totalClicks },
                    { label: 'Pending commissions', value: `$${stats.pendingCommissions}` },
                    { label: 'Paid commissions', value: `$${stats.paidCommissions}` },
                    { label: 'Total payouts', value: `$${stats.payoutTotals}` },
                ]" :key="item.label">
                    <p class="text-sm text-slate-500">{{ item.label }}</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">{{ item.value }}</p>
                </div>
            </div>

            <div class="grid gap-4 xl:grid-cols-[2fr,1fr]">
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex flex-col gap-3 md:flex-row">
                        <Input v-model="searchValue" label="Search" placeholder="Affiliate code, name, email" />
                        <Select v-model="statusValue" :options="[{ value: '', label: 'All statuses' }, ...statuses]" label="Status" />
                    </div>

                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-slate-200 text-left text-slate-500">
                                    <th class="pb-3">Affiliate</th>
                                    <th class="pb-3">Code</th>
                                    <th class="pb-3">Status</th>
                                    <th class="pb-3">Referrals</th>
                                    <th class="pb-3">Earnings</th>
                                    <th class="pb-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="affiliate in affiliates.data" :key="affiliate.id" class="border-b border-slate-100">
                                    <td class="py-3">
                                        <p class="font-medium text-slate-900">{{ affiliate.user?.first_name }} {{ affiliate.user?.last_name }}</p>
                                        <p class="text-xs text-slate-500">{{ affiliate.user?.email }}</p>
                                    </td>
                                    <td class="py-3 text-slate-600">{{ affiliate.code }}</td>
                                    <td class="py-3 capitalize text-slate-600">{{ affiliate.status }}</td>
                                    <td class="py-3 text-slate-600">{{ affiliate.referrals_count }}</td>
                                    <td class="py-3 text-slate-600">{{ affiliate.earnings_count }}</td>
                                    <td class="py-3 text-right">
                                        <div class="flex justify-end gap-2">
                                            <Link :href="route('admin.affiliates.show', affiliate.id)" class="text-sm font-medium text-sky-600 hover:text-sky-700">View</Link>
                                            <Link :href="route('admin.affiliates.edit', affiliate.id)" class="text-sm font-medium text-slate-700 hover:text-slate-900">Edit</Link>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <p v-if="!affiliates.data.length" class="py-6 text-sm text-slate-500">No affiliates found.</p>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h2 class="text-base font-semibold text-slate-900">Top affiliates</h2>
                    <div class="mt-4 space-y-3">
                        <div v-for="affiliate in topAffiliates" :key="affiliate.id" class="rounded-lg bg-slate-50 p-3">
                            <p class="font-medium text-slate-900">{{ affiliate.user?.first_name }} {{ affiliate.user?.last_name }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ affiliate.referrals_count }} referrals · ${{ affiliate.earnings_sum_commission_amount || 0 }}</p>
                        </div>
                        <p v-if="!topAffiliates.length" class="text-sm text-slate-500">No affiliate performance data yet.</p>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">Pending invites</h2>
                        <p class="mt-1 text-sm text-slate-500">Resend invitations to affiliates who have not completed signup yet.</p>
                    </div>
                    <Link :href="route('admin.affiliates.invite.create')" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">New invite</Link>
                </div>

                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-left text-slate-500">
                                <th class="pb-3">Invitee</th>
                                <th class="pb-3">Sent</th>
                                <th class="pb-3">Expires</th>
                                <th class="pb-3">Invited by</th>
                                <th class="pb-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="invite in pendingInvites" :key="invite.id" class="border-b border-slate-100">
                                <td class="py-3">
                                    <p class="font-medium text-slate-900">{{ invite.name }}</p>
                                    <p class="text-xs text-slate-500">{{ invite.email }}</p>
                                </td>
                                <td class="py-3 text-slate-600">{{ invite.sent_at || '—' }}</td>
                                <td class="py-3 text-slate-600">
                                    <span>{{ invite.expires_at || '—' }}</span>
                                    <span v-if="invite.is_expired" class="ml-2 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">Expired</span>
                                </td>
                                <td class="py-3 text-slate-600">{{ invite.inviter_name || '—' }}</td>
                                <td class="py-3 text-right">
                                    <button type="button" class="text-sm font-medium text-sky-600 hover:text-sky-700" @click="resendInvite(invite.id)">
                                        Resend invite
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="!pendingInvites.length" class="py-6 text-sm text-slate-500">No pending invitations right now.</p>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
