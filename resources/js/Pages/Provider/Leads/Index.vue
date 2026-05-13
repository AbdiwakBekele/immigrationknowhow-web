<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import ProviderLayout from '@/Layouts/ProviderLayout.vue';
import { 
    FunnelIcon,
    MagnifyingGlassIcon,
    ChatBubbleLeftRightIcon,
    ClockIcon,
    ExclamationCircleIcon,
    CheckCircleIcon,
    XCircleIcon,
    ArrowPathIcon,
    EyeIcon,
    ChevronDownIcon,
    ArrowTrendingUpIcon,
} from '@heroicons/vue/24/outline';
import { ref, computed, watch } from 'vue';
import { Menu, MenuButton, MenuItems, MenuItem } from '@headlessui/vue';

const props = defineProps({
    leads: { type: Object, required: true },
    stats: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});

const searchQuery = ref(props.filters.search || '');
const statusFilter = ref(props.filters.status || '');
const urgencyFilter = ref(props.filters.urgency || '');
const serviceTypeFilter = ref(props.filters.service_type || '');
const openLeadsOnly = ref(Boolean(props.filters.open));

const leadsQuery = () => {
    const params = {
        search: searchQuery.value || undefined,
        urgency: urgencyFilter.value || undefined,
        service_type: serviceTypeFilter.value || undefined,
    };
    if (openLeadsOnly.value && !statusFilter.value) {
        params.open = 1;
    } else if (statusFilter.value) {
        params.status = statusFilter.value;
    }
    return params;
};

const statusOptions = [
    { value: '', label: 'All Statuses' },
    { value: 'new', label: 'New' },
    { value: 'contacted', label: 'Contacted' },
    { value: 'in_progress', label: 'In Progress' },
    { value: 'converted', label: 'Converted' },
    { value: 'closed', label: 'Closed' },
    { value: 'declined', label: 'Declined' },
];

const urgencyOptions = [
    { value: '', label: 'All Urgencies' },
    { value: 'low', label: 'Low' },
    { value: 'normal', label: 'Normal' },
    { value: 'high', label: 'High' },
    { value: 'urgent', label: 'Urgent' },
];

const applyFilters = () => {
    router.get(route('provider.leads.index'), leadsQuery(), {
        preserveState: true,
        preserveScroll: true,
    });
};

// Debounce search
let searchTimeout;
watch(searchQuery, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 300);
});

watch(statusFilter, (v) => {
    if (v) {
        openLeadsOnly.value = false;
    }
});

watch([statusFilter, urgencyFilter, serviceTypeFilter], applyFilters);

const updateLeadStatus = (leadId, newStatus) => {
    router.patch(`/provider/leads/${leadId}/status`, {
        status: newStatus,
    }, {
        preserveScroll: true,
    });
};

const getStatusColor = (status) => {
    const colors = {
        new: 'bg-yellow-100 text-yellow-700 border-yellow-200',
        contacted: 'bg-blue-100 text-blue-700 border-blue-200',
        in_progress: 'bg-purple-100 text-purple-700 border-purple-200',
        converted: 'bg-green-100 text-green-700 border-green-200',
        closed: 'bg-gray-100 text-gray-600 border-gray-200',
        declined: 'bg-red-100 text-red-700 border-red-200',
    };
    return colors[status] || 'bg-gray-100 text-gray-600 border-gray-200';
};

const getUrgencyColor = (urgency) => {
    const colors = {
        low: 'text-gray-500',
        normal: 'text-blue-500',
        high: 'text-orange-500',
        urgent: 'text-red-600',
    };
    return colors[urgency] || 'text-gray-500';
};

const formatDate = (date) => {
    if (!date) return '';
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
};

const resolveAvatar = (person) => {
    const candidate = (person?.avatar_url || person?.avatar || '').trim();
    if (!candidate) return '';
    if (candidate.startsWith('http://') || candidate.startsWith('https://') || candidate.startsWith('/')) {
        return candidate;
    }
    return `/storage/${candidate}`;
};

const firstInitial = (...values) => {
    for (const value of values) {
        const text = (value || '').trim();
        if (text) return text.charAt(0).toUpperCase();
    }
    return 'U';
};
</script>

