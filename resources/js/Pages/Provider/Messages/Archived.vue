<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import ProviderLayout from '@/Layouts/ProviderLayout.vue';
import { ArrowLeftIcon, ArrowPathIcon, InboxIcon, TrashIcon } from '@heroicons/vue/24/outline';

defineProps({
    conversations: { type: Object, required: true },
});

const formatTime = (date) => {
    if (!date) return '';
    const d = new Date(date);
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
};

const recoverConversation = (uuid) => {
    router.post(route('provider.messages.unarchive', uuid), {}, {
        preserveScroll: true,
    });
};

const deleteConversation = (uuid) => {
    if (!window.confirm('Delete this conversation permanently?')) {
        return;
    }

    router.delete(route('provider.messages.destroy', uuid), {
        preserveScroll: true,
    });
};

const getAvatarSrc = (person) => {
    if (!person) {
        return null;
    }
    const fromUrl = String(person.avatar_url ?? '').trim();
    if (fromUrl) {
        return fromUrl;
    }
    const raw = String(person.avatar ?? '').trim();
    if (!raw) {
        return null;
    }
    if (raw.startsWith('http://') || raw.startsWith('https://') || raw.startsWith('/')) {
        return raw;
    }
    return `/storage/${raw}`;
};

const getAvatarInitial = (person) => {
    const first = (person?.first_name || '').trim();
    if (first) {
        return first.charAt(0).toUpperCase();
    }
    const last = (person?.last_name || '').trim();
    if (last) {
        return last.charAt(0).toUpperCase();
    }
    return '?';
};
</script>

<template>
    <Head title="Archived messages" />

    <ProviderLayout>
        <div class="admin-page-container">
            <section class="admin-hero-card mb-6">
                <div class="flex items-center gap-3">
                    <Link
                        :href="route('provider.messages.index')"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50"
                    >
                        <ArrowLeftIcon class="h-5 w-5" />
                    </Link>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Messaging</p>
                        <h1 class="mt-1 admin-title">Archived</h1>
                        <p class="admin-subtitle">Conversations you archived as a provider.</p>
                    </div>
                </div>
            </section>

            <div v-if="conversations.data?.length" class="bg-white rounded-2xl border border-slate-200 divide-y divide-slate-100">
                <Link
                    v-for="conversation in conversations.data"
                    :key="conversation.id"
                    :href="route('provider.messages.show', conversation.uuid)"
                    class="flex items-center gap-4 p-4 hover:bg-slate-50"
                >
                    <img
                        v-if="getAvatarSrc(conversation.user)"
                        :src="getAvatarSrc(conversation.user)"
                        alt=""
                        class="h-10 w-10 rounded-full object-cover ring-1 ring-slate-200"
                    />
                    <div
                        v-else
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-200 text-sm font-semibold text-slate-700 ring-1 ring-slate-200"
                    >
                        {{ getAvatarInitial(conversation.user) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-slate-900 truncate">{{ conversation.user?.full_name || 'Client' }}</p>
                        <p class="text-sm text-slate-500 truncate">{{ conversation.latest_message?.body || 'No preview' }}</p>
                    </div>
                    <div class="flex items-center gap-2">
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

            <div v-else class="bg-white rounded-2xl border border-slate-200 p-12 text-center">
                <InboxIcon class="h-12 w-12 text-slate-300 mx-auto mb-3" />
                <p class="text-slate-600">No archived conversations</p>
            </div>

            <div v-if="conversations.links?.length > 3" class="mt-6 flex justify-center">
                <nav class="flex gap-1">
                    <Link
                        v-for="link in conversations.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        class="px-3 py-2 text-sm rounded-lg"
                        :class="link.active ? 'bg-primary-600 text-white' : 'text-slate-600 hover:bg-slate-100'"
                        v-html="link.label"
                    />
                </nav>
            </div>
        </div>
    </ProviderLayout>
</template>
