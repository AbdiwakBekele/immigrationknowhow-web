<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { 
    ShieldCheckIcon,
    ArrowLeftIcon,
    UserIcon,
    EnvelopeIcon,
    PhoneIcon,
    MapPinIcon,
    CalendarIcon,
    ClockIcon,
    CheckBadgeIcon,
    ExclamationTriangleIcon,
    DocumentTextIcon,
    LinkIcon,
} from '@heroicons/vue/24/outline';
import { CheckBadgeIcon as CheckBadgeSolid } from '@heroicons/vue/24/solid';

const props = defineProps({
    backgroundCheck: Object,
    provider: Object,
});

const getStatusClasses = (status) => {
    const classes = {
        pending: 'bg-gray-100 text-gray-700 border-gray-200',
        invited: 'bg-blue-100 text-blue-700 border-blue-200',
        completed: 'bg-amber-100 text-amber-700 border-amber-200',
        clear: 'bg-emerald-100 text-emerald-700 border-emerald-200',
        consider: 'bg-orange-100 text-orange-700 border-orange-200',
        suspended: 'bg-red-100 text-red-700 border-red-200',
        dispute: 'bg-purple-100 text-purple-700 border-purple-200',
        expired: 'bg-gray-100 text-gray-600 border-gray-200',
    };
    return classes[status] || 'bg-gray-100 text-gray-700 border-gray-200';
};
</script>

