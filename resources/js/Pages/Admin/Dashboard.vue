<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { 
    UsersIcon,
    UserGroupIcon,
    ShieldCheckIcon,
    PlusIcon,
    StarIcon,
    ChatBubbleLeftRightIcon,
    DocumentTextIcon,
    ArrowTrendingUpIcon,
    ArrowTrendingDownIcon,
    EyeIcon,
    CheckCircleIcon,
    ClockIcon,
    ExclamationTriangleIcon
} from '@heroicons/vue/24/outline';

defineProps({
    stats: { type: Object, default: () => ({}) },
    recentUsers: { type: Array, default: () => [] },
    pendingVerifications: { type: Array, default: () => [] },
    recentReviews: { type: Array, default: () => [] },
    leadsTrend: { type: Array, default: () => [] },
});

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
};
</script>

<template>
    <Head title="Admin Dashboard" />

    <AdminLayout>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-2xl font-display font-bold text-gray-900">Dashboard</h1>
                <p class="text-gray-500 mt-1">Overview of platform activity and metrics</p>
            </div>

            <!-- Stats Grid -->
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div class="bg-white rounded-xl border border-gray-100 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-2 bg-blue-100 rounded-lg">
                            <UsersIcon class="h-5 w-5 text-blue-600" />
                        </div>
                        <span class="flex items-center text-sm font-medium text-green-600">
                            <ArrowTrendingUpIcon class="h-4 w-4 mr-1" />
                            +{{ stats.usersGrowth || 0 }}%
                        </span>
                    </div>
                    <div class="text-3xl font-display font-bold text-gray-900">{{ stats.totalUsers || 0 }}</div>
                    <div class="text-sm text-gray-500 mt-1">Total Users</div>
                </div>

                <div class="bg-white rounded-xl border border-gray-100 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-2 bg-green-100 rounded-lg">
                            <UserGroupIcon class="h-5 w-5 text-green-600" />
                        </div>
                    </div>
                    <div class="text-3xl font-display font-bold text-gray-900">{{ stats.totalProviders || 0 }}</div>
                    <div class="text-sm text-gray-500 mt-1">Service Providers</div>
                </div>

                <div class="bg-white rounded-xl border border-gray-100 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-2 bg-yellow-100 rounded-lg">
                            <ShieldCheckIcon class="h-5 w-5 text-yellow-600" />
                        </div>
                        <span v-if="stats.pendingVerifications > 0" class="flex items-center text-sm font-medium text-orange-600">
                            <ExclamationTriangleIcon class="h-4 w-4 mr-1" />
                            Action needed
                        </span>
                    </div>
                    <div class="text-3xl font-display font-bold text-gray-900">{{ stats.pendingVerifications || 0 }}</div>
                    <div class="text-sm text-gray-500 mt-1">Pending Verifications</div>
                </div>

                <div class="bg-white rounded-xl border border-gray-100 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-2 bg-purple-100 rounded-lg">
                            <ChatBubbleLeftRightIcon class="h-5 w-5 text-purple-600" />
                        </div>
                    </div>
                    <div class="text-3xl font-display font-bold text-gray-900">{{ stats.totalLeads || 0 }}</div>
                    <div class="text-sm text-gray-500 mt-1">Total Leads</div>
                </div>
            </div>

            <div class="grid lg:grid-cols-2 gap-6 mb-8">
                <!-- Pending Verifications -->
                <div class="bg-white rounded-xl border border-gray-100 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold text-gray-900">Pending Verifications</h2>
                        <Link href="/admin/verifications" class="text-primary-600 hover:text-primary-700 text-sm font-medium">
                            View all →
                        </Link>
                    </div>
                    <div v-if="pendingVerifications.length" class="space-y-3">
                        <Link 
                            v-for="verification in pendingVerifications" 
                            :key="verification.id"
                            :href="`/admin/verifications/${verification.uuid}`"
                            class="flex items-center gap-4 p-3 rounded-lg hover:bg-gray-50 transition-colors"
                        >
                            <img 
                                :src="verification.service_provider?.user?.avatar || '/images/default-avatar.png'" 
                                class="h-10 w-10 rounded-full"
                            />
                            <div class="flex-1 min-w-0">
                                <div class="font-medium text-gray-900 truncate">
                                    {{ verification.service_provider?.business_name }}
                                </div>
                                <div class="text-sm text-gray-500">
                                    {{ verification.document_type }} · {{ formatDate(verification.created_at) }}
                                </div>
                            </div>
                            <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-700 rounded">
                                {{ verification.status }}
                            </span>
                        </Link>
                    </div>
                    <div v-else class="text-center py-8 text-gray-500">
                        <CheckCircleIcon class="h-12 w-12 text-green-300 mx-auto mb-2" />
                        <p>All verifications reviewed!</p>
                    </div>
                </div>

                <!-- Recent Users -->
                <div class="bg-white rounded-xl border border-gray-100 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold text-gray-900">Recent Users</h2>
                        <div class="flex items-center gap-3">
                            <Link href="/admin/users/create" class="inline-flex items-center gap-1 text-primary-600 hover:text-primary-700 text-sm font-medium">
                                <PlusIcon class="h-4 w-4" />
                                Add User
                            </Link>
                            <Link href="/admin/users" class="text-primary-600 hover:text-primary-700 text-sm font-medium">
                                View all →
                            </Link>
                        </div>
                    </div>
                    <div v-if="recentUsers.length" class="space-y-3">
                        <Link 
                            v-for="user in recentUsers" 
                            :key="user.id"
                            :href="`/admin/users/${user.id}`"
                            class="flex items-center gap-4 p-3 rounded-lg hover:bg-gray-50 transition-colors"
                        >
                            <img 
                                :src="user.avatar || '/images/default-avatar.png'" 
                                class="h-10 w-10 rounded-full"
                            />
                            <div class="flex-1 min-w-0">
                                <div class="font-medium text-gray-900 truncate">
                                    {{ user.full_name }}
                                </div>
                                <div class="text-sm text-gray-500">
                                    {{ user.email }}
                                </div>
                            </div>
                            <span 
                                class="px-2 py-1 text-xs font-medium rounded"
                                :class="user.roles?.[0]?.name === 'provider' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700'"
                            >
                                {{ user.roles?.[0]?.name || 'user' }}
                            </span>
                        </Link>
                    </div>
                    <div v-else class="text-center py-8 text-gray-500">
                        <p>No recent users</p>
                    </div>
                </div>
            </div>

            <!-- Recent Reviews -->
            <div class="bg-white rounded-xl border border-gray-100 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-900">Recent Reviews</h2>
                    <Link href="/admin/reviews" class="text-primary-600 hover:text-primary-700 text-sm font-medium">
                        View all →
                    </Link>
                </div>
                <div v-if="recentReviews.length" class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="border-b border-gray-100">
                            <tr>
                                <th class="text-left py-3 px-4 text-sm font-medium text-gray-500">Reviewer</th>
                                <th class="text-left py-3 px-4 text-sm font-medium text-gray-500">Provider</th>
                                <th class="text-left py-3 px-4 text-sm font-medium text-gray-500">Rating</th>
                                <th class="text-left py-3 px-4 text-sm font-medium text-gray-500">Date</th>
                                <th class="text-right py-3 px-4 text-sm font-medium text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="review in recentReviews" :key="review.id" class="hover:bg-gray-50">
                                <td class="py-3 px-4">
                                    <div class="font-medium text-gray-900">{{ review.user?.full_name }}</div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="text-gray-600">{{ review.service_provider?.business_name }}</div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-1">
                                        <StarIcon class="h-4 w-4 text-yellow-500 fill-current" />
                                        <span>{{ review.overall_rating }}</span>
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-gray-500 text-sm">
                                    {{ formatDate(review.created_at) }}
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <Link :href="`/admin/reviews/${review.uuid}`" class="text-primary-600 hover:text-primary-700">
                                        <EyeIcon class="h-5 w-5" />
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="text-center py-8 text-gray-500">
                    <p>No recent reviews</p>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
