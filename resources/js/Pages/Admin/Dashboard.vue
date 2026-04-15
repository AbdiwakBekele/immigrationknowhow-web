<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import {
    UsersIcon,
    UserGroupIcon,
    ShieldCheckIcon,
    BookOpenIcon,
    ChatBubbleLeftRightIcon,
    ArrowTrendingUpIcon,
    CheckCircleIcon,
    ExclamationTriangleIcon,
    PlusIcon,
    EyeIcon,
    StarIcon,
    CurrencyDollarIcon,
    ClockIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    userStats: { type: Object, default: () => ({}) },
    providerStats: { type: Object, default: () => ({}) },
    backgroundCheckStats: { type: Object, default: () => ({}) },
    leadStats: { type: Object, default: () => ({}) },
    reviewStats: { type: Object, default: () => ({}) },
    libraryStats: { type: Object, default: () => ({}) },
    recentUsers: { type: Array, default: () => [] },
    pendingBackgroundChecks: { type: Array, default: () => [] },
    recentReviews: { type: Array, default: () => [] },
    recentLeads: { type: Array, default: () => [] },
    leadsChartData: { type: Array, default: () => [] },
    serviceTypeDistribution: { type: Array, default: () => [] },
});

const formatDateTime = (value) => {
    if (!value) return '—';

    return new Date(value).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
};

const formatMoney = (value) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: 0,
    }).format(Number(value || 0));
};

const getInitial = (user) => {
    const firstName = (user?.first_name || '').trim();
    const lastName = (user?.last_name || '').trim();

    if (firstName || lastName) {
        return `${firstName.charAt(0)}${lastName.charAt(0)}`.trim().toUpperCase() || '?';
    }

    const fullName = (user?.full_name || '').trim();
    return fullName ? fullName.charAt(0).toUpperCase() : '?';
};

const statCards = computed(() => [
    {
        title: 'Total Users',
        value: props.userStats.total || 0,
        sublabel: `${props.userStats.this_month || 0} new this month`,
        accent: 'blue',
        icon: UsersIcon,
        chip: `${props.userStats.today || 0} today`,
    },
    {
        title: 'Service Providers',
        value: props.providerStats.total || 0,
        sublabel: `${props.providerStats.verified || 0} verified`,
        accent: 'emerald',
        icon: UserGroupIcon,
        chip: `${props.providerStats.today || 0} new today`,
    },
    {
        title: 'Pending Verifications',
        value: props.backgroundCheckStats.pending || 0,
        sublabel: `${props.backgroundCheckStats.needs_review || 0} need review`,
        accent: 'amber',
        icon: ShieldCheckIcon,
        chip: (props.backgroundCheckStats.pending || 0) > 0 ? 'Action needed' : 'Up to date',
    },
    {
        title: 'Total Leads',
        value: props.leadStats.total || 0,
        sublabel: `${props.leadStats.conversion_rate || 0}% conversion rate`,
        accent: 'violet',
        icon: ChatBubbleLeftRightIcon,
        chip: `${props.leadStats.today || 0} today`,
    },
    {
        title: 'New Providers Today',
        value: props.providerStats.today || 0,
        sublabel: 'Created in the last 24 hours',
        accent: 'cyan',
        icon: PlusIcon,
        chip: 'Daily',
    },
    {
        title: 'New Users Today',
        value: props.userStats.today || 0,
        sublabel: 'Created in the last 24 hours',
        accent: 'sky',
        icon: UsersIcon,
        chip: 'Daily',
    },
    {
        title: 'Sold eBooks Today',
        value: props.libraryStats.ebooks_sold_today || 0,
        sublabel: `${formatMoney(props.libraryStats.ebook_revenue_today || 0)} revenue`,
        accent: 'indigo',
        icon: BookOpenIcon,
        chip: 'Sales',
    },
    {
        title: 'Active Providers Today',
        value: props.providerStats.active_today || 0,
        sublabel: `${props.providerStats.active || 0} active & accepting overall`,
        accent: 'teal',
        icon: ClockIcon,
        chip: 'Logged in today',
    },
]);

const accentMap = {
    blue: {
        box: 'bg-blue-50 text-blue-700',
        chip: 'bg-blue-100 text-blue-700',
    },
    emerald: {
        box: 'bg-emerald-50 text-emerald-700',
        chip: 'bg-emerald-100 text-emerald-700',
    },
    amber: {
        box: 'bg-amber-50 text-amber-700',
        chip: 'bg-amber-100 text-amber-700',
    },
    violet: {
        box: 'bg-violet-50 text-violet-700',
        chip: 'bg-violet-100 text-violet-700',
    },
    cyan: {
        box: 'bg-cyan-50 text-cyan-700',
        chip: 'bg-cyan-100 text-cyan-700',
    },
    sky: {
        box: 'bg-sky-50 text-sky-700',
        chip: 'bg-sky-100 text-sky-700',
    },
    indigo: {
        box: 'bg-indigo-50 text-indigo-700',
        chip: 'bg-indigo-100 text-indigo-700',
    },
    teal: {
        box: 'bg-teal-50 text-teal-700',
        chip: 'bg-teal-100 text-teal-700',
    },
};
</script>

