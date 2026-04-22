<script setup>
import { ref, computed } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import BrandLogo from '@/Components/Brand/BrandLogo.vue';
import ImpersonationBanner from '@/Components/ImpersonationBanner.vue';
import { adminMainNavItems, adminSettingsNavItems } from '@/config/adminSidebarNav.js';
import {
    Bars3Icon,
    HomeIcon,
    UsersIcon,
    BriefcaseIcon,
    ShieldCheckIcon,
    StarIcon,
    BookOpenIcon,
    VideoCameraIcon,
    LinkIcon,
    ChartBarIcon,
    Cog6ToothIcon,
    Squares2X2Icon,
    BanknotesIcon,
    CreditCardIcon,
    MegaphoneIcon,
    ArrowRightOnRectangleIcon,
    BellIcon,
    UserCircleIcon,
    XMarkIcon,
    ChevronDownIcon,
} from '@heroicons/vue/24/outline';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const sidebarOpen = ref(false);
const isSuperAdmin = computed(() => user.value?.roles?.includes('super_admin'));

const mainNavigation = computed(() =>
    adminMainNavItems({
        HomeIcon,
        UsersIcon,
        BriefcaseIcon,
        ShieldCheckIcon,
        StarIcon,
        BookOpenIcon,
        VideoCameraIcon,
        LinkIcon,
        Squares2X2Icon,
        ChartBarIcon,
        BanknotesIcon,
        CreditCardIcon,
        MegaphoneIcon,
    }),
);

const settingsNavigation = computed(() => {
    if (!isSuperAdmin.value) return [];

    return adminSettingsNavItems().flatMap((group) => {
        if (Array.isArray(group?.children)) return group.children;
        return group?.href ? [group] : [];
    });
});
const settingsOpen = ref(true);

const unreadNotificationsCount = computed(() => page.props.unread_notifications_count ?? 0);

const logout = () => {
    router.post('/logout');
};

