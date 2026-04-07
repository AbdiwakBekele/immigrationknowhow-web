<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
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
} from '@heroicons/vue/24/outline';
import { StarIcon as StarSolid } from '@heroicons/vue/24/solid';

const props = defineProps({
    stats: Object,
    recentLeads: Array,
    recentMessages: Array,
    recommendedProviders: Array,
    libraryItems: Array,
});

const page = usePage();
const user = page.props.auth.user;

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
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout>
        <div class="min-h-screen bg-slate-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <!-- Welcome Header -->
                <div class="mb-8">
                    <h1 class="text-3xl font-display font-bold text-slate-900">
                        Welcome back, {{ user.first_name }}!
                    </h1>
                    <p class="text-slate-500 mt-1">
                        Here's what's happening with your immigration journey.
                    </p>
                </div>

                <!-- Quick Actions -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                    <Link 
                        :href="route('marketplace.index')"
                        class="bg-white rounded-2xl p-5 shadow-soft hover:shadow-lg transition-all group"
                    >
                        <div class="w-12 h-12 bg-primary-100 rounded-xl flex items-center justify-center mb-4 group-hover:bg-primary-200 transition-colors">
                            <MagnifyingGlassIcon class="w-6 h-6 text-primary-600" />
                        </div>
                        <h3 class="font-semibold text-slate-900 group-hover:text-primary-600 transition-colors">
                            Find Providers
                        </h3>
                        <p class="text-sm text-slate-500 mt-1">Search for services</p>
                    </Link>

                    <Link 
                        :href="route('messages.index')"
                        class="bg-white rounded-2xl p-5 shadow-soft hover:shadow-lg transition-all group relative"
                    >
                        <span v-if="stats?.unreadMessages" class="absolute top-4 right-4 w-6 h-6 bg-accent-500 text-white text-xs font-bold rounded-full flex items-center justify-center">
                            {{ stats.unreadMessages > 9 ? '9+' : stats.unreadMessages }}
                        </span>
                        <div class="w-12 h-12 bg-secondary-100 rounded-xl flex items-center justify-center mb-4 group-hover:bg-secondary-200 transition-colors">
                            <ChatBubbleLeftRightIcon class="w-6 h-6 text-secondary-600" />
                        </div>
                        <h3 class="font-semibold text-slate-900 group-hover:text-secondary-600 transition-colors">
                            Messages
                        </h3>
                        <p class="text-sm text-slate-500 mt-1">{{ stats?.unreadMessages || 0 }} unread</p>
                    </Link>

                    <Link 
                        :href="route('library.index')"
                        class="bg-white rounded-2xl p-5 shadow-soft hover:shadow-lg transition-all group"
                    >
                        <div class="w-12 h-12 bg-accent-100 rounded-xl flex items-center justify-center mb-4 group-hover:bg-accent-200 transition-colors">
                            <BookOpenIcon class="w-6 h-6 text-accent-600" />
                        </div>
                        <h3 class="font-semibold text-slate-900 group-hover:text-accent-600 transition-colors">
                            Library
                        </h3>
                        <p class="text-sm text-slate-500 mt-1">E-books & audiobooks</p>
                    </Link>

                    <Link 
                        :href="route('profile.edit')"
                        class="bg-white rounded-2xl p-5 shadow-soft hover:shadow-lg transition-all group"
                    >
                        <div class="w-12 h-12 bg-slate-100 rounded-xl flex items-center justify-center mb-4 group-hover:bg-slate-200 transition-colors">
                            <UserCircleIcon class="w-6 h-6 text-slate-600" />
                        </div>
                        <h3 class="font-semibold text-slate-900 group-hover:text-slate-600 transition-colors">
                            Profile
                        </h3>
                        <p class="text-sm text-slate-500 mt-1">Update your info</p>
                    </Link>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Main Content -->
                    <div class="lg:col-span-2 space-y-8">
                        <!-- Recent Inquiries -->
                        <div class="bg-white rounded-2xl shadow-soft overflow-hidden">
                            <div class="flex items-center justify-between p-6 border-b border-slate-100">
                                <h2 class="text-lg font-display font-bold text-slate-900">
                                    Your Inquiries
                                </h2>
                                <span class="text-sm text-slate-500">{{ stats?.totalLeads || 0 }} total</span>
                            </div>

                            <div v-if="recentLeads?.length" class="divide-y divide-slate-100">
                                <Link 
                                    v-for="lead in recentLeads" 
                                    :key="lead.uuid"
                                    :href="route('messages.show', lead.conversation?.uuid)"
                                    class="flex items-center gap-4 p-4 hover:bg-slate-50 transition-colors"
                                >
                                    <img 
                                        :src="lead.service_provider?.user?.avatar || '/img/default-avatar.png'"
                                        :alt="lead.service_provider?.business_name"
                                        class="w-12 h-12 rounded-xl object-cover"
                                    />
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
                                    <span class="text-sm text-slate-400 flex-shrink-0">
                                        {{ formatTimeAgo(lead.created_at) }}
                                    </span>
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
                        <div v-if="recommendedProviders?.length" class="bg-white rounded-2xl shadow-soft overflow-hidden">
                            <div class="flex items-center justify-between p-6 border-b border-slate-100">
                                <h2 class="text-lg font-display font-bold text-slate-900">
                                    Recommended for You
                                </h2>
                                <Link :href="route('marketplace.index')" class="text-sm text-primary-600 hover:text-primary-700 font-medium">
                                    View all
                                </Link>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4">
                                <Link 
                                    v-for="provider in recommendedProviders" 
                                    :key="provider.id"
                                    :href="route('marketplace.show', provider.slug)"
                                    class="flex items-start gap-4 p-4 bg-slate-50 rounded-xl hover:bg-slate-100 transition-colors"
                                >
                                    <img 
                                        :src="provider.user?.avatar || '/img/default-avatar.png'"
                                        :alt="provider.business_name"
                                        class="w-14 h-14 rounded-xl object-cover"
                                    />
                                    <div class="flex-1 min-w-0">
                                        <h3 class="font-semibold text-slate-900 truncate">
                                            {{ provider.business_name }}
                                        </h3>
                                        <div class="flex items-center gap-1 mt-1">
                                            <StarSolid class="w-4 h-4 text-secondary-500" />
                                            <span class="text-sm text-slate-600">
                                                {{ provider.average_rating?.toFixed(1) || 'New' }}
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
                    <div class="space-y-6">
                        <!-- Recent Messages Preview -->
                        <div class="bg-white rounded-2xl shadow-soft overflow-hidden">
                            <div class="flex items-center justify-between p-4 border-b border-slate-100">
                                <h3 class="font-semibold text-slate-900">Recent Messages</h3>
                                <Link :href="route('messages.index')" class="text-sm text-primary-600 hover:text-primary-700">
                                    View all
                                </Link>
                            </div>

                            <div v-if="recentMessages?.length" class="divide-y divide-slate-100">
                                <Link 
                                    v-for="message in recentMessages" 
                                    :key="message.uuid"
                                    :href="route('messages.show', message.conversation_uuid)"
                                    class="flex items-center gap-3 p-4 hover:bg-slate-50 transition-colors"
                                >
                                    <img 
                                        :src="message.sender?.avatar || '/img/default-avatar.png'"
                                        :alt="message.sender?.first_name"
                                        class="w-10 h-10 rounded-full object-cover"
                                    />
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-slate-900 truncate">
                                            {{ message.sender?.first_name }}
                                        </p>
                                        <p class="text-sm text-slate-500 truncate">{{ message.body }}</p>
                                    </div>
                                </Link>
                            </div>
                            
                            <div v-else class="p-6 text-center text-slate-500 text-sm">
                                No messages yet
                            </div>
                        </div>

                        <!-- Library Preview -->
                        <div v-if="libraryItems?.length" class="bg-white rounded-2xl shadow-soft overflow-hidden">
                            <div class="flex items-center justify-between p-4 border-b border-slate-100">
                                <h3 class="font-semibold text-slate-900">From the Library</h3>
                                <Link :href="route('library.index')" class="text-sm text-primary-600 hover:text-primary-700">
                                    Browse all
                                </Link>
                            </div>

                            <div class="p-4 space-y-3">
                                <Link 
                                    v-for="item in libraryItems" 
                                    :key="item.uuid"
                                    :href="route('library.show', item.slug)"
                                    class="flex items-center gap-3 group"
                                >
                                    <div class="w-12 h-16 bg-slate-100 rounded-lg overflow-hidden flex-shrink-0">
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
                                </Link>
                            </div>
                        </div>

                        <!-- Profile Completion -->
                        <div v-if="stats?.profileCompletion < 100" class="bg-gradient-to-br from-primary-50 to-secondary-50 rounded-2xl p-6">
                            <h3 class="font-semibold text-slate-900 mb-2">Complete Your Profile</h3>
                            <p class="text-sm text-slate-600 mb-4">
                                A complete profile helps providers better understand your needs.
                            </p>
                            <div class="mb-4">
                                <div class="flex items-center justify-between text-sm mb-2">
                                    <span class="text-slate-600">Progress</span>
                                    <span class="font-medium text-primary-600">{{ stats?.profileCompletion || 0 }}%</span>
                                </div>
                                <div class="h-2 bg-white rounded-full overflow-hidden">
                                    <div 
                                        :style="{ width: `${stats?.profileCompletion || 0}%` }"
                                        class="h-full bg-primary-500 rounded-full transition-all"
                                    ></div>
                                </div>
                            </div>
                            <Link 
                                :href="route('profile.edit')"
                                class="inline-flex items-center gap-2 text-sm font-medium text-primary-600 hover:text-primary-700"
                            >
                                Update profile
                                <ArrowRightIcon class="w-4 h-4" />
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
