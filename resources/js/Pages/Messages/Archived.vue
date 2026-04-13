<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ArrowLeftIcon, ArchiveBoxIcon, InboxIcon } from '@heroicons/vue/24/outline';

defineProps({
    conversations: { type: Object, required: true },
});

const formatTime = (date) => {
    if (!date) return '';
    const d = new Date(date);
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
};

const displayName = (conversation) =>
    conversation.service_provider?.business_name
    || conversation.service_provider?.user?.full_name
    || conversation.user?.full_name
    || 'Conversation';

const avatarSrc = (conversation) =>
    conversation.service_provider?.user?.avatar
    || conversation.user?.avatar
    || '/images/default-avatar.png';
</script>

<template>
    <Head title="Archived messages" />

    <AppLayout>
        <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="mb-6 flex items-center gap-3">
                <Link
                    :href="route('messages.index')"
                    class="inline-flex rounded-lg p-2 text-slate-600 hover:bg-slate-100"
                >
                    <ArrowLeftIcon class="h-5 w-5" />
                </Link>
                <div>
                    <h1 class="font-display text-2xl font-bold text-slate-900">Archived</h1>
                    <p class="text-sm text-slate-500">
                        Conversations you moved out of your main inbox
                    </p>
                </div>
            </div>

            <div
                v-if="conversations.data?.length"
                class="divide-y divide-slate-100 rounded-2xl border border-slate-200 bg-white"
            >
                <Link
                    v-for="conversation in conversations.data"
                    :key="conversation.id"
                    :href="route('messages.show', conversation.uuid)"
                    class="flex items-center gap-4 p-4 transition hover:bg-slate-50"
                >
                    <img
                        :src="avatarSrc(conversation)"
                        class="h-10 w-10 shrink-0 rounded-full object-cover"
                        alt=""
                    />
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium text-slate-900">
                            {{ displayName(conversation) }}
                        </p>
                        <p class="truncate text-sm text-slate-500">
                            {{ conversation.latest_message?.body || 'No preview' }}
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <ArchiveBoxIcon class="h-4 w-4 text-slate-400" aria-hidden="true" />
                        <span class="text-xs text-slate-400">{{ formatTime(conversation.last_message_at) }}</span>
                    </div>
                </Link>
            </div>

            <div v-else class="rounded-2xl border border-slate-200 bg-white p-12 text-center">
                <InboxIcon class="mx-auto mb-3 h-12 w-12 text-slate-300" />
                <p class="text-slate-600">No archived conversations</p>
                <p class="mt-2 text-sm text-slate-500">
                    Archive a thread from Messages to find it here.
                </p>
                <Link :href="route('messages.index')" class="btn-primary btn-sm mt-6 inline-flex">
                    Back to messages
                </Link>
            </div>

            <div v-if="conversations.links?.length > 3" class="mt-6 flex justify-center">
                <nav class="flex gap-1">
                    <Link
                        v-for="link in conversations.links"
                        :key="link.label"
                        :href="link.url"
                        class="rounded-lg px-3 py-2 text-sm"
                        :class="link.active ? 'bg-primary-600 text-white' : 'text-slate-600 hover:bg-slate-100'"
                        v-html="link.label"
                    />
                </nav>
            </div>
        </div>
    </AppLayout>
</template>
