<script setup>
import { ref, computed } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import BrandLogo from '@/Components/Brand/BrandLogo.vue';
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
    ArrowRightOnRectangleIcon,
    BellIcon,
    UserCircleIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const sidebarOpen = ref(false);
const isSuperAdmin = computed(() => user.value?.roles?.includes('super_admin'));

/** Flat list — Library is one link (no Videos / no “Library items” sub-rows). */
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
    }),
);

const settingsNavigation = computed(() => (isSuperAdmin.value ? adminSettingsNavItems() : []));

const logout = () => {
    router.post('/logout');
};

const isActive = (href) => {
    const path = page.url.split('?')[0] ?? '';
    if (href === '/admin/library-manual-payments') {
        return path.startsWith('/admin/library-manual-payments');
    }
    if (href === '/admin/library') {
        if (path.startsWith('/admin/library-manual-payments')) {
            return false;
        }
        if (path === '/admin/library') {
            return true;
        }
        if (path.startsWith('/admin/library-categories')) {
            return false;
        }
        return path.startsWith('/admin/library/');
    }
    if (href.startsWith('/admin/library-categories')) {
        return path.startsWith('/admin/library-categories');
    }
    if (href === '/admin/videos') {
        return path === '/admin/videos' || path.startsWith('/admin/videos/');
    }
    return path.startsWith(href);
};

const unreadNotificationsCount = computed(
    () => page.props.unread_notifications_count ?? 0,
);
</script>

<template>
    <div class="min-h-screen bg-slate-100">
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
            data-admin-sidebar="flat-main-nav"
            :class="[
                'fixed inset-y-0 left-0 z-50 flex min-h-screen w-64 flex-col border-r border-slate-200/90 bg-gradient-to-b from-white to-slate-50/90 shadow-[4px_0_32px_-12px_rgba(15,23,42,0.12)] backdrop-blur-sm transform transition-transform duration-300 ease-out lg:translate-x-0',
                sidebarOpen ? 'translate-x-0' : '-translate-x-full'
            ]"
        >
            <!-- Logo -->
            <div class="relative flex shrink-0 flex-col gap-1 border-b border-slate-200/80 px-4 pb-4 pt-5">
                <button
                    type="button"
                    class="absolute right-3 top-4 rounded-lg p-1.5 text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-800 lg:hidden"
                    aria-label="Close menu"
                    @click="sidebarOpen = false"
                >
                    <XMarkIcon class="h-5 w-5" />
                </button>
                <Link href="/admin/dashboard" class="block w-full pr-8 lg:pr-0" @click="sidebarOpen = false">
                    <BrandLogo
                        context="admin"
                        :show-name="false"
                        container-class="flex items-center"
                        mark-class="flex h-11 w-full max-w-[180px] items-center justify-start overflow-hidden rounded-none bg-transparent text-slate-900 shadow-none"
                        image-class="h-full w-full object-contain object-left"
                        initials-class="font-bold text-lg uppercase tracking-wide"
                    />
                </Link>
                <p class="pl-0.5 text-[11px] font-medium uppercase tracking-wider text-slate-400">
                    Administration
                </p>
            </div>

            <!-- Navigation -->
            <nav class="min-h-0 flex-1 space-y-1 overflow-y-auto px-3 pb-4 pt-5">
                <p class="px-3 pb-2 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-400">
                    Menu
                </p>
                <Link
                    v-for="item in mainNavigation"
                    :key="item.href"
                    :href="item.href"
                    :class="[
                        'group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-all duration-150',
                        isActive(item.href)
                            ? 'bg-sky-600 text-white shadow-sm shadow-sky-600/20'
                            : 'text-slate-600 hover:bg-white hover:text-slate-900 hover:shadow-sm'
                    ]"
                    @click="sidebarOpen = false"
                >
                    <span
                        :class="[
                            'inline-flex rounded-lg p-1.5 transition-colors',
                            isActive(item.href)
                                ? 'bg-white/20 text-white'
                                : 'bg-slate-100 text-slate-600 group-hover:bg-sky-50 group-hover:text-sky-700'
                        ]"
                    >
                        <component :is="item.icon" class="h-[18px] w-[18px] flex-shrink-0" />
                    </span>
                    {{ item.name }}
                </Link>

                <div v-if="settingsNavigation.length" class="mt-3 space-y-1 border-t border-slate-200/80 pt-3">
                    <div class="flex items-center gap-3 px-3 pb-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <span class="inline-flex rounded-lg bg-slate-100 p-1.5 text-slate-600">
                            <Cog6ToothIcon class="h-4 w-4 flex-shrink-0" />
                        </span>
                        Settings
                    </div>
                    <Link
                        v-for="child in settingsNavigation"
                        :key="child.href"
                        :href="child.href"
                        :class="[
                            'ml-1 flex items-center rounded-xl px-3 py-2 text-sm font-medium transition-all duration-150',
                            isActive(child.href)
                                ? 'bg-sky-600 text-white shadow-sm shadow-sky-600/25'
                                : 'text-slate-600 hover:bg-white hover:text-slate-900 hover:shadow-sm'
                        ]"
                        @click="sidebarOpen = false"
                    >
                        {{ child.name }}
                    </Link>
                </div>
            </nav>

        </aside>

        <!-- Main content -->
        <div class="lg:pl-64">
            <!-- Top bar -->
            <header class="sticky top-0 z-30 bg-white border-b border-slate-200">
                <div class="flex h-16 items-center justify-between px-4 sm:px-6">
                    <button
                        @click="sidebarOpen = true"
                        class="lg:hidden p-2 rounded-lg text-slate-500 hover:bg-slate-100"
                    >
                        <Bars3Icon class="h-6 w-6" />
                    </button>

                    <div class="flex-1 lg:flex-none"></div>

                    <div class="flex items-center gap-2 sm:gap-3">
                        <Link
                            href="/admin/notifications"
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
                            href="/admin/profile"
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
