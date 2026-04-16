<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { BanknotesIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    pending: Object,
});

const approve = (id) => {
    if (!confirm('Grant library access for this user? They will be able to download immediately.')) {
        return;
    }
    router.post(route('admin.library-manual-payments.approve', id), {}, { preserveScroll: true });
};

const formatDate = (value) => {
    if (!value) return '—';
    return new Date(value).toLocaleString();
};
</script>

<template>
    <Head title="Pending library payments" />

    <AdminLayout>
        <div class="admin-page-container">
            <section class="admin-hero-card">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                    Library operations
                </p>
                <h1 class="mt-2 admin-title">Pending library payments</h1>
                <p class="admin-subtitle">
                        Users paid manually (no Stripe). Approve after you confirm the payment in your bank or PayPal.
                </p>
            </section>

            <div
                v-if="!pending?.data?.length"
                class="flex flex-col items-center justify-center rounded-[1.5rem] border border-dashed border-slate-200 bg-white py-16 text-center"
            >
                <BanknotesIcon class="h-10 w-10 text-slate-300" />
                <p class="mt-3 text-sm font-medium text-slate-700">No pending requests</p>
                <p class="mt-1 max-w-sm text-sm text-slate-500">
                    When a user submits payment details from the library pay page, their request appears here.
                </p>
            </div>

            <div v-else class="admin-table-wrap">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                        <thead class="admin-table-head">
                            <tr>
                                <th class="admin-table-th">Requested</th>
                                <th class="admin-table-th">User</th>
                                <th class="admin-table-th">Item</th>
                                <th class="admin-table-th">Reference</th>
                                <th class="admin-table-th">Note</th>
                                <th class="admin-table-th" />
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="row in pending.data" :key="row.id" class="hover:bg-slate-50/80">
                                <td class="whitespace-nowrap px-4 py-3 text-slate-600">
                                    {{ formatDate(row.manual_payment_requested_at) }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-slate-900">{{ row.user?.name ?? '—' }}</div>
                                    <div class="text-xs text-slate-500">{{ row.user?.email ?? '' }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-slate-900">{{ row.library_item?.title ?? '—' }}</div>
                                    <div class="text-xs text-slate-500">
                                        {{ row.library_item?.currency }} {{ row.library_item?.price }}
                                    </div>
                                </td>
                                <td class="max-w-[12rem] break-words px-4 py-3 text-slate-700">
                                    {{ row.manual_payment_reference || '—' }}
                                </td>
                                <td class="max-w-[14rem] break-words px-4 py-3 text-slate-600">
                                    {{ row.manual_payment_note || '—' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    <button
                                        type="button"
                                        class="rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-primary-700"
                                        @click="approve(row.id)"
                                    >
                                        Approve
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="pending.links?.length > 3" class="border-t border-slate-100 px-3 py-2.5">
                    <nav class="flex justify-center gap-1">
                        <Link
                            v-for="link in pending.links"
                            :key="link.label"
                            :href="link.url"
                            class="rounded-lg px-2.5 py-1.5 text-xs"
                            :class="link.active ? 'bg-primary-600 text-white' : 'text-slate-600 hover:bg-slate-100'"
                            v-html="link.label"
                        />
                    </nav>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
