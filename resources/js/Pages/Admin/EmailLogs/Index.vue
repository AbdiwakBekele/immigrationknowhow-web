<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    logs: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    email_logs_table_missing: { type: Boolean, default: false },
});

const filterState = computed(() => ({
    direction: props.filters.direction ?? '',
    status: props.filters.status ?? '',
    search: props.filters.search ?? '',
}));

const formatDate = (value) => {
    if (!value) return 'N/A';
    return new Date(value).toLocaleString();
};

const statusBadgeClass = (status) => {
    if (status === 'sent' || status === 'received') {
        return 'bg-emerald-50 text-emerald-700';
    }

    if (status === 'failed') {
        return 'bg-rose-50 text-rose-700';
    }

    return 'bg-gray-100 text-gray-600';
};

const applyFilters = (event) => {
    const formData = new FormData(event.target);
    router.get(route('admin.email-logs.index'), {
        direction: formData.get('direction') || undefined,
        status: formData.get('status') || undefined,
        search: formData.get('search') || undefined,
    }, { preserveState: true, preserveScroll: true });
};
</script>

<template>
    <Head title="Email Logs" />

    <AdminLayout>
        <div class="space-y-5">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Email Logs</h1>
                <p class="mt-1 text-gray-500">Track outgoing and incoming emails across the platform.</p>
            </div>

            <div
                v-if="email_logs_table_missing"
                class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
            >
                Email logs table is not installed yet. Run
                <code class="rounded bg-amber-100 px-1 py-0.5 font-mono text-xs">php artisan migrate</code>
                to enable this module.
            </div>

            <form v-if="!email_logs_table_missing" class="grid gap-3 rounded-xl border border-gray-100 bg-white p-4 shadow-sm md:grid-cols-4" @submit.prevent="applyFilters">
                <select
                    name="direction"
                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm"
                    :value="filterState.direction"
                >
                    <option value="">All directions</option>
                    <option value="outgoing">Outgoing</option>
                    <option value="incoming">Incoming</option>
                </select>

                <select
                    name="status"
                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm"
                    :value="filterState.status"
                >
                    <option value="">All statuses</option>
                    <option value="sent">Sent</option>
                    <option value="received">Received</option>
                    <option value="failed">Failed</option>
                </select>

                <input
                    name="search"
                    type="text"
                    placeholder="Search subject or email"
                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm"
                    :value="filterState.search"
                />

                <button
                    type="submit"
                    class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-sky-700"
                >
                    Apply filters
                </button>
            </form>

            <div v-if="!email_logs_table_missing" class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Direction</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Subject</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">From</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">To</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Time</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="row in logs.data" :key="row.id">
                            <td class="px-4 py-3 text-sm text-gray-700">{{ row.direction }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span
                                    class="rounded-full px-2 py-1 text-xs font-medium uppercase tracking-wide"
                                    :class="statusBadgeClass(row.status)"
                                >
                                    {{ row.status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900">{{ row.subject || 'No subject' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ row.from_email || 'N/A' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ row.to_email || 'N/A' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ formatDate(row.sent_at || row.received_at || row.created_at) }}</td>
                        </tr>
                        <tr v-if="!logs.data?.length">
                            <td colspan="6" class="px-4 py-10 text-center text-sm text-gray-500">No email logs found yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="!email_logs_table_missing && (logs.prev_page_url || logs.next_page_url)" class="flex justify-center gap-3">
                <Link
                    v-if="logs.prev_page_url"
                    :href="logs.prev_page_url"
                    class="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50"
                >
                    Previous
                </Link>
                <Link
                    v-if="logs.next_page_url"
                    :href="logs.next_page_url"
                    class="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50"
                >
                    Next
                </Link>
            </div>
        </div>
    </AdminLayout>
</template>
