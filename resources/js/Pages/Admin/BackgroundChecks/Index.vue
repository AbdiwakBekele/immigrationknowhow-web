<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { 
    ShieldCheckIcon,
    MagnifyingGlassIcon,
    ClockIcon,
    CheckBadgeIcon,
    ExclamationTriangleIcon,
    XCircleIcon,
    EnvelopeIcon,
    EyeIcon,
    FunnelIcon,
} from '@heroicons/vue/24/outline';
import { ref, watch } from 'vue';

const debounce = (fn, wait = 300) => {
    let timeoutId;
    return (...args) => {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => fn(...args), wait);
    };
};

const props = defineProps({
    backgroundChecks: Object,
    filters: Object,
    stats: Object,
    statuses: Array,
});

const search = ref(props.filters.search || '');
const status = ref(props.filters.status || '');

const applyFilters = debounce(() => {
    router.get('/admin/background-checks', {
        search: search.value || undefined,
        status: status.value || undefined,
    }, {
        preserveState: true,
        replace: true,
    });
}, 300);

watch([search, status], applyFilters);

const getStatusIcon = (status) => {
    const icons = {
        pending: ClockIcon,
        invited: EnvelopeIcon,
        completed: MagnifyingGlassIcon,
        clear: CheckBadgeIcon,
        consider: ExclamationTriangleIcon,
        suspended: XCircleIcon,
        dispute: ExclamationTriangleIcon,
        expired: ClockIcon,
    };
    return icons[status] || ClockIcon;
};

const getStatusClasses = (status) => {
    const classes = {
        pending: 'bg-gray-100 text-gray-700',
        invited: 'bg-blue-100 text-blue-700',
        completed: 'bg-amber-100 text-amber-700',
        clear: 'bg-emerald-100 text-emerald-700',
        consider: 'bg-orange-100 text-orange-700',
        suspended: 'bg-red-100 text-red-700',
        dispute: 'bg-purple-100 text-purple-700',
        expired: 'bg-gray-100 text-gray-600',
    };
    return classes[status] || 'bg-gray-100 text-gray-700';
};
</script>

<template>
    <Head title="Background Checks" />

    <AdminLayout>
        <div class="admin-page-container">
            <section class="admin-hero-card">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                    Compliance and screening
                </p>
                <h1 class="mt-2 admin-title">Background Checks</h1>
                <p class="admin-subtitle">Monitor and review Checkr background check results for providers.</p>
            </section>

            <section class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-6">
                <article class="rounded-[1.1rem] border border-slate-200 bg-white p-3 shadow-sm">
                    <p class="text-xl font-semibold tracking-tight text-slate-900">{{ stats.total }}</p>
                    <p class="text-xs text-slate-500">Total</p>
                </article>
                <article class="rounded-[1.1rem] border border-slate-200 bg-white p-3 shadow-sm">
                    <p class="text-xl font-semibold tracking-tight text-blue-600">{{ stats.pending }}</p>
                    <p class="text-xs text-slate-500">Pending</p>
                </article>
                <article class="rounded-[1.1rem] border border-slate-200 bg-white p-3 shadow-sm">
                    <p class="text-xl font-semibold tracking-tight text-amber-600">{{ stats.in_progress }}</p>
                    <p class="text-xs text-slate-500">In Progress</p>
                </article>
                <article class="rounded-[1.1rem] border border-slate-200 bg-white p-3 shadow-sm">
                    <p class="text-xl font-semibold tracking-tight text-emerald-600">{{ stats.cleared }}</p>
                    <p class="text-xs text-slate-500">Cleared</p>
                </article>
                <article class="rounded-[1.1rem] border border-slate-200 bg-white p-3 shadow-sm">
                    <p class="text-xl font-semibold tracking-tight text-orange-600">{{ stats.needs_review }}</p>
                    <p class="text-xs text-slate-500">Needs Review</p>
                </article>
                <article class="rounded-[1.1rem] border border-slate-200 bg-white p-3 shadow-sm">
                    <p class="text-xl font-semibold tracking-tight text-slate-500">{{ stats.expired }}</p>
                    <p class="text-xs text-slate-500">Expired</p>
                </article>
            </section>

            <section class="admin-panel">
                <div class="flex flex-col sm:flex-row gap-4">
                    <div class="flex-1 relative">
                        <MagnifyingGlassIcon class="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search by name, email, or business..."
                            class="admin-input pl-10"
                        />
                    </div>
                    <div class="sm:w-48">
                        <select
                            v-model="status"
                            class="admin-select"
                        >
                            <option value="">All Statuses</option>
                            <option v-for="s in statuses" :key="s.value" :value="s.value">
                                {{ s.label }}
                            </option>
                        </select>
                    </div>
                </div>
            </section>

            <section class="admin-table-wrap">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="admin-table-head border-b border-slate-200">
                            <tr>
                                <th class="admin-table-th">
                                    Provider
                                </th>
                                <th class="admin-table-th">
                                    Candidate
                                </th>
                                <th class="admin-table-th">
                                    Status
                                </th>
                                <th class="admin-table-th">
                                    Initiated
                                </th>
                                <th class="admin-table-th">
                                    Expires
                                </th>
                                <th class="admin-table-th text-right">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="check in backgroundChecks.data" :key="check.id" class="hover:bg-slate-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div v-if="check.service_provider">
                                        <div class="font-medium text-slate-900">
                                            {{ check.service_provider.business_name }}
                                        </div>
                                        <div class="text-sm text-slate-500">
                                            {{ check.service_provider.user?.first_name }} {{ check.service_provider.user?.last_name }}
                                        </div>
                                    </div>
                                    <span v-else class="text-slate-400">—</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="font-medium text-slate-900">
                                        {{ check.first_name }} {{ check.last_name }}
                                    </div>
                                    <div class="text-sm text-slate-500">{{ check.email }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="['inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium', getStatusClasses(check.status)]">
                                        <component :is="getStatusIcon(check.status)" class="w-3.5 h-3.5" />
                                        {{ check.status_display?.label || check.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">
                                    {{ new Date(check.created_at).toLocaleDateString() }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">
                                    <span v-if="check.expires_at">
                                        {{ new Date(check.expires_at).toLocaleDateString() }}
                                    </span>
                                    <span v-else class="text-slate-400">—</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <Link
                                        :href="`/admin/background-checks/${check.uuid}`"
                                        class="inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 hover:text-slate-900"
                                    >
                                        <EyeIcon class="w-4 h-4" />
                                        View
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="!backgroundChecks.data?.length">
                                <td colspan="6" class="px-6 py-12 text-center text-slate-500">
                                    <ShieldCheckIcon class="mx-auto mb-3 h-12 w-12 text-slate-300" />
                                    <p>No background checks found</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="backgroundChecks.links?.length > 3" class="flex items-center justify-between border-t border-slate-200 px-6 py-4">
                    <p class="text-sm text-slate-500">
                        Showing {{ backgroundChecks.from }} to {{ backgroundChecks.to }} of {{ backgroundChecks.total }} results
                    </p>
                    <div class="flex gap-2">
                        <Link
                            v-for="link in backgroundChecks.links"
                            :key="link.label"
                            :href="link.url || '#'"
                            :class="[
                                'rounded-lg px-3 py-1 text-sm',
                                link.active ? 'bg-sky-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200',
                                !link.url && 'opacity-50 cursor-not-allowed'
                            ]"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>
