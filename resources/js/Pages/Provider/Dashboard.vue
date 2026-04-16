<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
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
    ArchiveBoxIcon,
    TrashIcon,
} from '@heroicons/vue/24/outline';
import { StarIcon as StarSolid, CheckBadgeIcon as CheckBadgeSolid } from '@heroicons/vue/24/solid';
import { computed, ref } from 'vue';

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

const canStartBackgroundCheck = computed(() => {
    return !['clear', 'invited', 'completed'].includes(props.provider.background_check_status);
});

const statCards = computed(() => [
    {
        title: 'Total Leads',
        value: props.stats?.totalLeads || 0,
        sublabel: 'All inquiries received',
        accent: 'blue',
        icon: UserGroupIcon,
        chip: props.stats?.leadsTrend !== undefined ? `${Math.abs(props.stats.leadsTrend)}%` : 'Overview',
        trend: props.stats?.leadsTrend,
    },
    {
        title: 'Open Leads',
        value: props.stats?.openLeads || 0,
        sublabel: `${props.stats?.newLeads || 0} new`,
        accent: 'emerald',
        icon: ChatBubbleLeftRightIcon,
        chip: 'Active',
    },
    {
        title: 'Average Rating',
        value: props.provider?.average_rating ? Number(props.provider.average_rating).toFixed(1) : '–',
        sublabel: `${props.provider?.total_reviews || 0} reviews`,
        accent: 'amber',
        icon: StarIcon,
        chip: 'Reviews',
    },
    {
        title: 'Profile Views (30d)',
        value: props.stats?.profileViews || 0,
        sublabel: 'Visibility in marketplace',
        accent: 'violet',
        icon: EyeIcon,
        chip: props.stats?.viewsTrend !== undefined ? `${Math.abs(props.stats.viewsTrend)}%` : 'Traffic',
        trend: props.stats?.viewsTrend,
    },
]);

const accentMap = {
    blue: { box: 'bg-blue-50 text-blue-700', chip: 'bg-blue-100 text-blue-700' },
    emerald: { box: 'bg-emerald-50 text-emerald-700', chip: 'bg-emerald-100 text-emerald-700' },
    amber: { box: 'bg-amber-50 text-amber-700', chip: 'bg-amber-100 text-amber-700' },
    violet: { box: 'bg-violet-50 text-violet-700', chip: 'bg-violet-100 text-violet-700' },
};

const failedAvatarKeys = ref(new Set());

const resolveAvatar = (person) => {
    const candidate = (person?.avatar_url || person?.avatar || '').trim();
    if (!candidate) return '';
    if (candidate.startsWith('http://') || candidate.startsWith('https://') || candidate.startsWith('/')) {
        return candidate;
    }
    return `/storage/${candidate}`;
};

const leadAvatarKey = (lead) => lead?.uuid || lead?.id || lead?.user?.id || '';

const hasLeadAvatar = (lead) => {
    const key = leadAvatarKey(lead);
    return Boolean(resolveAvatar(lead?.user)) && !failedAvatarKeys.value.has(key);
};

const markLeadAvatarFailed = (lead) => {
    const key = leadAvatarKey(lead);
    if (!key) return;
    failedAvatarKeys.value.add(key);
};

const leadFirstName = (lead) => {
    return (lead?.user?.first_name || '').trim() || 'User';
};

const leadFirstInitial = (lead) => {
    return leadFirstName(lead).charAt(0).toUpperCase();
};

const archiveConversation = (conversationUuid) => {
    if (!conversationUuid) return;

    router.post(route('provider.messages.archive', conversationUuid), {}, {
        preserveScroll: true,
    });
};

