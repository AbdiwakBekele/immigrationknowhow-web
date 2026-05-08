<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ProviderLayout from '@/Layouts/ProviderLayout.vue';
import { 
    ChatBubbleLeftRightIcon,
    ArchiveBoxIcon,
    TrashIcon,
    MagnifyingGlassIcon,
    EllipsisVerticalIcon,
    CheckCircleIcon,
    ClockIcon,
    InboxIcon
} from '@heroicons/vue/24/outline';
import { ref, computed } from 'vue';
import { Menu, MenuButton, MenuItems, MenuItem } from '@headlessui/vue';

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

const archiveConversation = (uuid) => {
    const archiveRoute = props.isProvider ? route('provider.messages.archive', uuid) : route('messages.archive', uuid);
    router.post(archiveRoute, {}, {
        preserveScroll: true,
    });
};

const markAsRead = (uuid) => {
    const readRoute = props.isProvider ? route('provider.messages.read', uuid) : route('messages.read', uuid);
    router.post(readRoute, {}, {
        preserveScroll: true,
    });
};

const deleteConversation = (uuid) => {
    if (!window.confirm('Delete this conversation permanently?')) {
        return;
    }

    const destroyRoute = props.isProvider ? route('provider.messages.destroy', uuid) : route('messages.destroy', uuid);
    router.delete(destroyRoute, {
        preserveScroll: true,
    });
};

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
        <div class="mx-auto max-w-5xl space-y-6 pb-10">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900">Messages</h1>
                    <p class="mt-1 text-sm text-slate-600">
                        {{ totalUnread > 0 ? `${totalUnread} unread message${totalUnread > 1 ? 's' : ''}` : 'All caught up!' }}
                    </p>
                    <p class="mt-2 max-w-xl text-sm text-slate-500">
                        Private in-app messaging tied to your service inquiries only. There is no public community board.
                    </p>
                </div>
                <Link :href="archivedHref" class="inline-flex items-center rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                    <ArchiveBoxIcon class="h-4 w-4 mr-2" />
                    Archived
                </Link>
                </div>
            </section>

            <!-- Search -->
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="relative">
                    <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-slate-400" />
                    <input 
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search conversations..."
                        class="input w-full pl-10"
                    />
                </div>
            </div>

            <!-- Conversations List -->
            <div v-if="filteredConversations.length" class="divide-y divide-slate-100 rounded-2xl border border-slate-200 bg-white shadow-sm">
                <Link 
                    v-for="conversation in filteredConversations" 
                    :key="conversation.id"
                    :href="conversationHref(conversation)"
                    class="flex items-start gap-4 p-4 hover:bg-gray-50 transition-colors"
                >
                    <!-- Avatar -->
                    <div class="relative flex-shrink-0">
                        <img
                            v-if="getAvatarSrc(conversation)"
                            :src="getAvatarSrc(conversation)"
                            class="h-12 w-12 rounded-full object-cover ring-1 ring-slate-200"
                        />
                        <div
                            v-else
                            class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-200 text-sm font-semibold text-slate-700"
                        >
                            {{ getAvatarInitial(conversation) }}
                        </div>
                        <div 
                            v-if="conversation.unread_count > 0"
                            class="absolute -top-1 -right-1 h-5 w-5 bg-primary-600 text-white text-xs rounded-full flex items-center justify-center"
                        >
                            {{ conversation.unread_count > 9 ? '9+' : conversation.unread_count }}
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between">
                            <h3 class="font-semibold text-gray-900 truncate" :class="{ 'font-bold': conversation.unread_count > 0 }">
                                {{ conversation.service_provider?.business_name || conversation.user?.full_name || 'Unknown' }}
                            </h3>
                            <span class="text-xs text-gray-500 flex-shrink-0 ml-2">
                                {{ formatTime(conversation.last_message_at) }}
                            </span>
                        </div>
                        
                        <!-- Lead Context -->
                        <div v-if="conversation.lead" class="flex items-center gap-2 mt-0.5">
                            <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded">
                                {{ conversation.lead.service_type_label || conversation.lead.service_type }}
                            </span>
                            <span 
                                class="text-xs px-2 py-0.5 rounded"
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

                        <!-- Latest Message Preview -->
                        <p 
                            class="text-sm mt-1 truncate"
                            :class="conversation.unread_count > 0 ? 'text-gray-900' : 'text-gray-500'"
                        >
                            {{ conversation.latest_message?.body || 'No messages yet' }}
                        </p>
                    </div>

                    <!-- Actions Menu -->
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
                            <MenuItem v-slot="{ active }">
                                <button
                                    type="button"
                                    @click="deleteConversation(conversation.uuid)"
                                    class="flex items-center gap-2 w-full px-4 py-2 text-sm text-left text-red-600"
                                    :class="active ? 'bg-gray-50' : ''"
                                >
                                    <TrashIcon class="h-4 w-4" />
                                    Delete
                                </button>
                            </MenuItem>
                        </MenuItems>
                    </Menu>
                </Link>
            </div>

            <!-- Empty State -->
            <div v-else class="rounded-2xl border border-slate-200 bg-white p-12 text-center shadow-sm">
                <InboxIcon class="mx-auto mb-4 h-12 w-12 text-slate-300" />
                <h3 class="mb-2 text-lg font-medium text-slate-900">
                    {{ searchQuery ? 'No conversations found' : 'No messages yet' }}
                </h3>
                <p class="mb-6 text-slate-500">
                    {{ searchQuery 
                        ? 'Try a different search term' 
                        : 'Start a conversation by contacting a service provider' 
                    }}
                </p>
                <Link v-if="!searchQuery" :href="route('marketplace.index')" class="btn-primary">
                    Browse Providers
                </Link>
            </div>

            <!-- Pagination -->
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
    </component>
</template>
