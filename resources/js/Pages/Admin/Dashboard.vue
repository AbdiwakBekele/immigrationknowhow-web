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
    ArrowTrendingUpIcon,
    EyeIcon,
    CheckCircleIcon,
    ExclamationTriangleIcon
} from '@heroicons/vue/24/outline';

defineProps({
    userStats: { type: Object, default: () => ({}) },
    providerStats: { type: Object, default: () => ({}) },
    backgroundCheckStats: { type: Object, default: () => ({}) },
    leadStats: { type: Object, default: () => ({}) },
    recentUsers: { type: Array, default: () => [] },
    pendingBackgroundChecks: { type: Array, default: () => [] },
    recentReviews: { type: Array, default: () => [] },
});

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
};

const getInitial = (user) => {
    const firstName = (user?.first_name || '').trim();
    if (firstName) return firstName.charAt(0).toUpperCase();

    const fullName = (user?.full_name || '').trim();
    return fullName ? fullName.charAt(0).toUpperCase() : '?';
};
</script>

<template>
    <Head title="Admin Dashboard" />

    <AdminLayout>
        <div class="mx-auto max-w-7xl space-y-5">
            <!-- Header -->
            <div class="flex items-end justify-between rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                <div>
                    <h1 class="text-xl font-display font-bold text-slate-900">Dashboard</h1>
                    <p class="mt-0.5 text-sm text-slate-500">Overview of platform activity and metrics</p>
                </div>
                <span class="hidden rounded-full bg-sky-50 px-2.5 py-1 text-xs font-medium text-sky-700 sm:inline-flex">
                    Live overview
                </span>
            </div>

            <!-- Stats Grid -->
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="mb-3 flex items-center justify-between">
                        <div class="p-2 bg-blue-100 rounded-lg">
                            <UsersIcon class="h-5 w-5 text-blue-600" />
                        </div>
                        <span class="flex items-center text-xs font-semibold text-emerald-600">
                            <ArrowTrendingUpIcon class="h-4 w-4 mr-1" />
                            {{ userStats.this_month || 0 }} new
                        </span>
                    </div>
                    <div class="text-2xl font-display font-bold text-slate-900">{{ userStats.total || 0 }}</div>
                    <div class="mt-0.5 text-xs text-slate-500">Total Users</div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="mb-3 flex items-center justify-between">
                        <div class="p-2 bg-green-100 rounded-lg">
                            <UserGroupIcon class="h-5 w-5 text-green-600" />
                        </div>
                    </div>
                    <div class="text-2xl font-display font-bold text-slate-900">{{ providerStats.total || 0 }}</div>
                    <div class="mt-0.5 text-xs text-slate-500">Service Providers</div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="mb-3 flex items-center justify-between">
                        <div class="p-2 bg-yellow-100 rounded-lg">
                            <ShieldCheckIcon class="h-5 w-5 text-yellow-600" />
                        </div>
                        <span v-if="backgroundCheckStats.pending > 0" class="flex items-center text-xs font-semibold text-orange-600">
                            <ExclamationTriangleIcon class="h-4 w-4 mr-1" />
                            Action needed
                        </span>
                    </div>
                    <div class="text-2xl font-display font-bold text-slate-900">{{ backgroundCheckStats.pending || 0 }}</div>
                    <div class="mt-0.5 text-xs text-slate-500">Pending Verifications</div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="mb-3 flex items-center justify-between">
                        <div class="p-2 bg-purple-100 rounded-lg">
                            <ChatBubbleLeftRightIcon class="h-5 w-5 text-purple-600" />
                        </div>
                    </div>
                    <div class="text-2xl font-display font-bold text-slate-900">{{ leadStats.total || 0 }}</div>
                    <div class="mt-0.5 text-xs text-slate-500">Total Leads</div>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <!-- Pending Verifications -->
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-base font-semibold text-slate-900">Pending Verifications</h2>
                        <Link href="/admin/background-checks" class="text-xs font-semibold text-sky-600 hover:text-sky-700">
                            View all →
                        </Link>
                    </div>
                    <div v-if="pendingBackgroundChecks.length" class="space-y-2">
                        <Link 
                            v-for="verification in pendingBackgroundChecks" 
                            :key="verification.id"
                            :href="`/admin/background-checks/${verification.uuid}`"
                            class="flex items-center gap-3 rounded-lg border border-transparent p-2.5 transition-colors hover:border-slate-200 hover:bg-slate-50"
                        >
                            <img 
                                :src="verification.service_provider?.user?.avatar || '/images/default-avatar.png'" 
                                class="h-9 w-9 rounded-full"
                            />
                            <div class="flex-1 min-w-0">
                                <div class="truncate text-sm font-semibold text-slate-900">
                                    {{ verification.service_provider?.business_name }}
                                </div>
                                <div class="text-xs text-slate-500">
                                    {{ verification.document_type }} · {{ formatDate(verification.created_at) }}
                                </div>
                            </div>
                            <span class="rounded-md bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-700">
                                {{ verification.status }}
                            </span>
                        </Link>
                    </div>
                    <div v-else class="py-6 text-center text-slate-500">
                        <CheckCircleIcon class="mx-auto mb-2 h-10 w-10 text-emerald-300" />
                        <p class="text-sm">All verifications reviewed!</p>
                    </div>
                </div>

                <!-- Recent Users -->
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-base font-semibold text-slate-900">Recent Users</h2>
                        <div class="flex items-center gap-3">
                            <Link href="/admin/users/create" class="inline-flex items-center gap-1 text-xs font-semibold text-sky-600 hover:text-sky-700">
                                <PlusIcon class="h-4 w-4" />
                                Add User
                            </Link>
                            <Link href="/admin/users" class="text-xs font-semibold text-sky-600 hover:text-sky-700">
                                View all →
                            </Link>
                        </div>
                    </div>
                    <div v-if="recentUsers.length" class="space-y-2">
                        <Link 
                            v-for="user in recentUsers" 
                            :key="user.id"
                            :href="`/admin/users/${user.id}`"
                            class="flex items-center gap-3 rounded-lg border border-transparent p-2.5 transition-colors hover:border-slate-200 hover:bg-slate-50"
                        >
                            <div class="flex h-9 w-9 items-center justify-center rounded-full bg-sky-100 text-sm font-semibold text-sky-700">
                                {{ getInitial(user) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="truncate text-sm font-semibold text-slate-900">
                                    {{ user.full_name }}
                                </div>
                                <div class="text-xs text-slate-500">
                                    {{ user.email }}
                                </div>
                            </div>
                            <span 
                                class="rounded-md px-2 py-0.5 text-xs font-medium"
                                :class="user.roles?.[0]?.name === 'provider' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700'"
                            >
                                {{ user.roles?.[0]?.name || 'user' }}
                            </span>
                        </Link>
                    </div>
                    <div v-else class="py-6 text-center text-sm text-slate-500">
                        <p>No recent users</p>
                    </div>
                </div>
            </div>

            <!-- Recent Reviews -->
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-base font-semibold text-slate-900">Recent Reviews</h2>
                    <Link href="/admin/reviews" class="text-xs font-semibold text-sky-600 hover:text-sky-700">
                        View all →
                    </Link>
                </div>
                <div v-if="recentReviews.length" class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="border-b border-slate-200">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500">Reviewer</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500">Provider</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500">Rating</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500">Date</th>
                                <th class="px-3 py-2 text-right text-xs font-semibold text-slate-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="review in recentReviews" :key="review.id" class="hover:bg-slate-50">
                                <td class="px-3 py-2.5">
                                    <div class="text-sm font-medium text-slate-900">{{ review.user?.full_name }}</div>
                                </td>
                                <td class="px-3 py-2.5">
                                    <div class="text-sm text-slate-600">{{ review.service_provider?.business_name }}</div>
                                </td>
                                <td class="px-3 py-2.5">
                                    <div class="flex items-center gap-1">
                                        <StarIcon class="h-4 w-4 text-yellow-500 fill-current" />
                                        <span class="text-sm">{{ review.overall_rating }}</span>
                                    </div>
                                </td>
                                <td class="px-3 py-2.5 text-xs text-slate-500">
                                    {{ formatDate(review.created_at) }}
                                </td>
                                <td class="px-3 py-2.5 text-right">
                                    <Link :href="`/admin/reviews/${review.uuid}`" class="text-sky-600 hover:text-sky-700">
                                        <EyeIcon class="h-4 w-4" />
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="py-6 text-center text-sm text-slate-500">
                    <p>No recent reviews</p>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
