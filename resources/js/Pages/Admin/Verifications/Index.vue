<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { 
    ShieldCheckIcon,
    ClockIcon,
    CheckCircleIcon,
    XCircleIcon,
    EyeIcon,
    FunnelIcon
} from '@heroicons/vue/24/outline';
import { ref, watch } from 'vue';

const props = defineProps({
    verifications: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    stats: { type: Object, default: () => ({}) },
    statuses: { type: Array, default: () => [] },
});

const statusFilter = ref(props.filters.status || '');

watch(statusFilter, (value) => {
    router.get('/admin/verifications', {
        status: value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
});

const getStatusInfo = (status) => {
    const info = {
        pending: { color: 'bg-yellow-100 text-yellow-700', icon: ClockIcon },
        under_review: { color: 'bg-blue-100 text-blue-700', icon: ClockIcon },
        approved: { color: 'bg-green-100 text-green-700', icon: CheckCircleIcon },
        rejected: { color: 'bg-red-100 text-red-700', icon: XCircleIcon },
        expired: { color: 'bg-gray-100 text-gray-600', icon: XCircleIcon },
    };
    return info[status] || info.pending;
};

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
};

const quickApprove = (verification) => {
    if (!confirm('Approve this verification?')) return;
    router.post(`/admin/verifications/${verification.uuid}/approve`);
};

const quickReject = (verification) => {
    const reason = prompt('Enter rejection reason:');
    if (!reason) return;
    router.post(`/admin/verifications/${verification.uuid}/reject`, { reason });
};
</script>

<template>
    <Head title="Identity Verifications" />

    <AdminLayout>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Header -->
            <div class="mb-6">
                <h1 class="text-2xl font-display font-bold text-gray-900">Identity Verifications</h1>
                <p class="text-gray-500 mt-1">Review and approve provider identity documents</p>
            </div>

            <!-- Stats -->
            <div class="grid sm:grid-cols-4 gap-4 mb-6">
                <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4">
                    <div class="flex items-center gap-3">
                        <ClockIcon class="h-8 w-8 text-yellow-600" />
                        <div>
                            <div class="text-2xl font-bold text-yellow-700">{{ stats.pending || 0 }}</div>
                            <div class="text-sm text-yellow-600">Pending</div>
                        </div>
                    </div>
                </div>
                <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                    <div class="flex items-center gap-3">
                        <EyeIcon class="h-8 w-8 text-blue-600" />
                        <div>
                            <div class="text-2xl font-bold text-blue-700">{{ stats.under_review || 0 }}</div>
                            <div class="text-sm text-blue-600">Under Review</div>
                        </div>
                    </div>
                </div>
                <div class="bg-green-50 border border-green-200 rounded-xl p-4">
                    <div class="flex items-center gap-3">
                        <CheckCircleIcon class="h-8 w-8 text-green-600" />
                        <div>
                            <div class="text-2xl font-bold text-green-700">{{ stats.approved_today || 0 }}</div>
                            <div class="text-sm text-green-600">Approved Today</div>
                        </div>
                    </div>
                </div>
                <div class="bg-red-50 border border-red-200 rounded-xl p-4">
                    <div class="flex items-center gap-3">
                        <XCircleIcon class="h-8 w-8 text-red-600" />
                        <div>
                            <div class="text-2xl font-bold text-red-700">{{ stats.rejected_today || 0 }}</div>
                            <div class="text-sm text-red-600">Rejected Today</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter -->
            <div class="bg-white rounded-xl border border-gray-100 p-4 mb-6">
                <div class="flex items-center gap-4">
                    <FunnelIcon class="h-5 w-5 text-gray-400" />
                    <select v-model="statusFilter" class="input w-48">
                        <option value="">Pending & Under Review</option>
                        <option v-for="status in statuses" :key="status.value" :value="status.value">
                            {{ status.label }}
                        </option>
                    </select>
                </div>
            </div>

            <!-- Verifications List -->
            <div v-if="verifications.data?.length" class="space-y-4">
                <div 
                    v-for="verification in verifications.data" 
                    :key="verification.id"
                    class="bg-white rounded-xl border border-gray-100 p-6"
                >
                    <div class="flex items-start gap-4">
                        <img 
                            :src="verification.service_provider?.user?.avatar || '/images/default-avatar.png'" 
                            class="h-14 w-14 rounded-full"
                        />
                        <div class="flex-1">
                            <div class="flex items-start justify-between">
                                <div>
                                    <h3 class="font-semibold text-gray-900">
                                        {{ verification.service_provider?.business_name }}
                                    </h3>
                                    <p class="text-sm text-gray-500">
                                        {{ verification.service_provider?.user?.email }}
                                    </p>
                                </div>
                                <span 
                                    class="px-3 py-1 text-sm font-medium rounded-full"
                                    :class="getStatusInfo(verification.status).color"
                                >
                                    {{ verification.status }}
                                </span>
                            </div>

                            <div class="mt-3 flex flex-wrap gap-4 text-sm text-gray-600">
                                <div>
                                    <span class="text-gray-400">Document:</span>
                                    <span class="ml-1 font-medium">{{ verification.document_type }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-400">Submitted:</span>
                                    <span class="ml-1">{{ formatDate(verification.created_at) }}</span>
                                </div>
                                <div v-if="verification.service_provider?.primary_service_type">
                                    <span class="text-gray-400">Service:</span>
                                    <span class="ml-1">{{ verification.service_provider.primary_service_type }}</span>
                                </div>
                            </div>

                            <div class="mt-4 flex items-center gap-3">
                                <Link 
                                    :href="`/admin/verifications/${verification.uuid}`"
                                    class="btn-primary btn-sm"
                                >
                                    <EyeIcon class="h-4 w-4 mr-1" />
                                    Review Documents
                                </Link>
                                <button 
                                    v-if="['pending', 'under_review'].includes(verification.status)"
                                    @click="quickApprove(verification)"
                                    class="btn-secondary btn-sm text-green-600 border-green-200 hover:bg-green-50"
                                >
                                    <CheckCircleIcon class="h-4 w-4 mr-1" />
                                    Quick Approve
                                </button>
                                <button 
                                    v-if="['pending', 'under_review'].includes(verification.status)"
                                    @click="quickReject(verification)"
                                    class="btn-ghost btn-sm text-red-600"
                                >
                                    <XCircleIcon class="h-4 w-4 mr-1" />
                                    Reject
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="bg-white rounded-xl border border-gray-100 p-12 text-center">
                <CheckCircleIcon class="h-16 w-16 text-green-300 mx-auto mb-4" />
                <h3 class="text-lg font-medium text-gray-900 mb-2">All caught up!</h3>
                <p class="text-gray-500">No pending verifications to review.</p>
            </div>

            <!-- Pagination -->
            <div v-if="verifications.links?.length > 3" class="mt-6 flex justify-center">
                <nav class="flex gap-1">
                    <Link 
                        v-for="link in verifications.links" 
                        :key="link.label"
                        :href="link.url"
                        class="px-3 py-2 text-sm rounded-lg"
                        :class="link.active ? 'bg-primary-600 text-white' : 'text-gray-600 hover:bg-gray-100'"
                        v-html="link.label"
                    />
                </nav>
            </div>
        </div>
    </AdminLayout>
</template>