const isActive = (href) => {
    if (!href || typeof href !== 'string') return false;

    const path = page.url.split('?')[0] ?? '';

    if (href === '/admin/library-manual-payments') {
        return path.startsWith('/admin/library-manual-payments');
    }

    if (href === '/admin/library') {
        if (path.startsWith('/admin/library-manual-payments')) return false;
        if (path === '/admin/library') return true;
        if (path.startsWith('/admin/library-categories')) return false;
        return path.startsWith('/admin/library/');
    }

    if (href.startsWith('/admin/library-categories')) {
        return path.startsWith('/admin/library-categories');
    }

    if (href === '/admin/videos') {
        return path === '/admin/videos' || path.startsWith('/admin/videos/');
    }

    if (href === '/admin/ads/analytics') {
        return path === '/admin/ads/analytics' || path.startsWith('/admin/ads/analytics/');
    }

    if (href === '/admin/ads') {
        if (path.startsWith('/admin/ads/analytics')) {
            return false;
        }

        return path === '/admin/ads' || path.startsWith('/admin/ads/');
    }

    return path.startsWith(href);
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
                class="fixed inset-0 z-40 bg-slate-950/45 backdrop-blur-[2px] lg:hidden"
                @click="sidebarOpen = false"
            />
        </Transition>

        <aside
            :class="[
                'fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-slate-200 bg-white/95 shadow-[8px_0_32px_-20px_rgba(15,23,42,0.18)] backdrop-blur transition-transform duration-300 lg:translate-x-0',
                sidebarOpen ? 'translate-x-0' : '-translate-x-full'
            ]"
        >
            <div class="relative border-b border-slate-200 px-5 pb-5 pt-6">
                <button
                    type="button"
                    class="absolute right-4 top-4 rounded-xl p-2 text-slate-500 hover:bg-slate-100 lg:hidden"
                    aria-label="Close menu"
                    @click="sidebarOpen = false"
                >
                    <XMarkIcon class="h-5 w-5" />
                </button>

                <Link href="/admin/dashboard" class="block pr-10 lg:pr-0" @click="sidebarOpen = false">
                    <BrandLogo
                        context="admin"
                        :show-name="false"
                        container-class="flex items-center"
                        mark-class="flex h-12 w-full max-w-[190px] items-center justify-start overflow-hidden rounded-none bg-transparent text-slate-900 shadow-none"
                        image-class="h-full w-full object-contain object-left"
                        initials-class="font-bold text-lg uppercase tracking-wide"
                    />
                </Link>

                <div class="mt-3">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">
                        Administration
                    </p>
                    <p class="mt-1 text-sm text-slate-500">
                        Manage users, providers, content, and platform activity.
                    </p>
                </div>
            </div>

            <nav class="flex-1 overflow-y-auto px-4 py-5">
                <p class="mb-3 px-3 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">
                    Main navigation
                </p>


             
                <div class="space-y-1.5">
                    <template v-for="item in mainNavigation" :key="item.href">
                        <a
                            v-if="item.external"
                            :href="item.href"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="group flex items-center gap-3 rounded-2xl px-3 py-3 text-sm font-medium text-slate-600 transition-all duration-150 hover:bg-slate-50 hover:text-slate-900"
                            @click="sidebarOpen = false"
                        >
                            <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-slate-100 text-slate-600 transition-colors group-hover:bg-blue-50 group-hover:text-blue-700">
                                <component :is="item.icon" class="h-5 w-5" />
                            </span>
                            <span class="truncate">{{ item.name }}</span>
                        </a>
                        <Link
                            v-else
                            :href="item.href"
                            :class="[
                                'group flex items-center gap-3 rounded-2xl px-3 py-3 text-sm font-medium transition-all duration-150',
                                isActive(item.href)
                                    ? 'bg-blue-600 text-white shadow-[0_10px_24px_-12px_rgba(37,99,235,0.65)]'
                                    : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                            ]"
                            @click="sidebarOpen = false"
                        >
                            <span
                                :class="[
                                    'inline-flex h-10 w-10 items-center justify-center rounded-2xl transition-colors',
                                    isActive(item.href)
                                        ? 'bg-white/18 text-white'
                                        : 'bg-slate-100 text-slate-600 group-hover:bg-blue-50 group-hover:text-blue-700'
                                ]"
                            >
                                <component :is="item.icon" class="h-5 w-5" />
                            </span>
                            <span class="truncate">{{ item.name }}</span>
                        </Link>
                    </template>
                </div>

                <div v-if="settingsNavigation.length" class="mt-6 border-t border-slate-200 pt-5">
                    <p class="mb-3 px-3 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">
                        Settings
                    </p>

                    <div class="space-y-1.5">
                        <Link
                            v-for="child in settingsNavigation"
                            :key="child.href"
                            :href="child.href"
                            :class="[
                                'group flex items-center gap-3 rounded-2xl px-3 py-3 text-sm font-medium transition-all duration-150',
                                isActive(child.href)
                                    ? 'bg-blue-600 text-white shadow-[0_10px_24px_-12px_rgba(37,99,235,0.65)]'
                                    : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                            ]"
                            @click="sidebarOpen = false"
                        >
                            <span
                                :class="[
                                    'inline-flex h-10 w-10 items-center justify-center rounded-2xl transition-colors',
                                    isActive(child.href)
                                        ? 'bg-white/18 text-white'
                                        : 'bg-slate-100 text-slate-600 group-hover:bg-blue-50 group-hover:text-blue-700'
                                ]"
                            >
                                <Cog6ToothIcon class="h-5 w-5" />
                            </span>
                            <span class="truncate">{{ child.name }}</span>
                        </Link>
                    </div>
                </div>
            </nav>
        </aside>

        <div class="lg:pl-72">
            <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/90 backdrop-blur">
                <div class="flex h-20 items-center justify-between px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center gap-3">
                        <button
                            @click="sidebarOpen = true"
                            class="rounded-2xl p-2.5 text-slate-500 hover:bg-slate-100 lg:hidden"
                        >
                            <Bars3Icon class="h-6 w-6" />
                        </button>

                        <div class="hidden sm:block">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                                Admin portal
                            </p>
                            <h1 class="text-lg font-semibold text-slate-900">
                                Welcome back
                            </h1>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 sm:gap-3">
                        <Link
                            href="/admin/notifications"
                            class="relative inline-flex h-11 w-11 items-center justify-center rounded-2xl text-slate-500 transition hover:bg-slate-100"
                            title="Notifications"
                            aria-label="Notifications"
                        >
                            <BellIcon class="h-5 w-5" />
                            <span
                                v-if="unreadNotificationsCount > 0"
                                class="absolute right-3 top-3 h-2.5 w-2.5 rounded-full bg-rose-500"
                            />
                        </Link>

                        <Link
                            href="/admin/profile"
                            class="inline-flex h-11 w-11 items-center justify-center rounded-2xl text-slate-500 transition hover:bg-slate-100"
                            title="Profile"
                            aria-label="Profile"
                        >
                            <UserCircleIcon class="h-6 w-6" />
                        </Link>

                        <Link
                            href="/"
                            class="hidden rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 sm:inline-flex"
                        >
                            View Site
                        </Link>
                        <Link
                            href="/admin/dv-lottery"
                            class="hidden rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 sm:inline-flex"
                        >
                            DV Lottery
                        </Link>

                        <button
                            type="button"
                            class="inline-flex h-11 w-11 items-center justify-center rounded-2xl text-slate-500 transition hover:bg-slate-100"
                            title="Sign out"
                            aria-label="Sign out"
                            @click="logout"
                        >
                            <ArrowRightOnRectangleIcon class="h-5 w-5" />
                        </button>
                    </div>
                </div>
            </header>

            <main class="p-4 sm:p-6 lg:p-8">
                <ImpersonationBanner />
                <slot />
            </main>
        </div>
    </div>
</template>