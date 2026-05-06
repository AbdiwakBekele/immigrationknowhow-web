<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import ProviderLayout from '@/Layouts/ProviderLayout.vue';
import { 
    ChartBarIcon,
    UserGroupIcon,
    StarIcon,
    ClockIcon,
    ArrowTrendingUpIcon,
    ArrowTrendingDownIcon,
    ArrowRightIcon,
    FunnelIcon
} from '@heroicons/vue/24/outline';
import { ref, computed } from 'vue';

const props = defineProps({
    period: { type: String, default: '30' },
    stats: { type: Object, default: () => ({}) },
    trends: { type: Object, default: () => ({}) },
    conversionFunnel: { type: Array, default: () => [] },
    topServices: { type: Array, default: () => [] },
});

const selectedPeriod = ref(props.period);

const cleanQuery = (params) => {
    const q = { ...params };
    Object.keys(q).forEach((key) => {
        if (q[key] === '' || q[key] === null || q[key] === undefined) {
            delete q[key];
        }
    });
    return q;
};

const leadsHref = (params = {}) => route('provider.leads.index', cleanQuery(params));

const reviewsHref = (params = {}) => route('provider.reviews.index', cleanQuery(params));

const funnelStageHref = (stageLabel) => {
    const map = {
        Received: {},
        Contacted: { status: 'contacted' },
        'In Progress': { status: 'in_progress' },
        Converted: { status: 'converted' },
    };
    return leadsHref(map[stageLabel] || {});
};