<template>
    <Head title="Manage Leads" />

    <ProviderLayout>
        <div class="admin-page-container">
            <!-- Header -->
            <section class="admin-hero-card mb-6">
                <div class="flex flex-col gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Lead management</p>
                    <h1 class="mt-2 admin-title">Leads</h1>
                    <p class="admin-subtitle">Manage your incoming service inquiries and update status quickly.</p>
                </div>
                </div>
            </section>

            <!-- Stats Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
                <Link
                    :href="route('provider.leads.index')"
                    class="block rounded-xl border border-gray-100 bg-white p-4 transition hover:border-primary-200 hover:shadow-sm"
                >
                    <div class="text-2xl font-bold text-gray-900">{{ stats.total || 0 }}</div>
                    <div class="text-sm text-gray-500">Total Leads</div>
                </Link>
                <Link
                    :href="route('provider.leads.index', { status: 'new' })"
                    class="block rounded-xl border border-gray-100 bg-white p-4 transition hover:border-primary-200 hover:shadow-sm"
                >
                    <div class="text-2xl font-bold text-yellow-600">{{ stats.new || 0 }}</div>
                    <div class="text-sm text-gray-500">New</div>
                </Link>
                <Link
                    :href="route('provider.leads.index', { status: 'in_progress' })"
                    class="block rounded-xl border border-gray-100 bg-white p-4 transition hover:border-primary-200 hover:shadow-sm"
                >
                    <div class="text-2xl font-bold text-purple-600">{{ stats.in_progress || 0 }}</div>
                    <div class="text-sm text-gray-500">In Progress</div>
                </Link>
                <Link
                    :href="route('provider.leads.index', { status: 'converted' })"
                    class="block rounded-xl border border-gray-100 bg-white p-4 transition hover:border-primary-200 hover:shadow-sm"
                >
                    <div class="text-2xl font-bold text-green-600">{{ stats.converted || 0 }}</div>
                    <div class="text-sm text-gray-500">Converted</div>
                </Link>
                <Link
                    :href="route('provider.analytics.index')"
                    class="block rounded-xl border border-gray-100 bg-white p-4 transition hover:border-primary-200 hover:shadow-sm"
                >
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-2xl font-bold text-primary-600">{{ stats.conversion_rate || '0%' }}</span>
                        <ArrowTrendingUpIcon class="h-5 w-5 shrink-0 text-gray-400" aria-hidden="true" />
                    </div>
                    <div class="text-sm text-gray-500">Conversion Rate</div>
                </Link>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-xl border border-gray-100 p-4 mb-6">
                <div class="flex flex-col sm:flex-row gap-4">
                    <!-- Search -->
                    <div class="flex-1">
                        <div class="relative">
                            <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400" />
                            <input 
                                v-model="searchQuery"
                                type="text"
                                placeholder="Search by name, email, or message..."
                                class="input w-full pl-10"
                            />
                        </div>
                    </div>
                    
                    <!-- Status Filter -->
                    <select v-model="statusFilter" class="input w-full sm:w-40">
                        <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
                            {{ opt.label }}
                        </option>
                    </select>

                    <!-- Urgency Filter -->
                    <select v-model="urgencyFilter" class="input w-full sm:w-40">
                        <option v-for="opt in urgencyOptions" :key="opt.value" :value="opt.value">
                            {{ opt.label }}
                        </option>
                    </select>
                </div>
            </div>

            <!-- Leads Table -->
            <div class="bg-white rounded-xl border border-gray-100 overflow-visible">
                <div v-if="leads.data?.length" class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-100">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Service</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Urgency</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr 
                                v-for="lead in leads.data" 
                                :key="lead.id"
                                class="hover:bg-gray-50 transition-colors"
                            >
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <img
                                            v-if="resolveAvatar(lead.user)"
                                            :src="resolveAvatar(lead.user)"
                                            class="h-10 w-10 rounded-full object-cover"
                                            alt="Lead user avatar"
                                        />
                                        <div
                                            v-else
                                            class="flex h-10 w-10 items-center justify-center rounded-full bg-primary-600 text-xs font-bold text-white"
                                            aria-hidden="true"
                                        >
                                            {{ firstInitial(lead.user?.first_name, lead.user?.last_name, lead.user?.full_name) }}
                                        </div>
                                        <div>
                                            <div class="font-medium text-gray-900">{{ lead.user?.full_name || 'Anonymous' }}</div>
                                            <div class="text-sm text-gray-500">{{ lead.user?.email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="text-gray-900">{{ lead.service_type_label }}</span>
                                </td>
                                <td class="px-6 py-4">
                                    <Menu as="div" class="relative inline-block">
                                        <MenuButton 
                                            class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-medium border"
                                            :class="getStatusColor(lead.status)"
                                        >
                                            {{ lead.status.replace('_', ' ') }}
                                            <ChevronDownIcon class="h-4 w-4" />
                                        </MenuButton>
                                        <MenuItems class="absolute left-0 mt-2 w-40 bg-white rounded-xl shadow-lg border border-gray-100 py-1 z-50">
                                            <MenuItem 
                                                v-for="status in statusOptions.slice(1)" 
                                                :key="status.value"
                                                v-slot="{ active }"
                                            >
                                                <button 
                                            @click="updateLeadStatus(lead.uuid, status.value)"
                                                    class="w-full px-4 py-2 text-sm text-left"
                                                    :class="[active ? 'bg-gray-50' : '', lead.status === status.value ? 'font-medium text-primary-600' : 'text-gray-700']"
                                                >
                                                    {{ status.label }}
                                                </button>
                                            </MenuItem>
                                        </MenuItems>
                                    </Menu>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="flex items-center gap-1 text-sm" :class="getUrgencyColor(lead.urgency)">
                                        <ExclamationCircleIcon v-if="['high', 'urgent'].includes(lead.urgency)" class="h-4 w-4" />
                                        {{ lead.urgency }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    {{ formatDate(lead.created_at) }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <Link 
                                            :href="`/provider/leads/${lead.uuid}`"
                                            class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100"
                                            title="View Details"
                                        >
                                            <EyeIcon class="h-5 w-5" />
                                        </Link>
                                        <Link 
                                            v-if="lead.conversation"
                                            :href="route('provider.messages.show', lead.conversation.uuid)"
                                            class="p-2 text-gray-400 hover:text-primary-600 rounded-lg hover:bg-gray-100"
                                            title="View Messages"
                                        >
                                            <ChatBubbleLeftRightIcon class="h-5 w-5" />
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Empty State -->
                <div v-else class="p-12 text-center">
                    <FunnelIcon class="h-12 w-12 text-gray-300 mx-auto mb-4" />
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No leads yet</h3>
                    <p class="text-gray-500">When potential clients contact you, their inquiries will appear here.</p>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="leads.links?.length > 3" class="mt-6 flex justify-center">
                <nav class="flex gap-1">
                    <Link 
                        v-for="link in leads.links" 
                        :key="link.label"
                        :href="link.url || '#'"
                        class="px-3 py-2 text-sm rounded-lg"
                        :class="link.active ? 'bg-primary-600 text-white' : 'text-gray-600 hover:bg-gray-100'"
                        v-html="link.label"
                        :preserve-scroll="true"
                    />
                </nav>
            </div>
        </div>
    </ProviderLayout>
</template>
