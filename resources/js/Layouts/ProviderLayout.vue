<script setup>
import { ref, computed } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import BrandLogo from '@/Components/Brand/BrandLogo.vue';
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
const sidebarOpen = ref(false);

/** Always resolve a non-empty logo URL (branding can be missing or empty in edge cases). */
const providerLogoSrc = computed(() => {
    const u = page.props.branding?.site_logo_url;
    return typeof u === 'string' && u.trim() !== '' ? u : '/images/logo.svg';
});

const unreadNotificationsCount = computed(() => page.props.unread_notifications_count ?? 0);

const navigation = [
    { name: 'Dashboard', href: '/provider/dashboard', icon: HomeIcon },
    { name: 'Notifications', href: '/provider/notifications', icon: BellIcon },
    { name: 'Leads', href: '/provider/leads', icon: InboxIcon },
    { name: 'Messages', href: '/provider/messages', icon: ChatBubbleLeftRightIcon },
    { name: 'Reviews', href: '/provider/reviews', icon: StarIcon },
    { name: 'Profile', href: '/provider/profile', icon: UserCircleIcon },
    { name: 'Background Check', href: '/provider/background-check', icon: ShieldCheckIcon },
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
    <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-slate-50/90">
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
                class="fixed inset-0 z-40 bg-slate-900/40 backdrop-blur-[2px] lg:hidden"
                @click="sidebarOpen = false"
            ></div>
        </Transition>

        <!-- Sidebar -->
        <aside 
            :class="[
                'fixed inset-y-0 left-0 z-50 flex w-[17rem] flex-col border-r border-slate-200/80 bg-white/90 shadow-soft-lg backdrop-blur-xl transition-transform duration-300 ease-out lg:translate-x-0',
                sidebarOpen ? 'translate-x-0' : '-translate-x-full'
            ]"
        >
            <!-- Logo only (wordmark from branding; no duplicate name / portal badge) -->
            <div class="flex shrink-0 border-b border-slate-100/90 px-4 py-4">
                <Link
                    href="/"
                    class="block w-full rounded-lg outline-none ring-offset-2 transition-opacity hover:opacity-95 focus-visible:ring-2 focus-visible:ring-primary-400"
                >
                    <BrandLogo
                        context="site"
                        :mark-src="providerLogoSrc"
                        :show-name="false"
                        container-class="flex items-center"
                        mark-class="flex min-h-[3rem] w-full max-w-[220px] items-center justify-center overflow-visible rounded-xl border border-slate-200/80 bg-white px-2 py-2 shadow-sm"
                        image-class="block h-10 w-auto max-w-full object-contain object-left"
                        initials-class="text-sm font-bold uppercase tracking-wide text-slate-600"
                    />
                </Link>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 space-y-2 overflow-y-auto px-3 py-4">
                <Link
                    v-for="item in navigation"
                    :key="item.name"
                    :href="item.href"
                    :class="[
                        'group flex items-center gap-3 rounded-xl px-3 py-3 text-lg font-medium transition-all duration-200',
                        isActive(item.href)
                            ? 'bg-primary-50 text-primary-800 shadow-sm ring-1 ring-primary-100/80'
                            : 'text-slate-600 hover:bg-slate-100/90 hover:text-slate-900'
                    ]"
                >
                    <component
                        :is="item.icon"
                        :class="[
                            'h-6 w-6 shrink-0 transition-colors',
                            isActive(item.href) ? 'text-primary-600' : 'text-slate-400 group-hover:text-slate-600'
                        ]"
                    />
                    {{ item.name }}
                </Link>
            </nav>

            <div class="border-t border-slate-100/90 p-4">
                <Link
                    href="/provider/profile"
                    class="flex items-center justify-between rounded-xl border border-dashed border-slate-200/90 bg-slate-50/50 px-3 py-2.5 text-xs text-slate-500 transition-colors hover:border-primary-200 hover:bg-primary-50/40 hover:text-primary-700"
                >
                    <span class="font-medium">Profile and visibility</span>
                    <span class="text-primary-600">Manage →</span>
                </Link>
            </div>
        </aside>

        <!-- Main content -->
        <div class="lg:pl-[17rem]">
            <header class="sticky top-0 z-30 border-b border-slate-200/70 bg-white/80 shadow-sm backdrop-blur-md">
                <div class="flex h-[4.25rem] items-center justify-between gap-3 px-4 sm:px-6 lg:px-8">
                    <div class="flex min-w-0 flex-1 items-center gap-3">
                        <button
                            type="button"
                            @click="sidebarOpen = true"
                            class="inline-flex rounded-xl p-2 text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-800 lg:hidden"
                        >
                            <Bars3Icon class="h-6 w-6" />
                        </button>
                        <h1 class="truncate text-base font-semibold text-slate-900 lg:hidden">Provider Portal</h1>
                    </div>

                    <div class="flex shrink-0 items-center gap-1 sm:gap-2">
                        <Link
                            :href="route('provider.notifications.index')"
                            class="relative inline-flex rounded-xl p-2.5 text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-800"
                            title="Notifications"
                            aria-label="Notifications"
                        >
                            <BellIcon class="h-5 w-5" />
                            <span
                                v-if="unreadNotificationsCount > 0"
                                class="absolute right-1.5 top-1.5 h-2 w-2 rounded-full bg-rose-500 ring-2 ring-white"
                            />
                        </Link>
                        <Link
                            href="/provider/profile"
                            class="inline-flex rounded-xl p-2.5 text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-800"
                            title="Profile"
                            aria-label="Profile"
                        >
                            <UserCircleIcon class="h-5 w-5" />
                        </Link>
                        <button
                            type="button"
                            title="Sign out"
                            aria-label="Sign out"
                            class="inline-flex items-center gap-2 rounded-xl border border-slate-200/90 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm transition-all hover:border-slate-300 hover:bg-slate-50"
                            @click="logout"
                        >
                            <ArrowRightOnRectangleIcon class="h-4 w-4 text-slate-500" />
                            <span class="hidden sm:inline">Sign out</span>
                        </button>
                    </div>
                </div>
            </header>

            <main class="p-4 sm:p-6 lg:p-8 lg:pb-10">
                <div class="mx-auto max-w-7xl">
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>
