<script setup>
import { ref, computed } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import { Transition } from 'vue';
import BrandLogo from '@/Components/Brand/BrandLogo.vue';
import {
    HomeIcon,
    InboxIcon,
    ChatBubbleLeftRightIcon,
    StarIcon,
    UserCircleIcon,
    ShieldCheckIcon,
    ChartBarIcon,
    Bars3Icon,
    BellIcon,
    ArrowRightOnRectangleIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';

const page = usePage();
const user = computed(() => page.props.auth?.user);

const providerLogoSrc = computed(() => {
    const u = page.props.branding?.site_logo_url;
    return typeof u === 'string' && u.trim() !== '' ? u : '/images/logo.svg';
});

const sidebarOpen = ref(false);

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
    const path = page.url.split('?')[0] ?? '';
    if (path === href) {
        return true;
    }
    return path.startsWith(`${href}/`);
};
</script>

<template>
    <div class="min-h-screen bg-slate-100">
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

        <aside
            :class="[
                'fixed inset-y-0 left-0 z-50 flex min-h-screen w-64 flex-col border-r border-slate-200/90 bg-gradient-to-b from-white to-slate-50/90 shadow-[4px_0_32px_-12px_rgba(15,23,42,0.12)] backdrop-blur-sm transform transition-transform duration-300 ease-out lg:translate-x-0',
                sidebarOpen ? 'translate-x-0' : '-translate-x-full',
            ]"
        >
            <div class="relative flex shrink-0 flex-col gap-1 border-b border-slate-200/80 px-4 pb-4 pt-5">
                <button
                    type="button"
                    class="absolute right-3 top-4 rounded-lg p-1.5 text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-800 lg:hidden"
                    aria-label="Close menu"
                    @click="sidebarOpen = false"
                >
                    <XMarkIcon class="h-5 w-5" />
                </button>
                <Link href="/provider/dashboard" class="block w-full pr-8 lg:pr-0" @click="sidebarOpen = false">
                    <BrandLogo
                        context="site"
                        :mark-src="providerLogoSrc"
                        :show-name="false"
                        container-class="flex items-center"
                        mark-class="flex h-11 w-full max-w-[180px] items-center justify-start overflow-hidden rounded-none border-0 bg-transparent text-slate-900 shadow-none"
                        image-class="h-full w-full object-contain object-left"
                        initials-class="font-bold text-lg uppercase tracking-wide"
                    />
                </Link>
                <p class="pl-0.5 text-[11px] font-medium uppercase tracking-wider text-slate-400">
                    Provider portal
                </p>
            </div>

            <nav class="min-h-0 flex-1 space-y-1 overflow-y-auto px-3 pb-4 pt-5">
                <p class="px-3 pb-2 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-400">
                    Menu
                </p>
                <Link
                    v-for="item in navigation"
                    :key="item.href"
                    :href="item.href"
                    :class="[
                        'group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-all duration-150',
                        isActive(item.href)
                            ? 'bg-sky-600 text-white shadow-sm shadow-sky-600/20'
                            : 'text-slate-600 hover:bg-white hover:text-slate-900 hover:shadow-sm',
                    ]"
                    @click="sidebarOpen = false"
                >
                    <span
                        :class="[
                            'inline-flex rounded-lg p-1.5 transition-colors',
                            isActive(item.href)
                                ? 'bg-white/20 text-white'
                                : 'bg-slate-100 text-slate-600 group-hover:bg-sky-50 group-hover:text-sky-700',
                        ]"
                    >
                        <component :is="item.icon" class="h-[18px] w-[18px] flex-shrink-0" />
                    </span>
                    {{ item.name }}
                </Link>
            </nav>

            <div class="shrink-0 border-t border-slate-200/80 p-3">
                <Link
                    href="/provider/profile"
                    class="flex items-center gap-3 rounded-xl border border-slate-200/80 bg-white px-3 py-2.5 text-sm text-slate-700 shadow-sm transition-colors hover:border-slate-300 hover:bg-slate-50"
                    @click="sidebarOpen = false"
                >
                    <UserCircleIcon class="h-9 w-9 flex-shrink-0 rounded-full text-slate-400" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold text-slate-900">
                            {{ user?.full_name || user?.first_name || 'Profile' }}
                        </p>
                        <p class="truncate text-xs text-slate-500">Provider account</p>
                    </div>
                </Link>
            </div>
        </aside>

        <div class="lg:pl-64">
            <header class="sticky top-0 z-30 border-b border-slate-200 bg-white">
                <div class="flex h-16 items-center justify-between px-4 sm:px-6">
                    <button
                        type="button"
                        class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:hidden"
                        aria-label="Open menu"
                        @click="sidebarOpen = true"
                    >
                        <Bars3Icon class="h-6 w-6" />
                    </button>

                    <div class="flex-1 lg:flex-none"></div>

                    <div class="flex items-center gap-2 sm:gap-3">
                        <Link
                            :href="route('provider.notifications.index')"
                            class="relative inline-flex rounded-lg p-2 text-slate-500 transition-colors hover:bg-slate-100"
                            title="Notifications"
                            aria-label="Notifications"
                        >
                            <BellIcon class="h-6 w-6" />
                            <span
                                v-if="unreadNotificationsCount > 0"
                                class="absolute top-1.5 right-1.5 h-2 w-2 rounded-full bg-rose-500"
                            />
                        </Link>
                        <Link
                            href="/provider/profile"
                            title="Profile"
                            aria-label="Profile"
                            class="inline-flex items-center justify-center rounded-lg p-2 text-slate-500 transition-colors hover:bg-slate-100"
                        >
                            <UserCircleIcon class="h-6 w-6" />
                        </Link>
                        <Link
                            href="/"
                            class="hidden items-center gap-2 rounded-lg px-3 py-1.5 text-sm text-slate-600 transition-colors hover:bg-slate-100 sm:inline-flex"
                        >
                            View Site
                        </Link>
                        <button
                            type="button"
                            title="Sign out"
                            aria-label="Sign out"
                            class="inline-flex items-center justify-center rounded-lg p-2 text-slate-500 transition-colors hover:bg-slate-100"
                            @click="logout"
                        >
                            <ArrowRightOnRectangleIcon class="h-6 w-6" />
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