<template>
    <Head :title="`Background Check - ${backgroundCheck.full_name}`" />

    <AdminLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center gap-4">
                <Link
                    href="/admin/background-checks"
                    class="p-2 rounded-lg hover:bg-gray-100 transition-colors"
                >
                    <ArrowLeftIcon class="w-5 h-5 text-gray-500" />
                </Link>
                <div class="flex-1">
                    <h1 class="text-2xl font-bold text-gray-900">Background Check Details</h1>
                    <p class="text-gray-500">{{ backgroundCheck.full_name }}</p>
                </div>
                <span :class="['px-4 py-2 rounded-xl text-sm font-medium border', getStatusClasses(backgroundCheck.status)]">
                    {{ backgroundCheck.status_display.label }}
                </span>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Main Info -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Status Card -->
                    <div v-if="backgroundCheck.status === 'clear'" class="bg-emerald-50 border border-emerald-200 rounded-xl p-6">
                        <div class="flex items-center gap-4">
                            <div class="p-3 bg-emerald-100 rounded-full">
                                <CheckBadgeSolid class="w-8 h-8 text-emerald-600" />
                            </div>
                            <div>
                                <h2 class="text-lg font-semibold text-emerald-800">Background Check Cleared</h2>
                                <p class="text-emerald-700">
                                    Completed on {{ backgroundCheck.completed_at }}
                                    <span v-if="backgroundCheck.expires_at"> · Expires {{ backgroundCheck.expires_at }}</span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="backgroundCheck.status === 'consider'" class="bg-orange-50 border border-orange-200 rounded-xl p-6">
                        <div class="flex items-center gap-4">
                            <div class="p-3 bg-orange-100 rounded-full">
                                <ExclamationTriangleIcon class="w-8 h-8 text-orange-600" />
                            </div>
                            <div>
                                <h2 class="text-lg font-semibold text-orange-800">Review Required</h2>
                                <p class="text-orange-700">
                                    This background check has findings that may require manual review.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Candidate Info -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Candidate Information</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="flex items-center gap-3">
                                <UserIcon class="w-5 h-5 text-gray-400" />
                                <div>
                                    <p class="text-sm text-gray-500">Full Name</p>
                                    <p class="font-medium text-gray-900">{{ backgroundCheck.full_name }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <EnvelopeIcon class="w-5 h-5 text-gray-400" />
                                <div>
                                    <p class="text-sm text-gray-500">Email</p>
                                    <p class="font-medium text-gray-900">{{ backgroundCheck.email }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <PhoneIcon class="w-5 h-5 text-gray-400" />
                                <div>
                                    <p class="text-sm text-gray-500">Phone</p>
                                    <p class="font-medium text-gray-900">{{ backgroundCheck.phone || '—' }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <MapPinIcon class="w-5 h-5 text-gray-400" />
                                <div>
                                    <p class="text-sm text-gray-500">ZIP Code</p>
                                    <p class="font-medium text-gray-900">{{ backgroundCheck.zipcode || '—' }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <CalendarIcon class="w-5 h-5 text-gray-400" />
                                <div>
                                    <p class="text-sm text-gray-500">Date of Birth</p>
                                    <p class="font-medium text-gray-900">{{ backgroundCheck.dob || '—' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Check Details -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Check Details</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-500">Package</p>
                                <p class="font-medium text-gray-900">{{ backgroundCheck.package || 'basic_criminal' }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Adjudication</p>
                                <p class="font-medium text-gray-900">{{ backgroundCheck.adjudication || '—' }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Initiated</p>
                                <p class="font-medium text-gray-900">{{ backgroundCheck.initiated_at }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Completed</p>
                                <p class="font-medium text-gray-900">{{ backgroundCheck.completed_at || 'Pending' }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Expires</p>
                                <p class="font-medium text-gray-900">
                                    <span v-if="backgroundCheck.expires_at">
                                        {{ backgroundCheck.expires_at }}
                                        <span v-if="backgroundCheck.days_until_expiry > 0" class="text-gray-500">
                                            ({{ backgroundCheck.days_until_expiry }} days)
                                        </span>
                                        <span v-else-if="backgroundCheck.days_until_expiry <= 0" class="text-red-500">
                                            (Expired)
                                        </span>
                                    </span>
                                    <span v-else>—</span>
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Last Webhook</p>
                                <p class="font-medium text-gray-900">{{ backgroundCheck.last_webhook_at || 'None received' }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Checkr IDs -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Checkr Reference IDs</h3>
                        <div class="space-y-3">
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <span class="text-sm text-gray-500">Candidate ID</span>
                                <code class="text-sm font-mono text-gray-700">{{ backgroundCheck.checkr_candidate_id || '—' }}</code>
                            </div>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <span class="text-sm text-gray-500">Report ID</span>
                                <code class="text-sm font-mono text-gray-700">{{ backgroundCheck.checkr_report_id || '—' }}</code>
                            </div>
                        </div>
                        <a
                            v-if="backgroundCheck.checkr_candidate_id"
                            :href="`https://dashboard.checkr.com/candidates/${backgroundCheck.checkr_candidate_id}`"
                            target="_blank"
                            class="inline-flex items-center gap-2 mt-4 text-sm text-sky-600 hover:text-sky-700"
                        >
                            <LinkIcon class="w-4 h-4" />
                            View in Checkr Dashboard
                        </a>
                    </div>

                    <!-- Report Summary -->
                    <div v-if="backgroundCheck.report_summary" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Report Summary</h3>
                        <pre class="p-4 bg-gray-50 rounded-lg text-sm font-mono text-gray-700 overflow-x-auto">{{ JSON.stringify(backgroundCheck.report_summary, null, 2) }}</pre>
                    </div>

                    <!-- Webhook History -->
                    <div v-if="backgroundCheck.webhook_history?.length" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Webhook History</h3>
                        <div class="space-y-3">
                            <div
                                v-for="(webhook, index) in backgroundCheck.webhook_history"
                                :key="index"
                                class="p-3 bg-gray-50 rounded-lg"
                            >
                                <div class="flex items-center justify-between mb-1">
                                    <span class="font-medium text-gray-900">{{ webhook.event }}</span>
                                    <span class="text-sm text-gray-500">{{ webhook.received_at }}</span>
                                </div>
                                <p v-if="webhook.payload_summary" class="text-sm text-gray-600">
                                    Type: {{ webhook.payload_summary.type }} · Object: {{ webhook.payload_summary.object }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Provider Card -->
                    <div v-if="provider" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Service Provider</h3>
                        <div class="flex items-center gap-3 mb-4">
                            <img
                                v-if="provider.user.avatar"
                                :src="provider.user.avatar"
                                :alt="provider.user.name"
                                class="w-12 h-12 rounded-full object-cover"
                            />
                            <div v-else class="w-12 h-12 rounded-full bg-gray-200 flex items-center justify-center">
                                <UserIcon class="w-6 h-6 text-gray-400" />
                            </div>
                            <div>
                                <p class="font-medium text-gray-900">{{ provider.business_name }}</p>
                                <p class="text-sm text-gray-500">{{ provider.user.email }}</p>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <Link
                                :href="`/admin/providers/${provider.id}`"
                                class="flex-1 px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg text-center transition-colors"
                            >
                                View Provider
                            </Link>
                            <Link
                                :href="`/providers/${provider.slug}`"
                                target="_blank"
                                class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition-colors"
                            >
                                Public Profile
                            </Link>
                        </div>
                    </div>

                    <!-- Quick Stats -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Status</h3>
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500">Valid</span>
                                <span :class="backgroundCheck.is_valid ? 'text-emerald-600' : 'text-gray-400'">
                                    {{ backgroundCheck.is_valid ? 'Yes' : 'No' }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500">Days Until Expiry</span>
                                <span :class="backgroundCheck.days_until_expiry > 30 ? 'text-gray-900' : 'text-orange-600'">
                                    {{ backgroundCheck.days_until_expiry ?? '—' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
