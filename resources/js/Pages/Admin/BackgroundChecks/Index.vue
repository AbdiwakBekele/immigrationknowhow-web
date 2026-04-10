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
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Background Checks</h1>
                    <p class="text-gray-500 mt-1">Monitor and review Checkr background check results</p>
                </div>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
                    <p class="text-2xl font-bold text-gray-900">{{ stats.total }}</p>
                    <p class="text-sm text-gray-500">Total</p>
                </div>
                <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
                    <p class="text-2xl font-bold text-blue-600">{{ stats.pending }}</p>
                    <p class="text-sm text-gray-500">Pending</p>
                </div>
                <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
                    <p class="text-2xl font-bold text-amber-600">{{ stats.in_progress }}</p>
                    <p class="text-sm text-gray-500">In Progress</p>
                </div>
                <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
                    <p class="text-2xl font-bold text-emerald-600">{{ stats.cleared }}</p>
                    <p class="text-sm text-gray-500">Cleared</p>
                </div>
                <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
                    <p class="text-2xl font-bold text-orange-600">{{ stats.needs_review }}</p>
                    <p class="text-sm text-gray-500">Needs Review</p>
                </div>
                <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
                    <p class="text-2xl font-bold text-gray-500">{{ stats.expired }}</p>
                    <p class="text-sm text-gray-500">Expired</p>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                <div class="flex flex-col sm:flex-row gap-4">
                    <div class="flex-1 relative">
                        <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search by name, email, or business..."
                            class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                        />
                    </div>
                    <div class="sm:w-48">
                        <select
                            v-model="status"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                        >
                            <option value="">All Statuses</option>
                            <option v-for="s in statuses" :key="s.value" :value="s.value">
                                {{ s.label }}
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Provider
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Candidate
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Initiated
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Expires
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="check in backgroundChecks.data" :key="check.id" class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div v-if="check.service_provider">
                                        <div class="font-medium text-gray-900">
                                            {{ check.service_provider.business_name }}
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            {{ check.service_provider.user?.first_name }} {{ check.service_provider.user?.last_name }}
                                        </div>
                                    </div>
                                    <span v-else class="text-gray-400">—</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="font-medium text-gray-900">
                                        {{ check.first_name }} {{ check.last_name }}
                                    </div>
                                    <div class="text-sm text-gray-500">{{ check.email }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="['inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium', getStatusClasses(check.status)]">
                                        <component :is="getStatusIcon(check.status)" class="w-3.5 h-3.5" />
                                        {{ check.status_display?.label || check.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ new Date(check.created_at).toLocaleDateString() }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <span v-if="check.expires_at">
                                        {{ new Date(check.expires_at).toLocaleDateString() }}
                                    </span>
                                    <span v-else class="text-gray-400">—</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <Link
                                        :href="`/admin/background-checks/${check.uuid}`"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-sky-600 hover:text-sky-700 hover:bg-sky-50 rounded-lg transition-colors"
                                    >
                                        <EyeIcon class="w-4 h-4" />
                                        View
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="!backgroundChecks.data?.length">
                                <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                    <ShieldCheckIcon class="w-12 h-12 mx-auto mb-3 text-gray-300" />
                                    <p>No background checks found</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="backgroundChecks.links?.length > 3" class="px-6 py-4 border-t border-gray-200 flex items-center justify-between">
                    <p class="text-sm text-gray-500">
                        Showing {{ backgroundChecks.from }} to {{ backgroundChecks.to }} of {{ backgroundChecks.total }} results
                    </p>
                    <div class="flex gap-2">
                        <Link
                            v-for="link in backgroundChecks.links"
                            :key="link.label"
                            :href="link.url"
                            :class="[
                                'px-3 py-1 rounded text-sm',
                                link.active ? 'bg-sky-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200',
                                !link.url && 'opacity-50 cursor-not-allowed'
                            ]"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
