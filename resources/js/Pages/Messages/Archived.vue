<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ArrowPathIcon, ArrowLeftIcon, InboxIcon, TrashIcon } from '@heroicons/vue/24/outline';

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

const conversationPerson = (conversation) =>
    conversation.service_provider?.user || conversation.user || null;

const avatarSrc = (conversation) => {
    const person = conversationPerson(conversation);
    if (!person) return null;

    const fromUrl = String(person.avatar_url ?? '').trim();
    if (fromUrl) return fromUrl;

    const raw = String(person.avatar ?? '').trim();
    if (!raw) return null;
    if (raw.startsWith('http://') || raw.startsWith('https://') || raw.startsWith('/')) {
        return raw;
    }

    return `/storage/${raw}`;
};

const avatarInitial = (conversation) => {
    const person = conversationPerson(conversation);
    const firstName = (person?.first_name || '').trim();
    if (firstName) return firstName.charAt(0).toUpperCase();

    const fullName = (person?.full_name || '').trim();
    if (fullName) return fullName.charAt(0).toUpperCase();

    return '?';
};

const recoverConversation = (uuid) => {
    router.post(route('messages.unarchive', uuid), {}, {
        preserveScroll: true,
    });
};

const deleteConversation = (uuid) => {
    if (!window.confirm('Delete this conversation permanently?')) {
        return;
    }

    router.delete(route('messages.destroy', uuid), {
        preserveScroll: true,
    });
};

const conversationHref = (uuid) => (uuid ? route('messages.show', uuid) : route('messages.index'));
</script>

<template>
    <Head title="Archived messages" />

    <AppLayout>
        <div class="mx-auto max-w-5xl space-y-6 pb-10">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div class="flex items-center gap-3">
                        <Link
                            :href="route('messages.index')"
                            class="inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50"
                        >
                            <ArrowLeftIcon class="h-5 w-5" />
                        </Link>
                        <div>
                            <h1 class="text-2xl font-semibold text-slate-900">Archived messages</h1>
                            <p class="mt-1 text-sm text-slate-500">
                                Conversations you moved out of your main inbox
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <div
                v-if="conversations.data?.length"
                class="divide-y divide-slate-100 rounded-2xl border border-slate-200 bg-white shadow-sm"
            >
                <Link
                    v-for="conversation in conversations.data"
                    :key="conversation.id"
                    :href="conversationHref(conversation.uuid)"
                    class="flex items-center gap-4 p-4 transition hover:bg-slate-50"
                >
                    <img
                        v-if="avatarSrc(conversation)"
                        :src="avatarSrc(conversation)"
                        class="h-10 w-10 shrink-0 rounded-full object-cover ring-1 ring-slate-200"
                        alt=""
                    />
                    <div
                        v-else
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-200 text-sm font-semibold text-slate-700 ring-1 ring-slate-200"
                    >
                        {{ avatarInitial(conversation) }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium text-slate-900">
                            {{ displayName(conversation) }}
                        </p>
                        <p class="truncate text-sm text-slate-500">
                            {{ conversation.latest_message?.body || 'No preview' }}
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <button
                            type="button"
                            class="rounded-lg p-1.5 text-slate-500 transition hover:bg-slate-100 hover:text-primary-600"
                            title="Recover"
                            @click.prevent="recoverConversation(conversation.uuid)"
                        >
                            <ArrowPathIcon class="h-4 w-4" />
                        </button>
                        <button
                            type="button"
                            class="rounded-lg p-1.5 text-slate-500 transition hover:bg-slate-100 hover:text-red-600"
                            title="Delete"
                            @click.prevent="deleteConversation(conversation.uuid)"
                        >
                            <TrashIcon class="h-4 w-4" />
                        </button>
                        <span class="text-xs text-slate-400">{{ formatTime(conversation.last_message_at) }}</span>
                    </div>
                </Link>
            </div>

            <div v-else class="rounded-2xl border border-slate-200 bg-white p-12 text-center shadow-sm">
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
                        :href="link.url || '#'"
                        class="rounded-lg px-3 py-2 text-sm"
                        :class="link.active ? 'bg-primary-600 text-white' : 'text-slate-600 hover:bg-slate-100'"
                        v-html="link.label"
                    />
                </nav>
            </div>
        </div>
    </AppLayout>
</template>
