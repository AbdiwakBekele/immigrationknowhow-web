<script setup>
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    ChatBubbleLeftRightIcon,
    MagnifyingGlassIcon,
    BookOpenIcon,
    ClipboardDocumentListIcon,
    UserCircleIcon,
    ArrowRightIcon,
    StarIcon,
    CheckCircleIcon,
    ClockIcon,
    ArchiveBoxIcon,
    TrashIcon,
    UserGroupIcon,
} from '@heroicons/vue/24/outline';
import { StarIcon as StarSolid } from '@heroicons/vue/24/solid';

const props = defineProps({
    stats: Object,
    recentLeads: Array,
    recentMessages: Array,
    recommendedProviders: Array,
    libraryItems: Array,
    purchasedItems: Array,
});

const page = usePage();
const user = page.props.auth.user;

const profileCompletion = computed(() => Number(props.stats?.profileCompletion ?? 0) || 0);
const unreadMessagesCount = computed(() => Number(props.stats?.unreadMessages ?? 0) || 0);
const totalLeadsCount = computed(() => Number(props.stats?.totalLeads ?? 0) || 0);

const quickActions = computed(() => [
    {
        title: 'Find Providers',
        subtitle: 'Search for services',
        href: route('marketplace.index'),
        icon: MagnifyingGlassIcon,
        iconClass: 'bg-blue-100 text-blue-600',
    },
    {
        title: 'Messages',
        subtitle: `${unreadMessagesCount.value} unread`,
        href: route('messages.index'),
        icon: ChatBubbleLeftRightIcon,
        iconClass: 'bg-emerald-100 text-emerald-600',
        badge: unreadMessagesCount.value > 0 ? (unreadMessagesCount.value > 9 ? '9+' : String(unreadMessagesCount.value)) : '',
    },
    {
        title: 'Library',
        subtitle: 'E-books & audiobooks',
        href: route('library.my'),
        icon: BookOpenIcon,
        iconClass: 'bg-violet-100 text-violet-600',
    },
    {
        title: 'Profile',
        subtitle: 'Update your info',
        href: route('profile.edit'),
        icon: UserCircleIcon,
        iconClass: 'bg-slate-100 text-slate-600',
    },
    {
        title: 'Community',
        subtitle: 'Join discussions',
        href: route('community.index'),
        icon: UserGroupIcon,
        iconClass: 'bg-amber-100 text-amber-600',
    },
]);

const getLeadStatusColor = (status) => {
    const colors = {
        new: 'bg-blue-100 text-blue-700',
        contacted: 'bg-yellow-100 text-yellow-700',
        in_progress: 'bg-purple-100 text-purple-700',
        converted: 'bg-green-100 text-green-700',
        closed: 'bg-slate-100 text-slate-700',
        declined: 'bg-red-100 text-red-700',
    };
    return colors[status] || 'bg-slate-100 text-slate-700';
};

const formatTimeAgo = (date) => {
    const d = new Date(date);
    const now = new Date();
    const diff = now - d;
    
    if (diff < 60000) return 'Just now';
    if (diff < 3600000) return `${Math.floor(diff / 60000)}m ago`;
    if (diff < 86400000) return `${Math.floor(diff / 3600000)}h ago`;
    return d.toLocaleDateString();
};

const archiveConversation = (conversationUuid) => {
    if (!conversationUuid) return;

    router.post(route('messages.archive', conversationUuid), {}, {
        preserveScroll: true,
    });
};

const deleteConversation = (conversationUuid) => {
    if (!conversationUuid) return;
    if (!window.confirm('Delete this conversation permanently?')) return;

    router.delete(route('messages.destroy', conversationUuid), {
        preserveScroll: true,
    });
};

const conversationHref = (conversationUuid) => (
    conversationUuid
        ? route('messages.show', conversationUuid)
        : route('messages.index')
);

const resolveAvatar = (person) => {
    const candidate = (person?.avatar_url || person?.avatar || '').trim();
    if (!candidate) return '';
    if (candidate.startsWith('http://') || candidate.startsWith('https://') || candidate.startsWith('/')) {
        return candidate;
    }
    return `/storage/${candidate}`;
};

