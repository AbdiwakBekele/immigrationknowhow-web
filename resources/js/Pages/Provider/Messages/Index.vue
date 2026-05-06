<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import ProviderLayout from '@/Layouts/ProviderLayout.vue';
import {
    ChatBubbleLeftRightIcon,
    ArchiveBoxIcon,
    MagnifyingGlassIcon,
    EllipsisVerticalIcon,
    CheckCircleIcon,
    InboxIcon,
} from '@heroicons/vue/24/outline';
import { ref, computed } from 'vue';
import { Menu, MenuButton, MenuItems, MenuItem } from '@headlessui/vue';

const props = defineProps({
    conversations: { type: Object, required: true },
    totalUnread: { type: Number, default: 0 },
});

const searchQuery = ref('');

const filteredConversations = computed(() => {
    if (!searchQuery.value) return props.conversations.data;
    const query = searchQuery.value.toLowerCase();
    return props.conversations.data.filter((conv) => {
        const clientName = conv.user?.full_name || '';
        const businessName = conv.service_provider?.business_name || '';
        return clientName.toLowerCase().includes(query) || businessName.toLowerCase().includes(query);
    });
});

const formatTime = (date) => {
    if (!date) return '';
    const d = new Date(date);
    const now = new Date();
    const diffDays = Math.floor((now - d) / (1000 * 60 * 60 * 24));

    if (diffDays === 0) {
        return d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
    }
    if (diffDays === 1) {
        return 'Yesterday';
    }
    if (diffDays < 7) {
        return d.toLocaleDateString('en-US', { weekday: 'short' });
    }
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
};

const archiveConversation = (uuid) => {
    router.post(route('provider.messages.archive', uuid), {}, {
        preserveScroll: true,
    });
};

const markAsRead = (uuid) => {
    router.post(route('provider.messages.read', uuid), {}, {
        preserveScroll: true,
    });
};

const latestPreview = (conv) => conv.latest_message?.body ?? conv.latestMessage?.body ?? '';

