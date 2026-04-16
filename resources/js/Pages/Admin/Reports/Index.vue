<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    BarElement,
    ArcElement,
    Title,
    Tooltip,
    Legend,
    Filler,
} from 'chart.js';
import { Line, Bar, Doughnut } from 'vue-chartjs';
import {
    ChartBarIcon,
    ChatBubbleLeftRightIcon,
    ShieldCheckIcon,
    UserGroupIcon,
    UsersIcon,
} from '@heroicons/vue/24/outline';

ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    BarElement,
    ArcElement,
    Title,
    Tooltip,
    Legend,
    Filler,
);

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({}),
    },
    charts: {
        type: Object,
        default: () => ({
            months: { labels: [], users: [], providers: [], leads: [], reviews: [] },
            lead_status: { labels: [], values: [] },
            review_ratings: { labels: [], values: [] },
        }),
    },
});

const chartFont = {
    family: 'ui-sans-serif, system-ui, sans-serif',
    size: 11,
};

const lineChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: {
        legend: {
            position: 'top',
            align: 'end',
            labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, font: chartFont },
        },
        tooltip: {
            backgroundColor: 'rgba(15, 23, 42, 0.92)',
            titleFont: chartFont,
            bodyFont: chartFont,
            padding: 10,
            cornerRadius: 8,
        },
    },
    scales: {
        x: {
            grid: { display: false },
            ticks: { font: chartFont, color: '#64748b', maxRotation: 0 },
        },
        y: {
            beginAtZero: true,
            grid: { color: 'rgba(148, 163, 184, 0.18)' },
            ticks: { font: chartFont, color: '#64748b', precision: 0 },
        },
    },
};

const barChartOptions = {
    ...lineChartOptions,
    plugins: {
        ...lineChartOptions.plugins,
        legend: {
            position: 'top',
            align: 'end',
            labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, font: chartFont },
        },
    },
};

const doughnutOptions = {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '62%',
    plugins: {
        legend: {
            position: 'right',
            labels: { boxWidth: 10, usePointStyle: true, font: chartFont },
        },
        tooltip: {
            backgroundColor: 'rgba(15, 23, 42, 0.92)',
            bodyFont: chartFont,
            padding: 10,
            cornerRadius: 8,
        },
    },
};

const ratingBarOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            backgroundColor: 'rgba(15, 23, 42, 0.92)',
            bodyFont: chartFont,
            padding: 10,
            cornerRadius: 8,
        },
    },
    scales: {
        x: {
            grid: { display: false },
            ticks: { font: chartFont, color: '#64748b' },
        },
        y: {
            beginAtZero: true,
            grid: { color: 'rgba(148, 163, 184, 0.18)' },
            ticks: { font: chartFont, color: '#64748b', precision: 0 },
        },
    },
};

const signupsChartData = computed(() => ({
    labels: props.charts.months?.labels ?? [],
    datasets: [
        {
            label: 'New users',
            data: props.charts.months?.users ?? [],
            borderColor: 'rgb(2, 132, 199)',
            backgroundColor: 'rgba(2, 132, 199, 0.12)',
            fill: true,
            tension: 0.35,
            pointRadius: 3,
            pointHoverRadius: 5,
        },
        {
            label: 'New providers',
            data: props.charts.months?.providers ?? [],
            borderColor: 'rgb(139, 92, 246)',
            backgroundColor: 'rgba(139, 92, 246, 0.08)',
            fill: true,
            tension: 0.35,
            pointRadius: 3,
            pointHoverRadius: 5,
        },
    ],
}));

const engagementChartData = computed(() => ({
    labels: props.charts.months?.labels ?? [],
    datasets: [
        {
            label: 'Leads',
            data: props.charts.months?.leads ?? [],
            backgroundColor: 'rgba(16, 185, 129, 0.65)',
            borderRadius: 6,
            borderSkipped: false,
        },
        {
            label: 'Reviews',
            data: props.charts.months?.reviews ?? [],
            backgroundColor: 'rgba(245, 158, 11, 0.7)',
            borderRadius: 6,
            borderSkipped: false,
        },
    ],
}));

const leadStatusColors = [
    'rgb(59, 130, 246)',
    'rgb(99, 102, 241)',
    'rgb(234, 179, 8)',
    'rgb(16, 185, 129)',
    'rgb(100, 116, 139)',
    'rgb(239, 68, 68)',
];

const leadStatusChartData = computed(() => ({
    labels: props.charts.lead_status?.labels ?? [],
    datasets: [
        {
            data: props.charts.lead_status?.values ?? [],
            backgroundColor: leadStatusColors,
            borderWidth: 0,
        },
    ],
}));

