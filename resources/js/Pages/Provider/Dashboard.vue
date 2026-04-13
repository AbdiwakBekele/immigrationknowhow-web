<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import ProviderLayout from '@/Layouts/ProviderLayout.vue';
import { 
    UserGroupIcon,
    ChatBubbleLeftRightIcon,
    StarIcon,
    EyeIcon,
    ArrowTrendingUpIcon,
    ArrowTrendingDownIcon,
    ArrowRightIcon,
    CheckBadgeIcon,
    ExclamationTriangleIcon,
    ClockIcon,
    CurrencyDollarIcon,
} from '@heroicons/vue/24/outline';
import { StarIcon as StarSolid, CheckBadgeIcon as CheckBadgeSolid } from '@heroicons/vue/24/solid';
import { computed } from 'vue';

const props = defineProps({
    stats: Object,
    recentLeads: Array,
    recentReviews: Array,
    leadsChartData: Array,
    provider: Object,
});

const page = usePage();
const user = page.props.auth.user;

const getTrendIcon = (trend) => {
    return trend >= 0 ? ArrowTrendingUpIcon : ArrowTrendingDownIcon;
};

const getTrendColor = (trend) => {
    return trend >= 0 ? 'text-green-600' : 'text-red-600';
};

const getLeadStatusColor = (status) => {
    const colors = {
        new: 'bg-blue-100 text-blue-700 border-blue-200',
        contacted: 'bg-yellow-100 text-yellow-700 border-yellow-200',
        in_progress: 'bg-purple-100 text-purple-700 border-purple-200',
        converted: 'bg-green-100 text-green-700 border-green-200',
        closed: 'bg-slate-100 text-slate-700 border-slate-200',
        declined: 'bg-red-100 text-red-700 border-red-200',
    };
    return colors[status] || 'bg-slate-100 text-slate-700 border-slate-200';
};

const formatTimeAgo = (date) => {
    const d = new Date(date);
    const now = new Date();
    const diff = now - d;
    
    if (diff < 60000) return 'Just now';
    if (diff < 3600000) return `${Math.floor(diff / 60000)}m ago`;
    if (diff < 86400000) return `${Math.floor(diff / 3600000)}h ago`;
    if (diff < 604800000) return `${Math.floor(diff / 86400000)}d ago`;
    return d.toLocaleDateString();
};

const verificationStatus = computed(() => {
    if (props.provider.background_check_status === 'clear') {
        return { icon: CheckBadgeSolid, text: 'Verified', color: 'text-green-600 bg-green-50' };
    }
    if (props.provider.background_check_status === 'invited') {
        return { icon: ClockIcon, text: 'Check Email', color: 'text-blue-600 bg-blue-50' };
    }
    if (props.provider.background_check_status === 'completed') {
        return { icon: ClockIcon, text: 'Under Review', color: 'text-yellow-600 bg-yellow-50' };
    }
    return { icon: ExclamationTriangleIcon, text: 'Not Verified', color: 'text-red-600 bg-red-50' };
});
</script>