<template>
    <Head title="Admin Dashboard" />

    <AdminLayout>
        <div class="admin-page-container">
            <section class="admin-hero-card">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                            Platform overview
                        </p>
                        <h1 class="mt-2 admin-title">
                            Dashboard
                        </h1>
                        <p class="admin-subtitle">
                            Track platform growth, provider activity, verifications, sales, and recent user activity from one place.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <span class="inline-flex rounded-full bg-blue-50 px-3 py-1.5 text-sm font-medium text-blue-700">
                            Live overview
                        </span>

                        <Link
                            href="/admin/reports"
                            class="inline-flex items-center rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        >
                            View reports
                        </Link>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article
                    v-for="card in statCards"
                    :key="card.title"
                    class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div
                            class="inline-flex h-12 w-12 items-center justify-center rounded-2xl"
                            :class="accentMap[card.accent].box"
                        >
                            <component :is="card.icon" class="h-6 w-6" />
                        </div>

                        <span
                            class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                            :class="accentMap[card.accent].chip"
                        >
                            {{ card.chip }}
                        </span>
                    </div>

                    <div class="mt-5">
                        <p class="text-sm font-medium text-slate-500">
                            {{ card.title }}
                        </p>
                        <p class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">
                            {{ card.value }}
                        </p>
                        <p class="mt-2 text-sm leading-6 text-slate-500">
                            {{ card.sublabel }}
                        </p>
                    </div>
                </article>
            </section>

            <section class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
                <div class="space-y-6">
                    <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                        <div class="mb-5 flex items-center justify-between">
                            <div>
                                <h2 class="text-xl font-semibold text-slate-900">Pending Verifications</h2>
                                <p class="mt-1 text-sm text-slate-500">
                                    Providers waiting on review or background check progress.
                                </p>
                            </div>
                            <Link
                                href="/admin/background-checks"
                                class="text-sm font-semibold text-blue-600 hover:text-blue-700"
                            >
                                View all →
                            </Link>
                        </div>

                        <div v-if="pendingBackgroundChecks.length" class="space-y-3">
                            <Link
                                v-for="verification in pendingBackgroundChecks"
                                :key="verification.id"
                                :href="`/admin/background-checks/${verification.uuid}`"
                                class="flex items-center gap-4 rounded-2xl border border-slate-200 p-4 transition hover:border-blue-200 hover:bg-slate-50"
                            >
                                <img
                                    :src="verification.service_provider?.user?.avatar || '/images/default-avatar.png'"
                                    alt=""
                                    class="h-12 w-12 rounded-2xl object-cover"
                                />

                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-slate-900">
                                        {{ verification.service_provider?.business_name || 'Service Provider' }}
                                    </p>
                                    <p class="mt-1 text-sm text-slate-500">
                                        {{ verification.document_type || 'Background check' }} · {{ formatDateTime(verification.created_at) }}
                                    </p>
                                </div>

                                <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">
                                    {{ verification.status }}
                                </span>
                            </Link>
                        </div>

                        <div v-else class="rounded-2xl border border-dashed border-slate-200 px-6 py-10 text-center">
                            <CheckCircleIcon class="mx-auto h-10 w-10 text-emerald-400" />
                            <p class="mt-3 text-sm font-medium text-slate-700">All caught up</p>
                            <p class="mt-1 text-sm text-slate-500">There are no pending verifications right now.</p>
                        </div>
                    </div>

                    <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                        <div class="mb-5 flex items-center justify-between">
                            <div>
                                <h2 class="text-xl font-semibold text-slate-900">Recent Reviews</h2>
                                <p class="mt-1 text-sm text-slate-500">
                                    Latest feedback submitted on the platform.
                                </p>
                            </div>
                            <Link
                                href="/admin/reviews"
                                class="text-sm font-semibold text-blue-600 hover:text-blue-700"
                            >
                                View all →
                            </Link>
                        </div>

                        <div v-if="recentReviews.length" class="space-y-3">
                            <div
                                v-for="review in recentReviews"
                                :key="review.id"
                                class="rounded-2xl border border-slate-200 p-4"
                            >
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900">
                                            {{ review.service_provider?.business_name || 'Provider' }}
                                        </p>
                                        <p class="mt-1 text-sm text-slate-500">
                                            by {{ review.user?.first_name }} {{ review.user?.last_name }}
                                        </p>
                                    </div>

                                    <div class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-3 py-1 text-sm font-semibold text-amber-700">
                                        <StarIcon class="h-4 w-4" />
                                        {{ review.rating ?? '—' }}
                                    </div>
                                </div>

                                <p class="mt-3 line-clamp-2 text-sm leading-6 text-slate-600">
                                    {{ review.comment || 'No comment provided.' }}
                                </p>
                            </div>
                        </div>

                        <div v-else class="rounded-2xl border border-dashed border-slate-200 px-6 py-10 text-center text-sm text-slate-500">
                            No recent reviews yet.
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                        <div class="mb-5 flex items-center justify-between">
                            <div>
                                <h2 class="text-xl font-semibold text-slate-900">Recent Users</h2>
                                <p class="mt-1 text-sm text-slate-500">
                                    Newest accounts added to the platform.
                                </p>
                            </div>
                            <div class="flex items-center gap-3">
                                <Link
                                    href="/admin/users/create"
                                    class="inline-flex items-center gap-1 rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                                >
                                    <PlusIcon class="h-4 w-4" />
                                    Add User
                                </Link>
                                <Link
                                    href="/admin/users"
                                    class="text-sm font-semibold text-blue-600 hover:text-blue-700"
                                >
                                    View all →
                                </Link>
                            </div>
                        </div>

                        <div v-if="recentUsers.length" class="space-y-3">
                            <Link
                                v-for="user in recentUsers"
                                :key="user.id"
                                :href="`/admin/users/${user.id}`"
                                class="flex items-center gap-4 rounded-2xl border border-slate-200 p-4 transition hover:border-blue-200 hover:bg-slate-50"
                            >
                                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50 text-sm font-semibold text-blue-700">
                                    {{ getInitial(user) }}
                                </div>

                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-slate-900">
                                        {{ user.full_name }}
                                    </p>
                                    <p class="truncate text-sm text-slate-500">
                                        {{ user.email }}
                                    </p>
                                </div>

                                <span class="text-xs font-medium text-slate-500">
                                    {{ formatDateTime(user.created_at) }}
                                </span>
                            </Link>
                        </div>

                        <div v-else class="rounded-2xl border border-dashed border-slate-200 px-6 py-10 text-center text-sm text-slate-500">
                            No recent users yet.
                        </div>
                    </div>

                    <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                        <div class="mb-5 flex items-center justify-between">
                            <div>
                                <h2 class="text-xl font-semibold text-slate-900">Quick Overview</h2>
                                <p class="mt-1 text-sm text-slate-500">
                                    A few extra health indicators for the platform.
                                </p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="flex items-center justify-between rounded-2xl bg-slate-50 px-4 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700">
                                        <ArrowTrendingUpIcon class="h-5 w-5" />
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900">Lead conversion rate</p>
                                        <p class="text-sm text-slate-500">Converted leads vs total leads</p>
                                    </div>
                                </div>
                                <p class="text-lg font-semibold text-slate-900">
                                    {{ leadStats.conversion_rate || 0 }}%
                                </p>
                            </div>

                            <div class="flex items-center justify-between rounded-2xl bg-slate-50 px-4 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-amber-50 text-amber-700">
                                        <StarIcon class="h-5 w-5" />
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900">Average review rating</p>
                                        <p class="text-sm text-slate-500">Across approved platform reviews</p>
                                    </div>
                                </div>
                                <p class="text-lg font-semibold text-slate-900">
                                    {{ reviewStats.average_rating || 0 }}
                                </p>
                            </div>

                            <div class="flex items-center justify-between rounded-2xl bg-slate-50 px-4 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-700">
                                        <CurrencyDollarIcon class="h-5 w-5" />
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900">eBook revenue today</p>
                                        <p class="text-sm text-slate-500">From completed eBook purchases</p>
                                    </div>
                                </div>
                                <p class="text-lg font-semibold text-slate-900">
                                    {{ formatMoney(libraryStats.ebook_revenue_today || 0) }}
                                </p>
                            </div>

                            <div class="flex items-center justify-between rounded-2xl bg-slate-50 px-4 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-rose-50 text-rose-700">
                                        <ExclamationTriangleIcon class="h-5 w-5" />
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900">Pending moderation</p>
                                        <p class="text-sm text-slate-500">Reviews awaiting approval</p>
                                    </div>
                                </div>
                                <p class="text-lg font-semibold text-slate-900">
                                    {{ reviewStats.pending_moderation || 0 }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>