const ratingsChartData = computed(() => ({
    labels: props.charts.review_ratings?.labels ?? [],
    datasets: [
        {
            label: 'Reviews',
            data: props.charts.review_ratings?.values ?? [],
            backgroundColor: 'rgba(14, 165, 233, 0.75)',
            borderRadius: 6,
            borderSkipped: false,
        },
    ],
}));

const kpiCardClass =
    'block rounded-[1.1rem] border border-slate-200/80 bg-white p-3 shadow-sm ring-1 ring-slate-900/5 transition duration-200 hover:border-sky-300/60 hover:shadow-md hover:ring-sky-900/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 focus-visible:ring-offset-2';
</script>

<template>
    <Head title="Reports" />

    <AdminLayout>
        <div class="admin-page-container">
            <section class="admin-hero-card">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                            Platform analytics
                        </p>
                        <h1 class="mt-2 admin-title">Reports</h1>
                        <p class="admin-subtitle">
                        Live metrics from your database — signups, providers, leads, and reviews over the last twelve months.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Link
                            href="/admin/users"
                            class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
                        >
                            Users
                        </Link>
                        <Link
                            href="/admin/providers"
                            class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
                        >
                            Providers
                        </Link>
                        <Link
                            href="/admin/reviews"
                            class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
                        >
                            Reviews
                        </Link>
                    </div>
                </div>
            </section>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <Link href="/admin/users" :class="kpiCardClass" title="Open users">
                    <div class="mb-2 inline-flex rounded-lg bg-sky-100 p-1.5">
                        <UsersIcon class="h-5 w-5 text-sky-700" />
                    </div>
                    <p class="text-xl font-bold tabular-nums text-slate-900">{{ stats.users ?? 0 }}</p>
                    <p class="text-xs font-medium text-slate-500">Total users</p>
                </Link>

                <Link href="/admin/providers" :class="kpiCardClass" title="Open providers">
                    <div class="mb-2 inline-flex rounded-lg bg-violet-100 p-1.5">
                        <UserGroupIcon class="h-5 w-5 text-violet-700" />
                    </div>
                    <p class="text-xl font-bold tabular-nums text-slate-900">{{ stats.providers ?? 0 }}</p>
                    <p class="text-xs font-medium text-slate-500">Service providers</p>
                </Link>

                <Link
                    href="/admin/reports#report-engagement"
                    :class="kpiCardClass"
                    title="View leads on this report"
                >
                    <div class="mb-2 inline-flex rounded-lg bg-emerald-100 p-1.5">
                        <ChartBarIcon class="h-5 w-5 text-emerald-700" />
                    </div>
                    <p class="text-xl font-bold tabular-nums text-slate-900">{{ stats.leads ?? 0 }}</p>
                    <p class="text-xs font-medium text-slate-500">Total leads</p>
                </Link>

                <Link href="/admin/reviews" :class="kpiCardClass" title="Open reviews">
                    <div class="mb-2 inline-flex rounded-lg bg-amber-100 p-1.5">
                        <ChatBubbleLeftRightIcon class="h-5 w-5 text-amber-700" />
                    </div>
                    <p class="text-xl font-bold tabular-nums text-slate-900">{{ stats.reviews ?? 0 }}</p>
                    <p class="text-xs font-medium text-slate-500">Reviews</p>
                </Link>
            </div>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
                    <div class="mb-1 flex items-start justify-between gap-2">
                        <div>
                            <h2 class="text-sm font-semibold text-slate-900">Signups over time</h2>
                            <p class="text-xs text-slate-500">Users and providers created per month</p>
                        </div>
                    </div>
                    <div class="mt-4 h-72">
                        <Line :data="signupsChartData" :options="lineChartOptions" />
                    </div>
                </div>

                <div
                    id="report-engagement"
                    class="scroll-mt-24 rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm ring-1 ring-slate-900/5"
                >
                    <div class="mb-1">
                        <h2 class="text-sm font-semibold text-slate-900">Engagement</h2>
                        <p class="text-xs text-slate-500">Leads and reviews created per month</p>
                    </div>
                    <div class="mt-4 h-72">
                        <Bar :data="engagementChartData" :options="barChartOptions" />
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
                    <div class="mb-1 flex items-center gap-2">
                        <ShieldCheckIcon class="h-4 w-4 text-slate-500" />
                        <div>
                            <h2 class="text-sm font-semibold text-slate-900">Leads by status</h2>
                            <p class="text-xs text-slate-500">Pipeline distribution (all time)</p>
                        </div>
                    </div>
                    <div class="mt-2 h-72">
                        <Doughnut :data="leadStatusChartData" :options="doughnutOptions" />
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
                    <div class="mb-1">
                        <h2 class="text-sm font-semibold text-slate-900">Review ratings</h2>
                        <p class="text-xs text-slate-500">Count of reviews by star rating</p>
                    </div>
                    <div class="mt-4 h-72">
                        <Bar :data="ratingsChartData" :options="ratingBarOptions" />
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
