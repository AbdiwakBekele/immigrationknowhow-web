<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ProviderLayout from '@/Layouts/ProviderLayout.vue';
import { 
    ChatBubbleLeftRightIcon,
    ArchiveBoxIcon,
    MagnifyingGlassIcon,
    InboxIcon,
} from '@heroicons/vue/24/outline';
import { ref, computed } from 'vue';

const props = defineProps({
    conversations: { type: Object, required: true },
    totalUnread: { type: Number, default: 0 },
    isProvider: { type: Boolean, default: false },
});

const searchQuery = ref('');
const layoutComponent = computed(() => (props.isProvider ? ProviderLayout : AppLayout));
const archivedHref = computed(() =>
    props.isProvider ? route('provider.messages.archived') : route('messages.archived'),
);

const filteredConversations = computed(() => {
    if (!searchQuery.value) return props.conversations.data;
    const query = searchQuery.value.toLowerCase();
    return props.conversations.data.filter(conv => {
        const otherParty = conv.service_provider?.user?.full_name || conv.user?.full_name || '';
        const businessName = conv.service_provider?.business_name || '';
        return otherParty.toLowerCase().includes(query) || businessName.toLowerCase().includes(query);
    });
});

const formatTime = (date) => {
    if (!date) return '';
    const d = new Date(date);
    const now = new Date();
    const diffDays = Math.floor((now - d) / (1000 * 60 * 60 * 24));
    
    if (diffDays === 0) {
        return d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
    } else if (diffDays === 1) {
        return 'Yesterday';
    } else if (diffDays < 7) {
        return d.toLocaleDateString('en-US', { weekday: 'short' });
    } else {
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }
};

const conversationHref = (conversation) =>
    conversation?.uuid
        ? (props.isProvider
            ? route('provider.messages.show', conversation.uuid)
            : route('messages.show', conversation.uuid))
        : (props.isProvider ? route('provider.messages.index') : route('messages.index'));

const getInitial = (name) => {
    const first = (name || '').trim();
    return first ? first.charAt(0).toUpperCase() : '?';
};

const getConversationPerson = (conversation) =>
    conversation.service_provider?.user || conversation.user || null;