const deleteConversation = (conversationUuid) => {
    if (!conversationUuid) return;
    if (!window.confirm('Delete this conversation permanently?')) return;

    router.delete(route('provider.messages.destroy', conversationUuid), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Provider Dashboard" />

    <ProviderLayout>
        <div class="admin-page-container">
            <!-- Background Check Alert Banner -->
            <div v-if="provider.background_check_status !== 'clear'" class="rounded-[1.75rem] border border-amber-200 bg-gradient-to-r from-amber-50 to-orange-50 p-6 shadow-sm">
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

            <section class="admin-hero-card">
                <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Provider overview</p>
                        <h1 class="mt-2 admin-title">
                            Welcome back, {{ user.first_name }}
                        </h1>
                        <p class="admin-subtitle">
                            Track leads, profile visibility, reviews, and conversion performance in one place.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 md:ml-auto md:justify-end lg:flex-nowrap">
                    <Link
                        :href="route('provider.subscriptions.index')"
                        class="inline-flex items-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-700 transition hover:bg-emerald-100"
                    >
                        <CurrencyDollarIcon class="h-5 w-5" />
                        Subscriptions
                    </Link>
                    <Link
                        :href="route('provider.background-check.index')"
                        :class="['inline-flex items-center gap-2 rounded-2xl px-4 py-2.5 text-sm font-medium', verificationStatus.color]"
                    >
                        <component :is="verificationStatus.icon" class="h-5 w-5" />
                        {{ verificationStatus.text }}
                    </Link>
                    <Link
                        v-if="canStartBackgroundCheck"
                        :href="route('provider.background-check.index')"
                        class="inline-flex items-center gap-2 rounded-2xl bg-rose-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-rose-700"
                    >
                        <CheckBadgeIcon class="h-5 w-5" />
                        Start Background Check
                    </Link>
                    <Link
                        :href="route('provider.profile.index')"
                        class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                    >
                        <EyeIcon class="h-5 w-5" />
                        View Profile
                    </Link>
                </div>
                </div>
            </section>

            <!-- Stats Grid -->
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article
                    v-for="card in statCards"
                    :key="card.title"
                    class="rounded-[1.25rem] border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="inline-flex h-10 w-10 items-center justify-center rounded-xl" :class="accentMap[card.accent].box">
                            <component :is="card.icon" class="h-5 w-5" />
                        </div>
                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold" :class="accentMap[card.accent].chip">
                            <component v-if="card.trend !== undefined" :is="getTrendIcon(card.trend)" :class="['h-3.5 w-3.5', getTrendColor(card.trend)]" />
                            {{ card.chip }}
                        </span>
                    </div>
                    <div class="mt-5">
                        <p class="text-sm font-medium text-slate-500">{{ card.title }}</p>
                        <p class="mt-1.5 text-2xl font-semibold tracking-tight text-slate-900">{{ card.value }}</p>
                        <p class="mt-2 text-sm leading-6 text-slate-500">{{ card.sublabel }}</p>
                    </div>
                </article>
            </section>

            <section class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
                <!-- Recent Leads -->
                <div class="overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-slate-100 p-6">
                        <h2 class="text-xl font-semibold text-slate-900">Recent Leads</h2>
                        <Link :href="route('provider.leads.index')" class="inline-flex items-center gap-1 text-sm font-semibold text-blue-600 hover:text-blue-700">
                            View all
                            <ArrowRightIcon class="w-4 h-4" />
                        </Link>
                    </div>

                    <div v-if="recentLeads?.length" class="divide-y divide-slate-100">
                        <Link 
                            v-for="lead in recentLeads" 
                            :key="lead.uuid"
                            :href="route('provider.leads.show', lead.uuid)"
                            class="flex items-center gap-4 p-4 transition-colors hover:bg-slate-50"
                        >
                            <img
                                v-if="hasLeadAvatar(lead)"
                                :src="resolveAvatar(lead.user)"
                                :alt="leadFirstName(lead)"
                                class="w-12 h-12 rounded-full object-cover"
                                @error="markLeadAvatarFailed(lead)"
                            />
                            <div
                                v-else
                                class="flex h-12 w-12 items-center justify-center rounded-full bg-primary-100 text-base font-semibold text-primary-700"
                                :title="leadFirstName(lead)"
                            >
                                {{ leadFirstInitial(lead) }}
                            </div>
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
                            <div v-if="lead.conversation?.uuid" class="flex items-center gap-1">
                                <button
                                    type="button"
                                    class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-primary-600"
                                    title="Archive"
                                    @click.prevent.stop="archiveConversation(lead.conversation.uuid)"
                                >
                                    <ArchiveBoxIcon class="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-red-600"
                                    title="Delete"
                                    @click.prevent.stop="deleteConversation(lead.conversation.uuid)"
                                >
                                    <TrashIcon class="h-4 w-4" />
                                </button>
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
                    <div class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="mb-4 text-xl font-semibold text-slate-900">Conversion Rate</h3>
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
                    <div class="overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-sm">
                        <div class="flex items-center justify-between p-4 border-b border-slate-100">
                            <h3 class="text-xl font-semibold text-slate-900">Recent Reviews</h3>
                            <Link :href="route('provider.reviews.index')" class="text-sm font-semibold text-blue-600 hover:text-blue-700">
                                View all
                            </Link>
                        </div>

                        <div v-if="recentReviews?.length" class="divide-y divide-slate-100">
                            <div v-for="review in recentReviews" :key="review.uuid" class="p-4">
                                <div class="flex items-center gap-2 mb-2">
                                    <div class="flex items-center gap-0.5 rounded-full bg-amber-50 px-2.5 py-1 text-amber-700">
                                        <StarSolid v-for="i in review.rating" :key="i" class="h-4 w-4" />
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
                    <div class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="font-semibold mb-4">Quick Actions</h3>
                        <div class="space-y-3">
                            <Link 
                                :href="route('provider.profile.edit')"
                                class="flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 p-3 text-slate-700 transition hover:bg-slate-100"
                            >
                                <span>Edit Profile</span>
                                <ArrowRightIcon class="w-4 h-4" />
                            </Link>
                            <Link 
                                v-if="provider.background_check_status !== 'clear'"
                                :href="route('provider.background-check.index')"
                                class="flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 p-3 text-slate-700 transition hover:bg-slate-100"
                            >
                                <span>Get Verified</span>
                                <ArrowRightIcon class="w-4 h-4" />
                            </Link>
                            <Link 
                                :href="route('provider.analytics.index')"
                                class="flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 p-3 text-slate-700 transition hover:bg-slate-100"
                            >
                                <span>View Analytics</span>
                                <ArrowRightIcon class="w-4 h-4" />
                            </Link>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </ProviderLayout>
</template>
