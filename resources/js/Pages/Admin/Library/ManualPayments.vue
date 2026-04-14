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
        <div class="space-y-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Pending library payments</h1>
                    <p class="mt-1 text-sm text-gray-600">
                        Users paid manually (no Stripe). Approve after you confirm the payment in your bank or PayPal.
                    </p>
                </div>
            </div>

            <div
                v-if="!pending?.data?.length"
                class="flex flex-col items-center justify-center rounded-xl border border-dashed border-gray-200 bg-white py-16 text-center"
            >
                <BanknotesIcon class="h-10 w-10 text-gray-300" />
                <p class="mt-3 text-sm font-medium text-gray-700">No pending requests</p>
                <p class="mt-1 max-w-sm text-sm text-gray-500">
                    When a user submits payment details from the library pay page, their request appears here.
                </p>
            </div>

            <div v-else class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-left text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 font-semibold text-gray-700">Requested</th>
                                <th class="px-4 py-3 font-semibold text-gray-700">User</th>
                                <th class="px-4 py-3 font-semibold text-gray-700">Item</th>
                                <th class="px-4 py-3 font-semibold text-gray-700">Reference</th>
                                <th class="px-4 py-3 font-semibold text-gray-700">Note</th>
                                <th class="px-4 py-3 font-semibold text-gray-700" />
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="row in pending.data" :key="row.id" class="hover:bg-gray-50/80">
                                <td class="whitespace-nowrap px-4 py-3 text-gray-600">
                                    {{ formatDate(row.manual_payment_requested_at) }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900">{{ row.user?.name ?? '—' }}</div>
                                    <div class="text-xs text-gray-500">{{ row.user?.email ?? '' }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900">{{ row.library_item?.title ?? '—' }}</div>
                                    <div class="text-xs text-gray-500">
                                        {{ row.library_item?.currency }} {{ row.library_item?.price }}
                                    </div>
                                </td>
                                <td class="max-w-[12rem] break-words px-4 py-3 text-gray-700">
                                    {{ row.manual_payment_reference || '—' }}
                                </td>
                                <td class="max-w-[14rem] break-words px-4 py-3 text-gray-600">
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
                <div v-if="pending.links?.length > 3" class="border-t border-gray-100 px-3 py-2.5">
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