<template>
    <Head title="Provider Dashboard" />

    <ProviderLayout :default-sidebar-minimized="true">
        <div class="space-y-8">
            <!-- Background Check Alert Banner -->
            <div v-if="provider.background_check_status !== 'clear'" class="bg-gradient-to-r from-amber-50 to-orange-50 border border-amber-200 rounded-2xl p-6">
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0 w-12 h-12 bg-amber-100 rounded-xl flex items-center justify-center">
                        <ExclamationTriangleIcon class="w-6 h-6 text-amber-600" />
                    </div>
                    <div class="flex-1">
                        <h3 class="text-lg font-semibold text-amber-900">
                            <template v-if="provider.background_check_status === 'invited'">
                                Complete Your Background Check
                            </template>
                            <template v-else-if="provider.background_check_status === 'completed'">
                                Background Check Under Review
                            </template>
                            <template v-else>
                                Background Check Required
                            </template>
                        </h3>
                        <p class="text-amber-700 mt-1">
                            <template v-if="provider.background_check_status === 'invited'">
                                We've sent you an email from Checkr. Please complete your background check to get verified.
                            </template>
                            <template v-else-if="provider.background_check_status === 'completed'">
                                Your background check is complete and under review. Results typically arrive within 2-5 business days.
                            </template>
                            <template v-else>
                                Verified providers get more visibility and build trust with potential clients. Complete a background check to unlock all features.
                            </template>
                        </p>
                        <Link 
                            v-if="!['invited', 'completed'].includes(provider.background_check_status)"
                            :href="route('provider.background-check.index')"
                            class="inline-flex items-center gap-2 mt-4 px-4 py-2 bg-amber-600 hover:bg-amber-500 text-white font-medium rounded-xl transition-colors"
                        >
                            <CheckBadgeIcon class="w-5 h-5" />
                            Start Background Check
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Header -->
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-primary-600">Overview</p>
                    <h1 class="mt-1 font-display text-3xl font-bold tracking-tight text-slate-900">
                        Welcome back, {{ user.first_name }}
                    </h1>
                    <p class="mt-2 text-slate-600">
                        Here is how your profile is performing.
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <Link 
                        :href="route('provider.background-check.index')"
                        :class="['flex items-center gap-2 px-4 py-2 rounded-xl', verificationStatus.color]"
                    >
                        <component :is="verificationStatus.icon" class="w-5 h-5" />
                        <span class="font-medium">{{ verificationStatus.text }}</span>
                    </Link>
                    <Link
                        :href="route('provider.profile.index')"
                        class="flex items-center gap-2 px-4 py-2 bg-primary-600 hover:bg-primary-500 text-white rounded-xl transition-colors"
                    >
                        <EyeIcon class="w-5 h-5" />
                        View Profile
                    </Link>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-white rounded-2xl p-6 shadow-soft">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 bg-primary-100 rounded-xl flex items-center justify-center">
                            <UserGroupIcon class="w-6 h-6 text-primary-600" />
                        </div>
                        <div v-if="stats.leadsTrend !== undefined" class="flex items-center gap-1 text-sm">
                            <component :is="getTrendIcon(stats.leadsTrend)" :class="['w-4 h-4', getTrendColor(stats.leadsTrend)]" />
                            <span :class="getTrendColor(stats.leadsTrend)">{{ Math.abs(stats.leadsTrend) }}%</span>
                        </div>
                    </div>
                    <p class="text-3xl font-bold text-slate-900">{{ stats.totalLeads || 0 }}</p>
                    <p class="text-sm text-slate-500 mt-1">Total Leads</p>
                </div>

                <div class="bg-white rounded-2xl p-6 shadow-soft">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                            <ChatBubbleLeftRightIcon class="w-6 h-6 text-blue-600" />
                        </div>
                        <span class="text-sm text-blue-600 font-medium">{{ stats.newLeads || 0 }} new</span>
                    </div>
                    <p class="text-3xl font-bold text-slate-900">{{ stats.openLeads || 0 }}</p>
                    <p class="text-sm text-slate-500 mt-1">Open Leads</p>
                </div>

                <div class="bg-white rounded-2xl p-6 shadow-soft">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 bg-secondary-100 rounded-xl flex items-center justify-center">
                            <StarIcon class="w-6 h-6 text-secondary-600" />
                        </div>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <p class="text-3xl font-bold text-slate-900">{{ provider.average_rating ? Number(provider.average_rating).toFixed(1) : '–' }}</p>
                        <div class="flex items-center gap-0.5">
                            <StarSolid v-for="i in 5" :key="i" :class="['w-4 h-4', i <= Math.round(Number(provider.average_rating) || 0) ? 'text-secondary-500' : 'text-slate-200']" />
                        </div>
                    </div>
                    <p class="text-sm text-slate-500 mt-1">{{ provider.total_reviews || 0 }} Reviews</p>
                </div>

                <div class="bg-white rounded-2xl p-6 shadow-soft">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 bg-accent-100 rounded-xl flex items-center justify-center">
                            <EyeIcon class="w-6 h-6 text-accent-600" />
                        </div>
                        <div v-if="stats.viewsTrend !== undefined" class="flex items-center gap-1 text-sm">
                            <component :is="getTrendIcon(stats.viewsTrend)" :class="['w-4 h-4', getTrendColor(stats.viewsTrend)]" />
                            <span :class="getTrendColor(stats.viewsTrend)">{{ Math.abs(stats.viewsTrend) }}%</span>
                        </div>
                    </div>
                    <p class="text-3xl font-bold text-slate-900">{{ stats.profileViews || 0 }}</p>
                    <p class="text-sm text-slate-500 mt-1">Profile Views (30d)</p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Recent Leads -->
                <div class="lg:col-span-2 bg-white rounded-2xl shadow-soft overflow-hidden">
                    <div class="flex items-center justify-between p-6 border-b border-slate-100">
                        <h2 class="text-lg font-display font-bold text-slate-900">Recent Leads</h2>
                        <Link :href="route('provider.leads.index')" class="text-sm text-primary-600 hover:text-primary-700 font-medium flex items-center gap-1">
                            View all
                            <ArrowRightIcon class="w-4 h-4" />
                        </Link>
                    </div>

                    <div v-if="recentLeads?.length" class="divide-y divide-slate-100">
                        <Link 
                            v-for="lead in recentLeads" 
                            :key="lead.uuid"
                            :href="route('provider.leads.show', lead.uuid)"
                            class="flex items-center gap-4 p-4 hover:bg-slate-50 transition-colors"
                        >
                            <img 
                                :src="lead.user?.avatar || '/img/default-avatar.png'"
                                :alt="lead.user?.first_name"
                                class="w-12 h-12 rounded-full object-cover"
                            />
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <h3 class="font-semibold text-slate-900">
                                        {{ lead.user?.first_name }} {{ lead.user?.last_name }}
                                    </h3>
                                    <span :class="['px-2 py-0.5 text-xs font-medium rounded-full border', getLeadStatusColor(lead.status)]">
                                        {{ lead.status }}
                                    </span>
                                </div>
                                <p class="text-sm text-slate-500 truncate">{{ lead.message }}</p>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <p class="text-sm text-slate-400">{{ formatTimeAgo(lead.created_at) }}</p>
                                <p v-if="lead.urgency === 'urgent'" class="text-xs text-red-600 font-medium mt-1">Urgent</p>
                            </div>
                        </Link>
                    </div>
                    
                    <div v-else class="p-12 text-center">
                        <UserGroupIcon class="w-12 h-12 text-slate-300 mx-auto mb-3" />
                        <p class="text-slate-500">No leads yet</p>
                        <p class="text-sm text-slate-400 mt-1">Complete your profile to attract more leads</p>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Conversion Rate -->
                    <div class="bg-white rounded-2xl p-6 shadow-soft">
                        <h3 class="font-semibold text-slate-900 mb-4">Conversion Rate</h3>
                        <div class="relative pt-1">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm text-slate-500">Leads Converted</span>
                                <span class="text-sm font-medium text-primary-600">{{ stats.conversionRate || 0 }}%</span>
                            </div>
                            <div class="h-3 bg-slate-100 rounded-full overflow-hidden">
                                <div 
                                    :style="{ width: `${stats.conversionRate || 0}%` }"
                                    class="h-full bg-gradient-to-r from-primary-500 to-primary-400 rounded-full transition-all"
                                ></div>
                            </div>
                        </div>
                        <div class="flex items-center justify-between mt-4 text-sm">
                            <span class="text-slate-500">{{ stats.convertedLeads || 0 }} converted</span>
                            <span class="text-slate-500">{{ stats.totalLeads || 0 }} total</span>
                        </div>
                    </div>

                    <!-- Recent Reviews -->
                    <div class="bg-white rounded-2xl shadow-soft overflow-hidden">
                        <div class="flex items-center justify-between p-4 border-b border-slate-100">
                            <h3 class="font-semibold text-slate-900">Recent Reviews</h3>
                            <Link :href="route('provider.reviews.index')" class="text-sm text-primary-600 hover:text-primary-700">
                                View all
                            </Link>
                        </div>

                        <div v-if="recentReviews?.length" class="divide-y divide-slate-100">
                            <div v-for="review in recentReviews" :key="review.uuid" class="p-4">
                                <div class="flex items-center gap-2 mb-2">
                                    <div class="flex items-center gap-0.5">
                                        <StarSolid v-for="i in review.rating" :key="i" class="w-4 h-4 text-secondary-500" />
                                    </div>
                                    <span class="text-sm text-slate-400">{{ formatTimeAgo(review.created_at) }}</span>
                                </div>
                                <p class="text-sm text-slate-600 line-clamp-2">{{ review.comment }}</p>
                                <p class="text-xs text-slate-400 mt-2">– {{ review.user?.first_name }}</p>
                            </div>
                        </div>
                        
                        <div v-else class="p-6 text-center text-slate-500 text-sm">
                            No reviews yet
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="bg-gradient-to-br from-primary-600 to-primary-700 rounded-2xl p-6 text-white">
                        <h3 class="font-semibold mb-4">Quick Actions</h3>
                        <div class="space-y-3">
                            <Link 
                                :href="route('provider.profile.edit')"
                                class="flex items-center justify-between p-3 bg-white/10 hover:bg-white/20 rounded-xl transition-colors"
                            >
                                <span>Edit Profile</span>
                                <ArrowRightIcon class="w-4 h-4" />
                            </Link>
                            <Link 
                                v-if="provider.background_check_status !== 'clear'"
                                :href="route('provider.background-check.index')"
                                class="flex items-center justify-between p-3 bg-white/10 hover:bg-white/20 rounded-xl transition-colors"
                            >
                                <span>Get Verified</span>
                                <ArrowRightIcon class="w-4 h-4" />
                            </Link>
                            <Link 
                                :href="route('provider.analytics.index')"
                                class="flex items-center justify-between p-3 bg-white/10 hover:bg-white/20 rounded-xl transition-colors"
                            >
                                <span>View Analytics</span>
                                <ArrowRightIcon class="w-4 h-4" />
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </ProviderLayout>
</template>