const getAvatarSrc = (conversation) => {
    const person = getConversationPerson(conversation);
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

const getAvatarInitial = (conversation) => {
    const person = getConversationPerson(conversation);
    const firstName = (person?.first_name || '').trim();
    if (firstName) {
        return firstName.charAt(0).toUpperCase();
    }

    return getInitial(person?.full_name || '');
};
</script>

<template>
    <Head title="Messages" />

    <component :is="layoutComponent">
        <div class="h-[calc(100vh-5rem)] overflow-hidden bg-slate-100 p-3 sm:p-4">
            <div class="grid h-full overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm lg:grid-cols-[300px_minmax(0,1fr)]">
                <aside class="flex min-h-0 flex-col border-b border-slate-200 bg-white lg:border-b-0 lg:border-r">
                    <div class="border-b border-slate-100 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h1 class="text-xl font-semibold text-slate-950">Messages</h1>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{ totalUnread > 0 ? `${totalUnread} unread` : 'All caught up' }}
                                </p>
                            </div>
                            <Link
                                :href="archivedHref"
                                class="inline-flex items-center rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-100"
                            >
                                <ArchiveBoxIcon class="mr-1.5 h-4 w-4" />
                                Archived
                            </Link>
                        </div>

                        <div class="relative mt-4">
                            <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Search chats..."
                                class="w-full rounded-2xl border border-slate-200 bg-slate-50 py-2.5 pl-9 pr-3 text-sm text-slate-700 outline-none transition focus:border-primary-300 focus:bg-white focus:ring-2 focus:ring-primary-100"
                            />
                        </div>
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto p-2">
                        <Link
                            v-for="conversation in filteredConversations"
                            :key="conversation.id"
                            :href="conversationHref(conversation)"
                            class="flex gap-3 rounded-2xl p-3 transition hover:bg-slate-50"
                        >
                            <div class="relative shrink-0">
                                <img
                                    v-if="getAvatarSrc(conversation)"
                                    :src="getAvatarSrc(conversation)"
                                    class="h-12 w-12 rounded-2xl object-cover ring-1 ring-slate-200"
                                />
                                <div
                                    v-else
                                    class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-sky-500 to-indigo-600 text-sm font-semibold text-white"
                                >
                                    {{ getAvatarInitial(conversation) }}
                                </div>
                                <span
                                    v-if="conversation.unread_count > 0"
                                    class="absolute -right-1 -top-1 flex min-h-5 min-w-5 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white ring-2 ring-white"
                                >
                                    {{ conversation.unread_count > 9 ? '9+' : conversation.unread_count }}
                                </span>
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-2">
                                    <h2
                                        :class="[
                                            'truncate text-sm text-slate-950',
                                            conversation.unread_count > 0 ? 'font-bold' : 'font-semibold'
                                        ]"
                                    >
                                        {{ conversation.service_provider?.business_name || conversation.user?.full_name || 'Unknown' }}
                                    </h2>
                                    <span class="shrink-0 text-[11px] font-medium text-slate-400">
                                        {{ formatTime(conversation.last_message_at) }}
                                    </span>
                                </div>
                                <div v-if="conversation.lead" class="mt-1 flex items-center gap-1.5">
                                    <span class="truncate rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600">
                                        {{ conversation.lead.service_type_label || conversation.lead.service_type }}
                                    </span>
                                    <span
                                        class="rounded-full px-2 py-0.5 text-[11px] font-medium"
                                        :class="{
                                            'bg-yellow-100 text-yellow-700': conversation.lead.status === 'new',
                                            'bg-blue-100 text-blue-700': conversation.lead.status === 'contacted',
                                            'bg-purple-100 text-purple-700': conversation.lead.status === 'in_progress',
                                            'bg-green-100 text-green-700': conversation.lead.status === 'converted',
                                            'bg-gray-100 text-gray-600': ['closed', 'declined'].includes(conversation.lead.status),
                                        }"
                                    >
                                        {{ conversation.lead.status }}
                                    </span>
                                </div>
                                <p
                                    :class="[
                                        'mt-1 truncate text-sm',
                                        conversation.unread_count > 0 ? 'font-medium text-slate-800' : 'text-slate-500'
                                    ]"
                                >
                                    {{ conversation.latest_message?.body || 'No messages yet' }}
                                </p>
                            </div>
                        </Link>

                        <div v-if="!filteredConversations.length" class="px-4 py-10 text-center">
                            <InboxIcon class="mx-auto h-10 w-10 text-slate-300" />
                            <h3 class="mt-3 text-sm font-semibold text-slate-900">
                                {{ searchQuery ? 'No conversations found' : 'No messages yet' }}
                            </h3>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ searchQuery ? 'Try a different search term.' : 'Start a conversation by contacting a provider.' }}
                            </p>
                            <Link v-if="!searchQuery" :href="route('marketplace.index')" class="mt-4 inline-flex rounded-2xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white">
                                Browse Providers
                            </Link>
                        </div>
                    </div>
                </aside>

                <section class="hidden min-h-0 flex-col items-center justify-center bg-gradient-to-b from-slate-50 to-white p-8 text-center lg:flex">
                    <div class="flex h-20 w-20 items-center justify-center rounded-3xl bg-primary-50 text-primary-600 ring-1 ring-primary-100">
                        <ChatBubbleLeftRightIcon class="h-10 w-10" />
                    </div>
                    <h2 class="mt-5 text-2xl font-semibold text-slate-950">Select a conversation</h2>
                    <p class="mt-2 max-w-md text-sm leading-6 text-slate-500">
                        Choose a person from the left list to open the chat. Message actions, offers, attachments, and replies will appear here.
                    </p>
                </section>
            </div>
        </div>
    </component>
</template>
