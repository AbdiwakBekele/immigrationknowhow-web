<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    subscribers: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    serviceTypes: { type: Array, default: () => [] },
    statusOptions: { type: Array, default: () => [] },
});

const search = ref(props.filters.search || '');
const statusFilter = ref(props.filters.status || '');
const serviceTypeFilter = ref(props.filters.service_type || '');

let searchTimeout;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => applyFilters(), 300);
});
watch([statusFilter, serviceTypeFilter], () => applyFilters());

const applyFilters = () => {
    router.get(
        route('admin.subscribers.index'),
        {
            search: search.value || undefined,
            status: statusFilter.value || undefined,
            service_type: serviceTypeFilter.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
};

const rows = computed(() => props.subscribers?.data || []);

const titleCase = (value) =>
    (value || '')
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());

const formatDate = (date) => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
};

const statusClasses = (status) => {
    const classes = {
        new: 'bg-blue-100 text-blue-700',
        contacted: 'bg-indigo-100 text-indigo-700',
        in_progress: 'bg-amber-100 text-amber-700',
        converted: 'bg-emerald-100 text-emerald-700',
        closed: 'bg-slate-100 text-slate-700',
        declined: 'bg-rose-100 text-rose-700',
    };

    return classes[status] || 'bg-slate-100 text-slate-700';
};
</script>

<template>
    <Head title="Subscribers" />

    <AdminLayout>
        <div class="mx-auto max-w-7xl space-y-4">
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                <h1 class="text-lg font-display font-bold text-slate-900">Subscribers</h1>
                <p class="mt-0.5 text-xs text-slate-500">
                    Users with contract details, subscribed service type, and assigned provider.
                </p>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                <div class="flex flex-col gap-3 sm:flex-row">
                    <div class="relative flex-1">
                        <MagnifyingGlassIcon class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input
                            v-model="search"
                            type="text"
                            class="input w-full pl-10"
                            placeholder="Search subscriber or provider..."
                        />
                    </div>
                    <select v-model="serviceTypeFilter" class="input w-full sm:w-52">
                        <option value="">All Service Types</option>
                        <option v-for="type in serviceTypes" :key="type.value" :value="type.value">
                            {{ type.label }}
                        </option>
                    </select>
                    <select v-model="statusFilter" class="input w-full sm:w-44">
                        <option value="">All Contract Status</option>
                        <option v-for="status in statusOptions" :key="status.value" :value="status.value">
                            {{ status.label }}
                        </option>
                    </select>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="border-b border-slate-200 bg-slate-50">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500">Subscriber</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500">Service Type</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500">Service Provider</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500">Contract Status</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500">Contract Sent</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500">Accepted</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500">Started</th>
                                <th class="px-3 py-2 text-right text-xs font-semibold text-slate-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="lead in rows" :key="lead.uuid" class="hover:bg-slate-50">
                                <td class="px-3 py-2.5">
                                    <div class="text-sm font-medium text-slate-900">
                                        {{ lead.user?.first_name }} {{ lead.user?.last_name }}
                                    </div>
                                    <div class="text-xs text-slate-500">{{ lead.user?.email }}</div>
                                </td>
                                <td class="px-3 py-2.5 text-xs text-slate-700">
                                    {{ lead.service_type_label || titleCase(lead.service_type) }}
                                </td>
                                <td class="px-3 py-2.5">
                                    <div class="text-sm text-slate-900">{{ lead.service_provider?.business_name || '—' }}</div>
                                    <div class="text-xs text-slate-500">
                                        {{ lead.service_provider?.user?.first_name }} {{ lead.service_provider?.user?.last_name }}
                                    </div>
                                </td>
                                <td class="px-3 py-2.5">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusClasses(lead.status)">
                                        {{ titleCase(lead.status) }}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5 text-xs text-slate-500">{{ formatDate(lead.contract_sent_at) }}</td>
                                <td class="px-3 py-2.5 text-xs text-slate-500">{{ formatDate(lead.contract_accepted_at) }}</td>
                                <td class="px-3 py-2.5 text-xs text-slate-500">{{ formatDate(lead.created_at) }}</td>
                                <td class="px-3 py-2.5 text-right">
                                    <Link
                                        v-if="lead.service_provider?.slug"
                                        :href="`/admin/providers/${lead.service_provider.slug}`"
                                        class="inline-flex rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                    >
                                        View Provider
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="!rows.length">
                                <td colspan="8" class="px-3 py-8 text-center text-sm text-slate-500">
                                    No subscribers found for the selected filters.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="subscribers.links?.length > 3" class="border-t border-slate-100 px-3 py-2.5">
                    <nav class="flex justify-center gap-1">
                        <Link
                            v-for="link in subscribers.links"
                            :key="`${link.label}-${link.url}`"
                            :href="link.url"
                            class="rounded-lg px-2.5 py-1.5 text-xs"
                            :class="link.active ? 'bg-sky-600 text-white' : 'text-slate-600 hover:bg-slate-100'"
                            v-html="link.label"
                        />
                    </nav>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