const firstInitial = (...values) => {
    for (const value of values) {
        const text = (value || '').trim();
        if (text) return text.charAt(0).toUpperCase();
    }
    return 'U';
};

const formatProviderRating = (rating) => {
    if (rating === null || rating === undefined || String(rating).trim() === '') {
        return 'New';
    }

    const value = Number(rating);

    return Number.isFinite(value) ? value.toFixed(1) : 'New';
};

/** First featured library item cover for the Library shortcut “avatar”. */
const libraryShortcutCoverUrl = computed(() => {
    for (const item of props.libraryItems ?? []) {
        if (item.cover_image_url) {
            return item.cover_image_url;
        }
        if (item.cover_image) {
            return `/storage/${item.cover_image}`;
        }
    }
    return null;
});

/** Your Purchases: paid amount first; else list price (> 0). Never show the word “Free”. */
const purchaseRowPrice = (purchase) => {
    const paid = purchase.purchase_amount;
    if (paid !== null && paid !== undefined && String(paid).trim() !== '' && Number(paid) > 0) {
        return {
            currency: purchase.purchase_currency ?? 'USD',
            amount: Number(paid),
        };
    }
    const item = purchase.item;
    if (item && item.price != null && String(item.price).trim() !== '') {
        const n = Number(item.price);
        if (!Number.isNaN(n) && n > 0) {
            return {
                currency: item.currency ?? 'USD',
                amount: n,
            };
        }
    }
    return null;
};
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout>
        <div class="mx-auto max-w-7xl space-y-4 px-2 pb-4 sm:space-y-5 sm:px-3">
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="bg-gradient-to-r from-sky-600 via-sky-600 to-indigo-600 p-5 text-white sm:p-6">
                    <h1 class="text-2xl font-display font-bold">
                        Welcome back, {{ user.first_name }}!
                    </h1>
                    <p class="mt-1 text-sm text-white/90">
                        Here's what's happening with your immigration journey.
                    </p>
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <span
                            v-if="stats?.preferredLanguageLabel"
                            class="rounded-full border border-white/30 bg-white/20 px-3 py-1 text-xs font-semibold"
                        >
                            {{ stats.preferredLanguageLabel }}
                        </span>
                        <span class="rounded-full border border-white/30 bg-white/20 px-3 py-1 text-xs font-semibold">
                            {{ profileCompletion }}% profile complete
                        </span>
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-2 border-t border-sky-100 bg-white p-3 sm:gap-3">
                    <div class="rounded-xl bg-slate-50 p-3 text-center">
                        <p class="text-lg font-bold text-slate-900">{{ totalLeadsCount }}</p>
                        <p class="text-xs text-slate-500">Inquiries</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3 text-center">
                        <p class="text-lg font-bold text-slate-900">{{ unreadMessagesCount }}</p>
                        <p class="text-xs text-slate-500">Unread messages</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3 text-center">
                        <p class="text-lg font-bold text-slate-900">{{ profileCompletion }}%</p>
                        <p class="text-xs text-slate-500">Profile complete</p>
                    </div>
                </div>
            </section>

            <section class="space-y-2">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Quick actions</h2>
                </div>
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
                <Link
                    v-for="action in quickActions"
                    :key="action.title"
                    :href="action.href"
                    class="group relative rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                >
                    <span
                        v-if="action.badge"
                        class="absolute right-3 top-3 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold leading-none text-white"
                    >
                        {{ action.badge }}
                    </span>
                    <div class="mb-3 flex h-10 w-10 items-center justify-center overflow-hidden rounded-xl" :class="action.iconClass">
                        <img
                            v-if="action.title === 'Library' && libraryShortcutCoverUrl"
                            :src="libraryShortcutCoverUrl"
                            alt=""
                            class="h-full w-full object-cover object-top"
                        />
                        <component :is="action.icon" v-else class="h-5 w-5" />
                    </div>
                    <h3 class="font-semibold text-slate-900">{{ action.title }}</h3>
                    <p class="mt-0.5 text-xs text-slate-500">{{ action.subtitle }}</p>
                </Link>
            </div>
            </section>

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <!-- Main Content -->
                    <div class="space-y-5 lg:col-span-2">
                        <!-- Recent Inquiries -->
                        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div class="flex items-center justify-between border-b border-slate-100 p-4 sm:p-5">
                                <h2 class="text-lg font-display font-bold text-slate-900">
                                    Your Inquiries
                                </h2>
                                <span class="text-sm text-slate-500">{{ totalLeadsCount }} total</span>
                            </div>

                            <div v-if="recentLeads?.length" class="divide-y divide-slate-100">
                                <Link 
                                    v-for="lead in recentLeads" 
                                    :key="lead.uuid"
                                    :href="conversationHref(lead.conversation?.uuid)"
                                    class="flex items-center gap-3 p-4 transition-colors hover:bg-slate-50 sm:gap-4"
                                >
                                    <img
                                        v-if="resolveAvatar(lead.service_provider?.user)"
                                        :src="resolveAvatar(lead.service_provider?.user)"
                                        :alt="lead.service_provider?.business_name"
                                        class="w-12 h-12 rounded-xl object-cover"
                                    />
                                    <div
                                        v-else
                                        class="flex h-12 w-12 items-center justify-center rounded-xl bg-primary-600 text-sm font-bold text-white"
                                        aria-hidden="true"
                                    >
                                        {{ firstInitial(lead.service_provider?.business_name, lead.service_provider?.user?.first_name) }}
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 mb-1">
                                            <h3 class="font-semibold text-slate-900 truncate">
                                                {{ lead.service_provider?.business_name }}
                                            </h3>
                                            <span :class="['px-2 py-0.5 text-xs font-medium rounded-full', getLeadStatusColor(lead.status)]">
                                                {{ lead.status }}
                                            </span>
                                        </div>
                                        <p class="text-sm text-slate-500 truncate">{{ lead.message }}</p>
                                    </div>
                                    <span class="hidden flex-shrink-0 text-sm text-slate-400 sm:inline">
                                        {{ formatTimeAgo(lead.created_at) }}
                                    </span>
                                    <div v-if="lead.conversation?.uuid" class="flex items-center gap-1">
                                        <button
                                            type="button"
                                            class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-primary-600"
                                            title="Archive"
                                            @click.prevent.stop="archiveConversation(lead.conversation.uuid)"
                                        >
                                            <ArchiveBoxIcon class="h-4 w-4" />
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-red-600"
                                            title="Delete"
                                            @click.prevent.stop="deleteConversation(lead.conversation.uuid)"
                                        >
                                            <TrashIcon class="h-4 w-4" />
                                        </button>
                                    </div>
                                </Link>
                            </div>
                            
                            <div v-else class="p-8 text-center">
                                <ClipboardDocumentListIcon class="w-12 h-12 text-slate-300 mx-auto mb-3" />
                                <p class="text-slate-500">No inquiries yet</p>
                                <Link 
                                    :href="route('marketplace.index')"
                                    class="inline-flex items-center gap-2 mt-4 text-primary-600 hover:text-primary-700 font-medium"
                                >
                                    Find a provider
                                    <ArrowRightIcon class="w-4 h-4" />
                                </Link>
                            </div>
                        </div>

                        <!-- Recommended Providers -->
                        <div v-if="recommendedProviders?.length" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div class="flex items-center justify-between border-b border-slate-100 p-4 sm:p-5">
                                <h2 class="text-lg font-display font-bold text-slate-900">
                                    Recommended for You
                                </h2>
                                <Link :href="route('marketplace.index')" class="text-sm text-primary-600 hover:text-primary-700 font-medium">
                                    View all
                                </Link>
                            </div>

                            <div class="grid grid-cols-1 gap-3 p-3 sm:grid-cols-2 sm:gap-4 sm:p-4">
                                <Link 
                                    v-for="provider in recommendedProviders" 
                                    :key="provider.id"
                                    :href="route('marketplace.show', provider.slug)"
                                    class="flex items-start gap-3 rounded-xl bg-slate-50 p-3 transition-colors hover:bg-slate-100 sm:gap-4 sm:p-4"
                                >
                                    <img
                                        v-if="resolveAvatar(provider.user)"
                                        :src="resolveAvatar(provider.user)"
                                        :alt="provider.business_name"
                                        class="w-14 h-14 rounded-xl object-cover"
                                    />
                                    <div
                                        v-else
                                        class="flex h-14 w-14 items-center justify-center rounded-xl bg-primary-600 text-lg font-bold text-white"
                                        aria-hidden="true"
                                    >
                                        {{ firstInitial(provider.business_name, provider.user?.first_name) }}
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h3 class="font-semibold text-slate-900 truncate">
                                            {{ provider.business_name }}
                                        </h3>
                                        <div class="flex items-center gap-1 mt-1">
                                            <StarSolid class="w-4 h-4 text-secondary-500" />
                                            <span class="text-sm text-slate-600">
                                                {{ formatProviderRating(provider.average_rating) }}
                                            </span>
                                            <span class="text-sm text-slate-400">({{ provider.total_reviews }})</span>
                                        </div>
                                        <p v-if="provider.free_consultation" class="flex items-center gap-1 mt-1 text-xs text-green-600">
                                            <CheckCircleIcon class="w-3.5 h-3.5" />
                                            Free consultation
                                        </p>
                                    </div>
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="space-y-5">
                        <!-- Recent Messages Preview -->
                        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div class="flex items-center justify-between border-b border-slate-100 p-4">
                                <h3 class="font-semibold text-slate-900">Recent Messages</h3>
                                <Link :href="route('messages.index')" class="text-sm text-primary-600 hover:text-primary-700">
                                    View all
                                </Link>
                            </div>

                            <div v-if="recentMessages?.length" class="divide-y divide-slate-100">
                                <Link 
                                    v-for="message in recentMessages" 
                                    :key="message.uuid"
                                    :href="conversationHref(message.conversation_uuid)"
                                    class="flex items-center gap-3 p-4 hover:bg-slate-50 transition-colors"
                                >
                                    <img
                                        v-if="resolveAvatar(message.sender)"
                                        :src="resolveAvatar(message.sender)"
                                        :alt="message.sender?.first_name"
                                        class="w-10 h-10 rounded-full object-cover"
                                    />
                                    <div
                                        v-else
                                        class="flex h-10 w-10 items-center justify-center rounded-full bg-primary-600 text-xs font-bold text-white"
                                        aria-hidden="true"
                                    >
                                        {{ firstInitial(message.sender?.first_name, message.sender?.last_name) }}
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-slate-900 truncate">
                                            {{ message.sender?.first_name }}
                                        </p>
                                        <p class="text-sm text-slate-500 truncate">{{ message.body }}</p>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <button
                                            type="button"
                                            class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-primary-600"
                                            title="Archive"
                                            @click.prevent.stop="archiveConversation(message.conversation_uuid)"
                                        >
                                            <ArchiveBoxIcon class="h-4 w-4" />
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-red-600"
                                            title="Delete"
                                            @click.prevent.stop="deleteConversation(message.conversation_uuid)"
                                        >
                                            <TrashIcon class="h-4 w-4" />
                                        </button>
                                    </div>
                                </Link>
                            </div>
                            
                            <div v-else class="p-6 text-center text-slate-500 text-sm">
                                No messages yet
                            </div>
                        </div>

                        <!-- Library Preview -->
                        <div v-if="libraryItems?.length" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div class="flex items-center justify-between border-b border-slate-100 p-4">
                                <h3 class="font-semibold text-slate-900">From the Library</h3>
                                <Link :href="route('library.my')" class="text-sm text-primary-600 hover:text-primary-700">
                                    Browse all
                                </Link>
                            </div>

                            <div class="p-3 space-y-2">
                                <Link 
                                    v-for="item in libraryItems" 
                                    :key="item.uuid"
                                    :href="route('library.show', item.slug)"
                                    class="flex items-center gap-2.5 group"
                                >
                                    <div class="w-8 h-6 sm:w-9 sm:h-7 bg-slate-100 rounded overflow-hidden flex-shrink-0 ring-1 ring-slate-100">
                                        <img 
                                            v-if="item.cover_image_url"
                                            :src="item.cover_image_url"
                                            :alt="item.title"
                                            class="w-full h-full object-cover"
                                        />
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-slate-900 truncate group-hover:text-primary-600 transition-colors">
                                            {{ item.title }}
                                        </p>
                                        <p class="text-xs text-slate-500">{{ item.author }}</p>
                                    </div>
                                    <div
                                        v-if="Number(item.price) > 0"
                                        class="shrink-0 text-xs font-medium tabular-nums text-slate-700"
                                    >
                                        <span class="text-slate-500">{{ item.currency ?? 'USD' }}</span>
                                        {{ item.price }}
                                    </div>
                                    <div v-else-if="item.is_premium" class="shrink-0 text-xs font-medium text-amber-700">
                                        Paid
                                    </div>
                                    <div v-else class="shrink-0 text-xs font-medium text-emerald-600">
                                        Free
                                    </div>
                                </Link>
                            </div>
                        </div>

                        <!-- Purchased Library Items -->
                        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div class="flex items-center justify-between border-b border-slate-100 p-4">
                                <h3 class="font-semibold text-slate-900">Your Purchases</h3>
                                <Link :href="route('library.my')" class="text-sm text-primary-600 hover:text-primary-700">
                                    Open library
                                </Link>
                            </div>

                            <div v-if="purchasedItems?.length" class="p-3 space-y-2">
                                <Link
                                    v-for="purchase in purchasedItems"
                                    :key="purchase.access_id"
                                    :href="route('library.show', purchase.item.slug)"
                                    class="flex items-center gap-2.5 group"
                                >
                                    <div class="w-8 h-6 sm:w-9 sm:h-7 bg-slate-100 rounded overflow-hidden flex-shrink-0 ring-1 ring-slate-100">
                                        <img
                                            v-if="purchase.item.cover_image_url"
                                            :src="purchase.item.cover_image_url"
                                            :alt="purchase.item.title"
                                            class="w-full h-full object-cover"
                                        />
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-slate-900 truncate group-hover:text-primary-600 transition-colors">
                                            {{ purchase.item.title }}
                                        </p>
                                        <p class="text-xs text-slate-500">
                                            {{ purchase.item.author }} · Bought {{ formatTimeAgo(purchase.purchased_at) }}
                                        </p>
                                    </div>
                                    <template v-for="row in [purchaseRowPrice(purchase)]" :key="purchase.access_id + '-price'">
                                        <div
                                            v-if="row"
                                            class="shrink-0 text-xs font-medium tabular-nums text-slate-700"
                                        >
                                            <span class="text-slate-500">{{ row.currency }}</span>
                                            {{ row.amount }}
                                        </div>
                                    </template>
                                </Link>
                            </div>

                            <div v-else class="p-6 text-center text-slate-500 text-sm">
                                No purchases yet
                            </div>
                        </div>

                        <!-- Profile Completion -->
                        <div v-if="profileCompletion < 100" class="rounded-2xl border border-sky-100 bg-gradient-to-br from-sky-50 to-indigo-50 p-6 shadow-sm">
                            <h3 class="font-semibold text-slate-900 mb-2">Complete Your Profile</h3>
                            <p class="text-sm text-slate-600 mb-4">
                                A complete profile helps providers better understand your needs.
                            </p>
                            <div class="mb-4">
                                <div class="flex items-center justify-between text-sm mb-2">
                                    <span class="text-slate-600">Progress</span>
                                    <span class="font-medium text-sky-700">{{ profileCompletion }}%</span>
                                </div>
                                <div class="h-2 overflow-hidden rounded-full bg-white">
                                    <div
                                        :style="{ width: `${profileCompletion}%` }"
                                        class="h-full rounded-full bg-sky-600 transition-all"
                                    ></div>
                                </div>
                            </div>
                            <Link
                                :href="route('profile.edit')"
                                class="inline-flex items-center gap-2 text-sm font-medium text-sky-700 hover:text-sky-800"
                            >
                                Update profile
                                <ArrowRightIcon class="w-4 h-4" />
                            </Link>
                        </div>
                    </div>
                </div>
        </div>
    </AppLayout>
</template>
