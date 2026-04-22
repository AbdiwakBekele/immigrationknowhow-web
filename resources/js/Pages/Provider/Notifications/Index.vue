<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProviderLayout from '@/Layouts/ProviderLayout.vue';

const props = defineProps({
    notifications: {
        type: Object,
        required: true,
    },
    notifications_table_missing: {
        type: Boolean,
        default: false,
    },
});

const hasUnread = computed(
    () =>
        !props.notifications_table_missing &&
        (props.notifications.data ?? []).some((r) => !r.read_at),
);

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
    if (d.type === 'new_message' && typeof d.sender_name === 'string') {
        const preview = typeof d.message_preview === 'string' ? d.message_preview : '';
        return preview ? `Message from ${d.sender_name}` : `New message from ${d.sender_name}`;
    }
    if (typeof d.title === 'string') {
        return d.title;
    }
    const base = row.type?.split('\\').pop() || 'Notification';
    return base.replace(/([A-Z])/g, ' $1').trim();
};

const detailLine = (row) => {
    const d = row.data || {};
    if (d.type === 'new_message' && typeof d.message_preview === 'string') {
        return d.message_preview;
    }
    if (typeof d.reason === 'string' && d.reason.trim()) {
        return d.reason;
    }
    return null;
};

const notificationHref = (row) => {
    const d = row.data || {};
    if (d.lead_uuid) {
        return route('provider.leads.show', d.lead_uuid);
    }
    if (d.conversation_uuid) {
        return route('provider.messages.show', d.conversation_uuid);
    }
    if (d.type === 'new_review') {
        return '/provider/portal-reviews';
    }
    if (d.type === 'verification_approved' || d.type === 'verification_rejected') {
        return '/provider/profile';
    }
    return null;
};

const markAsRead = (row) => {
    if (props.notifications_table_missing || row.read_at) {
        return;
    }
    router.post(route('provider.notifications.read', row.id), {}, { preserveScroll: true });
};

const markAllAsRead = () => {
    if (props.notifications_table_missing || !hasUnread.value) {
        return;
    }
    router.post(route('provider.notifications.read-all'), {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="Notifications" />

    <ProviderLayout>
        <div class="admin-page-container">
            <section class="admin-hero-card">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                            Alerts
                        </p>
                        <h1 class="mt-2 admin-title">Notifications</h1>
                        <p class="admin-subtitle">In-app alerts for your provider account.</p>
                    </div>
                    <div class="flex shrink-0 flex-wrap items-center gap-3 sm:pt-0.5">
                        <button
                            v-if="!notifications_table_missing && hasUnread"
                            type="button"
                            class="text-sm font-semibold text-primary-600 hover:text-primary-700"
                            @click="markAllAsRead"
                        >
                            Mark all as read
                        </button>
                    </div>
                </div>
            </section>

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
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-slate-900">{{ summary(row) }}</p>
                            <p v-if="detailLine(row)" class="mt-0.5 line-clamp-2 text-xs text-slate-600">
                                {{ detailLine(row) }}
                            </p>
                            <p class="mt-0.5 text-xs text-slate-500">{{ formatDate(row.created_at) }}</p>
                        </div>
                        <div class="flex shrink-0 flex-col items-end justify-center gap-2">
                            <button
                                v-if="!row.read_at"
                                type="button"
                                class="text-xs font-semibold text-primary-600 hover:text-primary-700"
                                @click="markAsRead(row)"
                            >
                                Mark read
                            </button>
                            <Link
                                v-if="notificationHref(row)"
                                :href="notificationHref(row)"
                                class="text-xs font-semibold text-slate-700 underline decoration-slate-300 underline-offset-2 hover:text-primary-600"
                                @click="markAsRead(row)"
                            >
                                View
                            </Link>
                        </div>
                    </li>
                </ul>
                <div v-else class="flex flex-col items-center justify-center px-4 py-14 text-center">
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
