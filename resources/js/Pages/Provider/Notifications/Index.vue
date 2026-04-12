<script setup>
import { Head, Link } from '@inertiajs/vue3';
import ProviderLayout from '@/Layouts/ProviderLayout.vue';
import { BellIcon } from '@heroicons/vue/24/outline';

defineProps({
    notifications: {
        type: Object,
        required: true,
    },
    notifications_table_missing: {
        type: Boolean,
        default: false,
    },
});

const formatDate = (iso) => {
    return new Date(iso).toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
};

const summary = (row) => {
    const d = row.data || {};
    if (typeof d.message === 'string') {
        return d.message;
    }
    if (typeof d.title === 'string') {
        return d.title;
    }
    const base = row.type?.split('\\').pop() || 'Notification';
    return base.replace(/([A-Z])/g, ' $1').trim();
};
</script>

<template>
    <Head title="Notifications" />

    <ProviderLayout>
        <div class="mx-auto max-w-3xl space-y-5">
            <div class="flex flex-col gap-3 rounded-xl border border-slate-200/80 bg-white px-4 py-3 shadow-sm sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="font-display text-xl font-bold text-slate-900">Notifications</h1>
                    <p class="mt-0.5 text-sm text-slate-500">In-app alerts for your provider account</p>
                </div>
                <Link
                    :href="route('provider.dashboard')"
                    class="text-sm font-semibold text-primary-600 hover:text-primary-700"
                >
                    ← Dashboard
                </Link>
            </div>

            <div
                v-if="notifications_table_missing"
                class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
            >
                The notifications database table is not installed yet. Run
                <code class="rounded bg-amber-100 px-1 py-0.5 font-mono text-xs">php artisan migrate</code>
                to enable in-app notifications.
            </div>

            <div
                v-if="!notifications_table_missing"
                class="rounded-xl border border-slate-200/80 bg-white shadow-sm"
            >
                <ul v-if="notifications.data?.length" class="divide-y divide-slate-100">
                    <li
                        v-for="row in notifications.data"
                        :key="row.id"
                        class="flex gap-3 px-4 py-3"
                        :class="row.read_at ? 'bg-white' : 'bg-primary-50/50'"
                    >
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600">
                            <BellIcon class="h-5 w-5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-slate-900">{{ summary(row) }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">{{ formatDate(row.created_at) }}</p>
                        </div>
                    </li>
                </ul>
                <div v-else class="flex flex-col items-center justify-center px-4 py-14 text-center">
                    <BellIcon class="mb-3 h-12 w-12 text-slate-300" />
                    <p class="text-sm font-medium text-slate-700">No notifications yet</p>
                    <p class="mt-1 max-w-sm text-sm text-slate-500">
                        When the system sends in-app notifications to your account, they will appear here.
                    </p>
                </div>
            </div>

            <div
                v-if="!notifications_table_missing && (notifications.prev_page_url || notifications.next_page_url)"
                class="flex justify-center gap-3"
            >
                <Link
                    v-if="notifications.prev_page_url"
                    :href="notifications.prev_page_url"
                    class="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50"
                >
                    Previous
                </Link>
                <Link
                    v-if="notifications.next_page_url"
                    :href="notifications.next_page_url"
                    class="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50"
                >
                    Next
                </Link>
            </div>
        </div>
    </ProviderLayout>
</template>