const leadStatus = (lead) => {
    if (!lead) return '';
    const s = lead.status;
    return typeof s === 'object' && s?.value != null ? s.value : s;
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
    <Head title="Messages" />

    <ProviderLayout>
        <div class="admin-page-container">
            <section class="admin-hero-card mb-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Messaging</p>
                    <h1 class="mt-2 admin-title">Client messages</h1>
                    <p v-if="totalUnread > 0" class="admin-subtitle">
                        {{ `${totalUnread} unread message${totalUnread > 1 ? 's' : ''}` }}
                    </p>
                    <p class="text-sm text-slate-500 mt-2 max-w-xl">
                        One-on-one inquiry threads (only clients who contacted you).
                    </p>
                </div>
                <Link :href="route('provider.messages.archived')" class="inline-flex items-center rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                    <ArchiveBoxIcon class="h-4 w-4 mr-2" />
                    Archived
                </Link>
                </div>
            </section>

            <div class="mb-6">
                <div class="relative">
                    <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400" />
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search by client name..."
                        class="input w-full pl-10"
                    />
                </div>
            </div>

            <div v-if="filteredConversations.length" class="bg-white rounded-2xl shadow-sm border border-gray-100 divide-y divide-gray-100">
                <Link
                    v-for="conversation in filteredConversations"
                    :key="conversation.id"
                    :href="route('provider.messages.show', conversation.uuid)"
                    class="flex items-start gap-4 p-4 hover:bg-gray-50 transition-colors"
                >
                    <div class="relative flex-shrink-0">
                        <img
                            v-if="getAvatarSrc(conversation.user)"
                            :src="getAvatarSrc(conversation.user)"
                            alt=""
                            class="h-12 w-12 rounded-full object-cover ring-1 ring-gray-200"
                        />
                        <div
                            v-else
                            class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-200 text-sm font-semibold text-slate-700 ring-1 ring-gray-200"
                        >
                            {{ getAvatarInitial(conversation.user) }}
                        </div>
                        <div
                            v-if="conversation.unread_count > 0"
                            class="absolute -top-1 -right-1 h-5 w-5 bg-primary-600 text-white text-xs rounded-full flex items-center justify-center"
                        >
                            {{ conversation.unread_count > 9 ? '9+' : conversation.unread_count }}
                        </div>
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between">
                            <h3 class="font-semibold text-gray-900 truncate" :class="{ 'font-bold': conversation.unread_count > 0 }">
                                {{ conversation.user?.full_name || 'Client' }}
                            </h3>
                            <span class="text-xs text-gray-500 flex-shrink-0 ml-2">
                                {{ formatTime(conversation.last_message_at) }}
                            </span>
                        </div>

                        <div v-if="conversation.lead" class="flex items-center gap-2 mt-0.5">
                            <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded">
                                {{ conversation.lead.service_type_label || conversation.lead.service_type }}
                            </span>
                            <span
                                class="text-xs px-2 py-0.5 rounded"
                                :class="{
                                    'bg-yellow-100 text-yellow-700': leadStatus(conversation.lead) === 'new',
                                    'bg-blue-100 text-blue-700': leadStatus(conversation.lead) === 'contacted',
                                    'bg-purple-100 text-purple-700': leadStatus(conversation.lead) === 'in_progress',
                                    'bg-green-100 text-green-700': leadStatus(conversation.lead) === 'converted',
                                    'bg-gray-100 text-gray-600': ['closed', 'declined'].includes(leadStatus(conversation.lead)),
                                }"
                            >
                                {{ leadStatus(conversation.lead) }}
                            </span>
                        </div>

                        <p
                            class="text-sm mt-1 truncate"
                            :class="conversation.unread_count > 0 ? 'text-gray-900' : 'text-gray-500'"
                        >
                            {{ latestPreview(conversation) || 'No messages yet' }}
                        </p>
                    </div>

                    <Menu as="div" class="relative flex-shrink-0" @click.prevent>
                        <MenuButton class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100">
                            <EllipsisVerticalIcon class="h-5 w-5" />
                        </MenuButton>
                        <MenuItems class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-lg border border-gray-100 py-1 z-10">
                            <MenuItem v-slot="{ active }">
                                <button
                                    type="button"
                                    @click="markAsRead(conversation.uuid)"
                                    class="flex items-center gap-2 w-full px-4 py-2 text-sm text-left"
                                    :class="active ? 'bg-gray-50' : ''"
                                >
                                    <CheckCircleIcon class="h-4 w-4" />
                                    Mark as read
                                </button>
                            </MenuItem>
                            <MenuItem v-slot="{ active }">
                                <button
                                    type="button"
                                    @click="archiveConversation(conversation.uuid)"
                                    class="flex items-center gap-2 w-full px-4 py-2 text-sm text-left"
                                    :class="active ? 'bg-gray-50' : ''"
                                >
                                    <ArchiveBoxIcon class="h-4 w-4" />
                                    Archive
                                </button>
                            </MenuItem>
                        </MenuItems>
                    </Menu>
                </Link>
            </div>

            <div v-else class="bg-white rounded-2xl shadow-sm border border-gray-100 p-12 text-center">
                <InboxIcon class="h-12 w-12 text-gray-300 mx-auto mb-4" />
                <h3 class="text-lg font-medium text-gray-900 mb-2">
                    {{ searchQuery ? 'No conversations found' : 'No messages yet' }}
                </h3>
                <p class="text-gray-500 mb-6">
                    {{ searchQuery ? 'Try a different search term' : 'When clients contact you, conversations will appear here.' }}
                </p>
            </div>

            <div v-if="conversations.links?.length > 3" class="mt-6 flex justify-center">
                <nav class="flex gap-1">
                    <Link
                        v-for="link in conversations.links"
                        :key="link.label"
                        :href="link.url"
                        class="px-3 py-2 text-sm rounded-lg"
                        :class="link.active ? 'bg-primary-600 text-white' : 'text-gray-600 hover:bg-gray-100'"
                        v-html="link.label"
                        :preserve-scroll="true"
                    />
                </nav>
            </div>
        </div>
    </ProviderLayout>
</template>
