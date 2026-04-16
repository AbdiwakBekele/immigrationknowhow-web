<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ProviderLayout from '@/Layouts/ProviderLayout.vue';
import { 
    ChatBubbleLeftRightIcon,
    ArchiveBoxIcon,
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
    router.post(`/messages/${uuid}/archive`, {}, {
        preserveScroll: true,
    });
};

const markAsRead = (uuid) => {
    router.post(`/messages/${uuid}/read`, {}, {
        preserveScroll: true,
    });
};

const getInitial = (name) => {
    const first = (name || '').trim();
    return first ? first.charAt(0).toUpperCase() : '?';
};
</script>

<template>
    <Head title="Messages" />

    <component :is="layoutComponent">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-display font-bold text-gray-900">Messages</h1>
                    <p class="text-gray-500 mt-1">
                        {{ totalUnread > 0 ? `${totalUnread} unread message${totalUnread > 1 ? 's' : ''}` : 'All caught up!' }}
                    </p>
                    <p class="text-sm text-gray-500 mt-2 max-w-xl">
                        Private in-app messaging tied to your service inquiries only. There is no public community board.
                    </p>
                </div>
                <Link :href="archivedHref" class="btn-secondary btn-sm">
                    <ArchiveBoxIcon class="h-4 w-4 mr-2" />
                    Archived
                </Link>
            </div>

            <!-- Search -->
            <div class="mb-6">
                <div class="relative">
                    <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400" />
                    <input 
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search conversations..."
                        class="input w-full pl-10"
                    />
                </div>
            </div>

            <!-- Conversations List -->
            <div v-if="filteredConversations.length" class="bg-white rounded-2xl shadow-sm border border-gray-100 divide-y divide-gray-100">
                <Link 
                    v-for="conversation in filteredConversations" 
                    :key="conversation.id"
                    :href="conversationHref(conversation)"
                    class="flex items-start gap-4 p-4 hover:bg-gray-50 transition-colors"
                >
                    <!-- Avatar -->
                    <div class="relative flex-shrink-0">
                        <img
                            v-if="conversation.service_provider?.user?.avatar || conversation.user?.avatar"
                            :src="conversation.service_provider?.user?.avatar || conversation.user?.avatar"
                            class="h-12 w-12 rounded-full object-cover"
                        />
                        <div
                            v-else
                            class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-200 text-sm font-semibold text-slate-700"
                        >
                            {{ getInitial(conversation.service_provider?.user?.first_name || conversation.user?.first_name || conversation.user?.full_name) }}
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
                        </MenuItems>
                    </Menu>
                </Link>
            </div>

            <!-- Empty State -->
            <div v-else class="bg-white rounded-2xl shadow-sm border border-gray-100 p-12 text-center">
                <InboxIcon class="h-12 w-12 text-gray-300 mx-auto mb-4" />
                <h3 class="text-lg font-medium text-gray-900 mb-2">
                    {{ searchQuery ? 'No conversations found' : 'No messages yet' }}
                </h3>
                <p class="text-gray-500 mb-6">
                    {{ searchQuery 
                        ? 'Try a different search term' 
                        : 'Start a conversation by contacting a service provider' 
                    }}
                </p>
                <Link v-if="!searchQuery" href="/marketplace" class="btn-primary">
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
