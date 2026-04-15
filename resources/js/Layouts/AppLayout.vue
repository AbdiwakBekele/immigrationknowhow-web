<script setup>
import { ref, computed, onMounted } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import BrandLogo from '@/Components/Brand/BrandLogo.vue';
import { Transition } from 'vue';
import {
    Bars3Icon,
    HomeIcon,
    MagnifyingGlassIcon,
    ChatBubbleLeftRightIcon,
    BookOpenIcon,
    ClipboardDocumentListIcon,
    VideoCameraIcon,
    StarIcon,
    ArrowRightOnRectangleIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const unreadMessages = computed(() => page.props.unreadMessages || 0);
const userLogoSrc = computed(() => {
    const u = page.props.branding?.site_logo_url;
    return typeof u === 'string' && u.trim() !== '' ? u : '/images/logo.svg';
});

const sidebarOpen = ref(false);
const scrolled = ref(false);

const navigation = [
    { name: 'Home', href: '/', icon: HomeIcon },
    { name: 'Find Services', href: '/providers', icon: MagnifyingGlassIcon },
    { name: 'Library', href: '/library', icon: BookOpenIcon },
    { name: 'Videos', href: '/videos', icon: VideoCameraIcon },
];

const userNavigation = [
    { name: 'Dashboard', href: '/dashboard', icon: HomeIcon },
    { name: 'Find Providers', href: '/providers', icon: MagnifyingGlassIcon },
    { name: 'Contracts', href: '/contracts', icon: ClipboardDocumentListIcon },
    { name: 'Messages', href: '/messages', icon: ChatBubbleLeftRightIcon },
    { name: 'Library', href: '/library', icon: BookOpenIcon },
    { name: 'Videos', href: '/videos', icon: VideoCameraIcon },
    { name: 'Reviews', href: '/reviews', icon: StarIcon },
];

onMounted(() => {
    window.addEventListener('scroll', () => {
        scrolled.value = window.scrollY > 20;
    });
});

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

const userAvatarSrc = computed(() => {
    const u = user.value;
    if (!u) return '';
    const candidate = (u.avatar_url || u.avatar || '').trim();
    if (!candidate) return '';
    if (candidate.startsWith('http://') || candidate.startsWith('https://') || candidate.startsWith('/')) {
        return candidate;
    }
    return `/storage/${candidate}`;
});

const hasUserAvatar = computed(() => Boolean(userAvatarSrc.value));
const userAvatarInitial = computed(() => {
    const first = (user.value?.first_name || '').trim();
    const last = (user.value?.last_name || '').trim();
    if (first) return first.charAt(0).toUpperCase();
    if (last) return last.charAt(0).toUpperCase();
    return 'U';
});
</script>

<template>
    <!-- Authenticated: match AdminLayout shell -->
    <div v-if="user" class="min-h-screen bg-slate-100">
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
            class="fixed inset-y-0 left-0 z-50 flex min-h-screen w-64 flex-col border-r border-slate-200/90 bg-gradient-to-b from-white to-slate-50/90 shadow-[4px_0_32px_-12px_rgba(15,23,42,0.12)] backdrop-blur-sm transform transition-transform duration-300 ease-out lg:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
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
                <Link href="/dashboard" class="block w-full pr-8 lg:pr-0" @click="sidebarOpen = false">
                    <BrandLogo
                        context="site"
                        :mark-src="userLogoSrc"
                        :show-name="false"
                        container-class="flex items-center"
                        mark-class="flex h-11 w-full max-w-[180px] items-center justify-start overflow-hidden rounded-none border-0 bg-transparent text-slate-900 shadow-none"
                        image-class="h-full w-full object-contain object-left"
                        initials-class="font-bold text-lg uppercase tracking-wide"
                    />
                </Link>
                <p class="pl-0.5 text-[11px] font-medium uppercase tracking-wider text-slate-400">
                    Your account
                </p>
            </div>

            <nav class="min-h-0 flex-1 space-y-1 overflow-y-auto px-3 pb-4 pt-5">
                <p class="px-3 pb-2 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-400">
                    Menu
                </p>
                <Link
                    v-for="item in userNavigation"
                    :key="item.name + item.href"
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
                    href="/profile"
                    class="flex items-center gap-3 rounded-xl border border-slate-200/80 bg-white px-3 py-2.5 text-sm text-slate-700 shadow-sm transition-colors hover:border-slate-300 hover:bg-slate-50"
                    aria-label="Profile"
                    @click="sidebarOpen = false"
                >
                    <img
                        v-if="hasUserAvatar"
                        :src="userAvatarSrc"
                        alt="User profile photo"
                        class="h-9 w-9 shrink-0 rounded-full object-cover ring-1 ring-slate-200"
                    />
                    <div
                        v-else
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-600 text-xs font-bold text-white ring-1 ring-primary-500/50"
                        aria-hidden="true"
                    >
                        {{ userAvatarInitial }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold text-slate-900">
                            {{ user?.first_name?.trim() || 'Profile' }}
                        </p>
                        <p class="truncate text-xs text-slate-500">Account</p>
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
                            href="/messages"
                            class="relative inline-flex rounded-lg p-2 text-slate-500 transition-colors hover:bg-slate-100"
                            title="Messages"
                            aria-label="Messages"
                        >
                            <ChatBubbleLeftRightIcon class="h-6 w-6" />
                            <span
                                v-if="unreadMessages > 0"
                                class="absolute -top-0.5 -right-0.5 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-semibold text-white ring-2 ring-white"
                            >
                                {{ unreadMessages > 9 ? '9+' : unreadMessages }}
                            </span>
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

    <!-- Guest: marketing-style top nav -->
    <div v-else class="min-h-screen bg-slate-50">
        <nav
            :class="[
                'fixed top-0 right-0 z-30 transition-all duration-300 left-0',
                scrolled
                    ? 'bg-white/95 backdrop-blur-md shadow-sm border-b border-slate-200/50'
                    : 'bg-transparent',
            ]"
        >
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-16 items-center justify-between">
                    <div class="flex items-center">
                        <Link
                            href="/"
                            class="block rounded-lg outline-none ring-offset-2 transition-opacity hover:opacity-95 focus-visible:ring-2 focus-visible:ring-primary-400"
                        >
                            <BrandLogo
                                context="site"
                                :mark-src="userLogoSrc"
                                :show-name="false"
                                container-class="flex items-center"
                                mark-class="flex min-h-[2.5rem] w-full max-w-[180px] items-center justify-center overflow-visible rounded-xl border border-slate-200/80 bg-white px-2 py-1.5 shadow-sm"
                                image-class="block h-8 w-auto max-w-full object-contain object-left"
                                initials-class="text-sm font-bold uppercase tracking-wide text-slate-600"
                            />
                        </Link>
                    </div>

                    <div class="hidden items-center space-x-1 md:flex">
                        <Link
                            v-for="item in navigation"
                            :key="item.name"
                            :href="item.href"
                            :class="[
                                'rounded-lg px-4 py-2 text-sm font-medium transition-all duration-200',
                                $page.url === item.href
                                    ? 'bg-sky-50 text-sky-700'
                                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900',
                            ]"
                        >
                            {{ item.name }}
                        </Link>
                    </div>

                    <div class="ml-auto flex items-center space-x-4">
                        <Link
                            href="/login"
                            class="hidden px-4 py-2 text-sm font-medium text-slate-700 transition-colors hover:text-slate-900 sm:inline-flex"
                        >
                            Sign in
                        </Link>
                        <Link
                            href="/register"
                            class="inline-flex items-center rounded-xl bg-gradient-to-r from-sky-500 to-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-sky-500/25 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-xl hover:shadow-sky-500/30"
                        >
                            Get Started
                        </Link>
                    </div>
                </div>
            </div>
        </nav>

        <main class="pt-16">
            <slot />
        </main>
    </div>
</template>
