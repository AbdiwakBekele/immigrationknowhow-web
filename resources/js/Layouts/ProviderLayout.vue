<script setup>
import { ref, computed } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import { 
    Bars3Icon,
    HomeIcon,
    InboxIcon,
    ChatBubbleLeftRightIcon,
    StarIcon,
    UserCircleIcon,
    ShieldCheckIcon,
    ChartBarIcon,
    ArrowRightOnRectangleIcon,
    BellIcon,
} from '@heroicons/vue/24/outline';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const provider = computed(() => page.props.provider);
const sidebarOpen = ref(false);

const navigation = [
    { name: 'Dashboard', href: '/provider/dashboard', icon: HomeIcon },
    { name: 'Leads', href: '/provider/leads', icon: InboxIcon },
    { name: 'Messages', href: '/provider/messages', icon: ChatBubbleLeftRightIcon },
    { name: 'Reviews', href: '/provider/reviews', icon: StarIcon },
    { name: 'Profile', href: '/provider/profile', icon: UserCircleIcon },
    { name: 'Verification', href: '/provider/verification', icon: ShieldCheckIcon },
    { name: 'Analytics', href: '/provider/analytics', icon: ChartBarIcon },
];

const logout = () => {
    router.post('/logout');
};

const isActive = (href) => {
    return page.url.startsWith(href);
};
</script>

<template>
    <div class="min-h-screen bg-slate-50">
        <!-- Mobile sidebar backdrop -->
        <Transition
            enter-active-class="transition-opacity duration-300"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-300"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div 
                v-if="sidebarOpen" 
                class="fixed inset-0 z-40 bg-slate-900/50 lg:hidden"
                @click="sidebarOpen = false"
            ></div>
        </Transition>

        <!-- Sidebar -->
        <aside 
            :class="[
                'fixed inset-y-0 left-0 z-50 flex w-64 flex-col bg-white border-r border-slate-200 transform transition-transform duration-300 lg:translate-x-0',
                sidebarOpen ? 'translate-x-0' : '-translate-x-full'
            ]"
        >
            <!-- Logo -->
            <div class="flex h-16 items-center gap-3 px-6 border-b border-slate-100">
                <Link href="/" class="flex items-center gap-3">
                    <div class="h-9 w-9 rounded-lg bg-gradient-to-br from-sky-500 to-indigo-600 flex items-center justify-center">
                        <span class="text-white font-bold">IK</span>
                    </div>
                    <div>
                        <span class="text-slate-900 font-semibold">ImmigrationKnowHow</span>
                        <span class="block text-xs text-slate-500">Provider Portal</span>
                    </div>
                </Link>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
                <Link
                    v-for="item in navigation"
                    :key="item.name"
                    :href="item.href"
                    :class="[
                        'flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors',
                        isActive(item.href)
                            ? 'bg-sky-50 text-sky-700'
                            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                    ]"
                >
                    <component :is="item.icon" class="h-5 w-5 flex-shrink-0" />
                    {{ item.name }}
                </Link>
            </nav>

            <div class="p-4 border-t border-slate-100">
                <div class="flex items-center gap-3 rounded-xl bg-slate-50 p-3">
                    <div class="h-12 w-12 rounded-lg bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center text-white font-semibold">
                        {{ user?.initials }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-slate-900">
                            {{ provider?.business_name || user?.full_name }}
                        </p>
                        <p class="mt-0.5 text-xs">
                            <span v-if="provider?.verification_status === 'approved'" class="text-emerald-600">Verified</span>
                            <span v-else class="text-amber-600">Pending Verification</span>
                        </p>
                    </div>
                </div>
            </div>

        </aside>

        <!-- Main content -->
        <div class="lg:pl-64">
            <!-- Top bar -->
            <header class="sticky top-0 z-30 bg-white/95 backdrop-blur-sm border-b border-slate-200">
                <div class="flex h-16 items-center justify-between px-4 sm:px-6">
                    <button
                        @click="sidebarOpen = true"
                        class="lg:hidden p-2 rounded-lg text-slate-500 hover:bg-slate-100"
                    >
                        <Bars3Icon class="h-6 w-6" />
                    </button>

                    <div class="flex-1 lg:flex-none">
                        <h1 class="text-lg font-semibold text-slate-900 lg:hidden">Provider Portal</h1>
                    </div>

                    <div class="flex items-center gap-4">
                        <button class="relative p-2 rounded-lg text-slate-500 hover:bg-slate-100 transition-colors">
                            <BellIcon class="h-6 w-6" />
                        </button>
                        <Link
                            :href="provider?.slug ? `/providers/${provider.slug}` : '#'"
                            class="hidden sm:inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm text-slate-600 hover:bg-slate-100 transition-colors"
                        >
                            View Public Profile
                        </Link>
                        <button
                            @click="logout"
                            class="hidden sm:inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm text-slate-600 hover:bg-slate-100 transition-colors"
                        >
                            <ArrowRightOnRectangleIcon class="h-4 w-4" />
                            Sign out
                        </button>
                    </div>
                </div>
            </header>

            <main class="p-4 sm:p-6 lg:p-8">
                <slot />
            </main>
        </div>
    </div>
</template>