const changePeriod = (days) => {
    selectedPeriod.value = days;
    router.get(route('provider.analytics.index'), { period: days }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const periodOptions = [
    { value: '7', label: 'Last 7 days' },
    { value: '30', label: 'Last 30 days' },
    { value: '90', label: 'Last 90 days' },
    { value: '365', label: 'Last year' },
];

const formatChange = (change) => {
    if (change === 0) return '0%';
    return (change > 0 ? '+' : '') + change + '%';
};

const statCards = computed(() => [
    {
        id: 'total-leads',
        href: leadsHref({}),
        icon: UserGroupIcon,
        iconWrap: 'bg-blue-100',
        iconClass: 'text-blue-600',
        value: props.stats.leads?.total || 0,
        label: 'Total Leads',
        trend: props.stats.leads?.change ?? 0,
        showTrend: true,
    },
    {
        id: 'conversion-rate',
        href: leadsHref({ status: 'converted' }),
        icon: FunnelIcon,
        iconWrap: 'bg-green-100',
        iconClass: 'text-green-600',
        value: `${props.stats.leads?.conversionRate || 0}%`,
        label: 'Conversion Rate',
        showTrend: false,
    },
    {
        id: 'avg-rating',
        href: reviewsHref({}),
        icon: StarIcon,
        iconWrap: 'bg-yellow-100',
        iconClass: 'text-yellow-600',
        value: props.stats.reviews?.averageRating || 0,
        label: `Average Rating (${props.stats.reviews?.total || 0} reviews)`,
        showTrend: false,
    },
    {
        id: 'response-time',
        href: leadsHref({ status: 'new' }),
        icon: ClockIcon,
        iconWrap: 'bg-purple-100',
        iconClass: 'text-purple-600',
        value: props.stats.avgResponseTime || 'N/A',
        label: 'Avg. Response Time',
        showTrend: false,
    },
]);
</script>

<template>
    <Head title="Analytics" />

    <ProviderLayout>
        <div class="admin-page-container">
            <!-- Header -->
            <section class="admin-hero-card mb-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Performance overview</p>
                    <h1 class="mt-2 admin-title">Analytics</h1>
                    <p class="admin-subtitle">Track your growth, lead trends, and conversion funnel.</p>
                </div>
                <div class="flex gap-2">
                    <button 
                        v-for="opt in periodOptions" 
                        :key="opt.value"
                        @click="changePeriod(opt.value)"
                        class="px-4 py-2 text-sm rounded-lg transition-colors"
                        :class="selectedPeriod === opt.value 
                            ? 'bg-primary-600 text-white' 
                            : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'"
                    >
                        {{ opt.label }}
                    </button>
                </div>
                </div>
            </section>

            <!-- Stats Cards -->
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <Link
                    v-for="card in statCards"
                    :key="card.id"
                    :href="card.href"
                    class="group block rounded-2xl border border-gray-100 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:border-primary-200 hover:shadow-md"
                >
                    <div class="mb-4 flex items-center justify-between">
                        <div class="rounded-lg p-2" :class="card.iconWrap">
                            <component :is="card.icon" class="h-5 w-5" :class="card.iconClass" />
                        </div>
                        <span
                            v-if="card.showTrend"
                            class="flex items-center text-sm font-medium"
                            :class="card.trend >= 0 ? 'text-green-600' : 'text-red-600'"
                        >
                            <ArrowTrendingUpIcon v-if="card.trend >= 0" class="mr-1 h-4 w-4" />
                            <ArrowTrendingDownIcon v-else class="mr-1 h-4 w-4" />
                            {{ formatChange(card.trend) }}
                        </span>
                        <ArrowRightIcon
                            v-else
                            class="h-5 w-5 shrink-0 text-gray-400 transition group-hover:text-primary-600"
                            aria-hidden="true"
                        />
                    </div>
                    <div class="font-display text-3xl font-bold text-gray-900">{{ card.value }}</div>
                    <div class="mt-1 text-sm text-gray-500">{{ card.label }}</div>
                </Link>
            </div>

            <div class="grid lg:grid-cols-2 gap-6">
                <!-- Leads Trend Chart -->
                <Link
                    :href="leadsHref({})"
                    class="block rounded-2xl border border-gray-100 bg-white p-6 shadow-sm transition hover:border-primary-200 hover:shadow-md"
                >
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <h2 class="text-lg font-semibold text-gray-900">Leads Over Time</h2>
                        <span class="inline-flex shrink-0 items-center gap-1 text-xs font-semibold text-primary-600">
                            Open leads
                            <ArrowRightIcon class="h-3.5 w-3.5" aria-hidden="true" />
                        </span>
                    </div>
                    <div v-if="trends.leads?.length" class="h-64">
                        <!-- Simple bar chart representation -->
                        <div class="flex items-end justify-between h-48 gap-1">
                            <div 
                                v-for="(item, index) in trends.leads" 
                                :key="index"
                                class="flex-1 bg-primary-500 rounded-t hover:bg-primary-600 transition-colors relative group"
                                :style="{ height: `${Math.max((item.count / Math.max(...trends.leads.map(i => i.count), 1)) * 100, 5)}%` }"
                            >
                                <div class="absolute -top-8 left-1/2 -translate-x-1/2 bg-gray-900 text-white text-xs px-2 py-1 rounded opacity-0 group-hover:opacity-100 whitespace-nowrap">
                                    {{ item.count }} leads
                                </div>
                            </div>
                        </div>
                        <div class="flex justify-between mt-2 text-xs text-gray-500">
                            <span>{{ trends.leads[0]?.date }}</span>
                            <span>{{ trends.leads[trends.leads.length - 1]?.date }}</span>
                        </div>
                    </div>
                    <div v-else class="flex h-64 items-center justify-center text-gray-400">
                        <div class="text-center">
                            <ChartBarIcon class="mx-auto mb-2 h-12 w-12" />
                            <p>No data for this period</p>
                        </div>
                    </div>
                </Link>

                <!-- Conversion Funnel -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Conversion Funnel</h2>
                    <div class="space-y-2">
                        <Link
                            v-for="(stage, index) in conversionFunnel"
                            :key="stage.stage"
                            :href="funnelStageHref(stage.stage)"
                            class="block rounded-xl p-2 transition hover:bg-gray-50"
                        >
                            <div class="mb-1 flex items-center justify-between gap-3">
                                <span class="text-sm font-medium text-gray-700">{{ stage.stage }}</span>
                                <span class="inline-flex shrink-0 items-center gap-1 text-sm text-gray-500">
                                    {{ stage.count }} ({{ stage.percentage }}%)
                                    <ArrowRightIcon class="h-3.5 w-3.5 text-gray-400" aria-hidden="true" />
                                </span>
                            </div>
                            <div class="h-8 overflow-hidden rounded-lg bg-gray-100">
                                <div 
                                    class="h-full rounded-lg transition-all duration-500"
                                    :class="[
                                        index === 0 ? 'bg-blue-500' : '',
                                        index === 1 ? 'bg-indigo-500' : '',
                                        index === 2 ? 'bg-purple-500' : '',
                                        index === 3 ? 'bg-green-500' : '',
                                    ]"
                                    :style="{ width: `${stage.percentage}%` }"
                                ></div>
                            </div>
                        </Link>
                    </div>
                </div>

                <!-- Top Services -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Most Requested Services</h2>
                    <div v-if="topServices.length" class="space-y-2">
                        <Link
                            v-for="(service, index) in topServices"
                            :key="service.service"
                            :href="leadsHref({ service_type: service.service })"
                            class="flex items-center gap-4 rounded-xl p-2 transition hover:bg-gray-50"
                        >
                            <span class="w-6 text-lg font-bold text-gray-300">{{ index + 1 }}</span>
                            <div class="min-w-0 flex-1">
                                <div class="mb-1 flex items-center justify-between gap-2">
                                    <span class="font-medium text-gray-900">{{ service.label }}</span>
                                    <span class="inline-flex shrink-0 items-center gap-1 text-sm text-gray-500">
                                        {{ service.count }} leads
                                        <ArrowRightIcon class="h-3.5 w-3.5 text-gray-400" aria-hidden="true" />
                                    </span>
                                </div>
                                <div class="h-2 overflow-hidden rounded-full bg-gray-100">
                                    <div 
                                        class="h-full rounded-full bg-primary-500"
                                        :style="{ width: `${(service.count / topServices[0].count) * 100}%` }"
                                    ></div>
                                </div>
                            </div>
                        </Link>
                    </div>
                    <div v-else class="text-center py-8 text-gray-400">
                        <p>No service data for this period</p>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Review Insights</h2>
                    <div class="grid grid-cols-2 gap-4">
                        <Link
                            :href="reviewsHref({ rating: 5 })"
                            class="rounded-xl bg-yellow-50 p-4 text-center transition hover:bg-yellow-100"
                        >
                            <div class="text-3xl font-bold text-yellow-600">{{ stats.reviews?.fiveStars || 0 }}</div>
                            <div class="mt-1 text-sm text-gray-600">5-Star Reviews</div>
                        </Link>
                        <Link
                            :href="reviewsHref({ responded: 'no' })"
                            class="rounded-xl bg-blue-50 p-4 text-center transition hover:bg-blue-100"
                        >
                            <div class="text-3xl font-bold text-blue-600">{{ stats.reviews?.needsResponse || 0 }}</div>
                            <div class="mt-1 text-sm text-gray-600">Awaiting Response</div>
                        </Link>
                        <Link
                            :href="leadsHref({ status: 'converted' })"
                            class="col-span-2 rounded-xl bg-green-50 p-4 text-center transition hover:bg-green-100"
                        >
                            <div class="text-3xl font-bold text-green-600">{{ stats.leads?.converted || 0 }}</div>
                            <div class="mt-1 text-sm text-gray-600">Converted Leads</div>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </ProviderLayout>
</template>
