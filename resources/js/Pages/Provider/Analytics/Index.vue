<script setup>
import { Head, router } from '@inertiajs/vue3';
import ProviderLayout from '@/Layouts/ProviderLayout.vue';
import { 
    ChartBarIcon,
    UserGroupIcon,
    StarIcon,
    ClockIcon,
    ArrowTrendingUpIcon,
    ArrowTrendingDownIcon,
    EyeIcon,
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

const changePeriod = (days) => {
    selectedPeriod.value = days;
    router.get('/provider/analytics', { period: days }, {
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
                <!-- Leads -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-2 bg-blue-100 rounded-lg">
                            <UserGroupIcon class="h-5 w-5 text-blue-600" />
                        </div>
                        <span 
                            class="flex items-center text-sm font-medium"
                            :class="stats.leads?.change >= 0 ? 'text-green-600' : 'text-red-600'"
                        >
                            <ArrowTrendingUpIcon v-if="stats.leads?.change >= 0" class="h-4 w-4 mr-1" />
                            <ArrowTrendingDownIcon v-else class="h-4 w-4 mr-1" />
                            {{ formatChange(stats.leads?.change || 0) }}
                        </span>
                    </div>
                    <div class="text-3xl font-display font-bold text-gray-900">{{ stats.leads?.total || 0 }}</div>
                    <div class="text-sm text-gray-500 mt-1">Total Leads</div>
                </div>

                <!-- Conversion Rate -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-2 bg-green-100 rounded-lg">
                            <FunnelIcon class="h-5 w-5 text-green-600" />
                        </div>
                    </div>
                    <div class="text-3xl font-display font-bold text-gray-900">{{ stats.leads?.conversionRate || 0 }}%</div>
                    <div class="text-sm text-gray-500 mt-1">Conversion Rate</div>
                </div>

                <!-- Reviews -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-2 bg-yellow-100 rounded-lg">
                            <StarIcon class="h-5 w-5 text-yellow-600" />
                        </div>
                    </div>
                    <div class="text-3xl font-display font-bold text-gray-900">{{ stats.reviews?.averageRating || 0 }}</div>
                    <div class="text-sm text-gray-500 mt-1">Average Rating ({{ stats.reviews?.total || 0 }} reviews)</div>
                </div>

                <!-- Response Time -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-2 bg-purple-100 rounded-lg">
                            <ClockIcon class="h-5 w-5 text-purple-600" />
                        </div>
                    </div>
                    <div class="text-3xl font-display font-bold text-gray-900">{{ stats.avgResponseTime || 'N/A' }}</div>
                    <div class="text-sm text-gray-500 mt-1">Avg. Response Time</div>
                </div>
            </div>

            <div class="grid lg:grid-cols-2 gap-6">
                <!-- Leads Trend Chart -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Leads Over Time</h2>
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
                    <div v-else class="h-64 flex items-center justify-center text-gray-400">
                        <div class="text-center">
                            <ChartBarIcon class="h-12 w-12 mx-auto mb-2" />
                            <p>No data for this period</p>
                        </div>
                    </div>
                </div>

                <!-- Conversion Funnel -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Conversion Funnel</h2>
                    <div class="space-y-4">
                        <div 
                            v-for="(stage, index) in conversionFunnel" 
                            :key="stage.stage"
                            class="relative"
                        >
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-sm font-medium text-gray-700">{{ stage.stage }}</span>
                                <span class="text-sm text-gray-500">{{ stage.count }} ({{ stage.percentage }}%)</span>
                            </div>
                            <div class="h-8 bg-gray-100 rounded-lg overflow-hidden">
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
                        </div>
                    </div>
                </div>

                <!-- Top Services -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Most Requested Services</h2>
                    <div v-if="topServices.length" class="space-y-3">
                        <div 
                            v-for="(service, index) in topServices" 
                            :key="service.service"
                            class="flex items-center gap-4"
                        >
                            <span class="text-lg font-bold text-gray-300 w-6">{{ index + 1 }}</span>
                            <div class="flex-1">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="font-medium text-gray-900">{{ service.label }}</span>
                                    <span class="text-sm text-gray-500">{{ service.count }} leads</span>
                                </div>
                                <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                                    <div 
                                        class="h-full bg-primary-500 rounded-full"
                                        :style="{ width: `${(service.count / topServices[0].count) * 100}%` }"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-8 text-gray-400">
                        <p>No service data for this period</p>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Review Insights</h2>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="text-center p-4 bg-yellow-50 rounded-xl">
                            <div class="text-3xl font-bold text-yellow-600">{{ stats.reviews?.fiveStars || 0 }}</div>
                            <div class="text-sm text-gray-600 mt-1">5-Star Reviews</div>
                        </div>
                        <div class="text-center p-4 bg-blue-50 rounded-xl">
                            <div class="text-3xl font-bold text-blue-600">{{ stats.reviews?.needsResponse || 0 }}</div>
                            <div class="text-sm text-gray-600 mt-1">Awaiting Response</div>
                        </div>
                        <div class="text-center p-4 bg-green-50 rounded-xl col-span-2">
                            <div class="text-3xl font-bold text-green-600">{{ stats.leads?.converted || 0 }}</div>
                            <div class="text-sm text-gray-600 mt-1">Converted Leads</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </ProviderLayout>
</template>
