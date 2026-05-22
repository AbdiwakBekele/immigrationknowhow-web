<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import BrandLogo from '@/Components/Brand/BrandLogo.vue';
import ImpersonationBanner from '@/Components/ImpersonationBanner.vue';
import SiteSharePanel from '@/Components/layout/SiteSharePanel.vue';
import SiteLegalFooter from '@/Components/legal/SiteLegalFooter.vue';
import { Transition } from 'vue';
import {
    Bars3Icon,
    HomeIcon,
    MagnifyingGlassIcon,
    ChatBubbleLeftRightIcon,
    BookOpenIcon,
    ClipboardDocumentListIcon,
    VideoCameraIcon,
    ShoppingCartIcon,
    StarIcon,
    SparklesIcon,
    MegaphoneIcon,
    ChartBarIcon,
    UserGroupIcon,
    ArrowRightOnRectangleIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';

const page = usePage();
const user = computed(() => page.props.auth?.user);
/** Spatie roles; advertisers are not necessarily `role:user`, so `/dashboard` etc. would 403. */
const roleNames = computed(() => {
    const roles = user.value?.roles;
    return Array.isArray(roles) ? roles : [];
});
const hasSeekerPortal = computed(() => roleNames.value.includes('user'));
const hasAdvertiserRole = computed(
    () => Boolean(user.value?.is_advertiser) || roleNames.value.includes('advertiser'),
);
/** Advertiser-only accounts: point nav at `advertiser.*` routes, not seeker middleware. */
const useAdvertiserNav = computed(() => hasAdvertiserRole.value && !hasSeekerPortal.value);
const primaryHomeHref = computed(() =>
    useAdvertiserNav.value ? route('advertiser.dashboard') : route('dashboard'),
);
const dvLottery = computed(() => page.props.dvLottery ?? {});
/** Live override so the sidebar badge can update without a full navigation (same count as server share). */
const liveUnreadOverride = ref(null);

const unreadMessages = computed(() => {
    if (typeof liveUnreadOverride.value === 'number' && !Number.isNaN(liveUnreadOverride.value)) {
        return liveUnreadOverride.value;
    }
    return Number(page.props.unreadMessages ?? 0) || 0;
});

watch(
    () => page.props.unreadMessages,
    () => {
        liveUnreadOverride.value = null;
    },
);
const unreadMessagesLabel = computed(() => {
    const n = unreadMessages.value;
    if (n < 1) {
        return '';
    }
    if (n > 9999) {
        return '9999+';
    }
    return String(n);
});

const libraryCartCount = computed(() => Number(page.props.library_cart_count ?? 0) || 0);

const cartCountLabel = computed(() => {
    const n = libraryCartCount.value;
    if (n < 1) {
        return '';
    }
    if (n > 99) {
        return '99+';
    }
    return String(n);
});
const userLogoSrc = computed(() => {
    const u = page.props.branding?.site_logo_url;
    return typeof u === 'string' && u.trim() !== '' ? u : '/images/logo.svg';
});

const sidebarOpen = ref(false);
const scrolled = ref(false);

/** Use Ziggy named routes (same pattern as Find Services / marketplace) so Library, Videos, etc. resolve correctly everywhere. */
const navigation = computed(() => [
    { name: 'Home', href: route('home'), icon: HomeIcon },
    { name: 'Find Services', href: route('marketplace.index'), icon: MagnifyingGlassIcon },
    { name: 'Library', href: route('library.index'), icon: BookOpenIcon },
    { name: 'Videos', href: route('videos.index'), icon: VideoCameraIcon },
    ...(dvLottery.value.show_in_menu
        ? [{
            name: 'DV Lottery',
            href: route('user.dv-lottery.index'),
            icon: BookOpenIcon,
        }]
        : []),
]);

const seekerUserNavigation = computed(() => {
    const items = [
        { name: 'Dashboard', href: route('dashboard'), icon: HomeIcon },
        { name: 'Find Providers', href: route('marketplace.index'), icon: MagnifyingGlassIcon },
        { name: 'Contracts', href: '/contracts', icon: ClipboardDocumentListIcon },
        { name: 'Messages', href: '/messages', icon: ChatBubbleLeftRightIcon },
        { name: 'My Ads', href: route('user.ads.index'), icon: MegaphoneIcon },
        { name: 'Ad Analytics', href: route('user.ads.analytics'), icon: ChartBarIcon },
        { name: 'My Library', href: route('library.my'), icon: BookOpenIcon },
        { name: 'Videos', href: route('videos.index'), icon: VideoCameraIcon },
        { name: 'Reviews', href: route('reviews.index'), icon: StarIcon },
        { name: 'AI Assistant', href: route('user.ai-assistant.index'), icon: SparklesIcon },
    ];
    if (dvLottery.value.show_in_menu) {
        items.push({ name: 'DV Lottery', href: route('user.dv-lottery.index'), icon: BookOpenIcon });
    }
    items.push({ name: 'Community', href: '/community', icon: UserGroupIcon });
    return items;
});

const advertiserUserNavigation = computed(() => [
    { name: 'Dashboard', href: route('advertiser.dashboard'), icon: HomeIcon },
    { name: 'Find Providers', href: route('marketplace.index'), icon: MagnifyingGlassIcon },
    { name: 'My Ads', href: route('advertiser.ads.index'), icon: MegaphoneIcon },
    { name: 'Ad Analytics', href: route('advertiser.analytics'), icon: ChartBarIcon },
    { name: 'Library', href: route('library.index'), icon: BookOpenIcon },
    { name: 'Videos', href: route('videos.index'), icon: VideoCameraIcon },
    { name: 'Community', href: '/community', icon: UserGroupIcon },
]);

const userNavigation = computed(() =>
    useAdvertiserNav.value ? advertiserUserNavigation.value : seekerUserNavigation.value,
);


const readXsrfCookie = () => {
    const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    return m ? decodeURIComponent(m[1]) : '';
};

const pollUnreadMessages = async () => {
    try {
        const res = await fetch('/messages/unread-count', {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': readXsrfCookie(),
            },
            credentials: 'same-origin',
        });
        if (!res.ok) {
            return;
        }
        const data = await res.json();
        if (typeof data.count === 'number') {
            liveUnreadOverride.value = data.count;
        }
    } catch {
        /* ignore */
    }
};

let unreadPollTimer = null;

const onVisibilityForUnread = () => {
    if (document.visibilityState === 'visible') {
        pollUnreadMessages();
    }
};

onMounted(() => {
    window.addEventListener('scroll', () => {
        scrolled.value = window.scrollY > 20;
    });

    if (hasSeekerPortal.value) {
        pollUnreadMessages();
        unreadPollTimer = window.setInterval(() => {
            if (document.visibilityState === 'visible') {
                pollUnreadMessages();
            }
        }, 12000);
        document.addEventListener('visibilitychange', onVisibilityForUnread);
    }
});

onUnmounted(() => {
    if (unreadPollTimer !== null) {
        clearInterval(unreadPollTimer);
    }
    document.removeEventListener('visibilitychange', onVisibilityForUnread);
});

const logout = () => {
    router.post('/logout');
};

const normalizePathname = (href) => {
    let target = typeof href === 'string' ? href : '';
    if (target.startsWith('http://') || target.startsWith('https://')) {
        try {
            target = new URL(target).pathname;
        } catch {
            /* keep target as-is */
        }
    }
    return target;
};

const isActive = (href) => {
    const path = page.url.split('?')[0] ?? '';
    const target = normalizePathname(href);
    if (path === target) {
        return true;
    }
    if (!path.startsWith(`${target}/`)) {
        return false;
    }
    // If a more specific sidebar route matches, don't keep the parent highlighted.
    // Example: /ads should not stay active on /ads/analytics.
    const hasMoreSpecificMatch = userNavigation.value.some((item) => {
        const candidate = normalizePathname(item.href);
        if (!candidate || candidate === target) {
            return false;
        }
        if (!candidate.startsWith(`${target}/`)) {
            return false;
        }
        return path === candidate || path.startsWith(`${candidate}/`);
    });
    if (hasMoreSpecificMatch) {
        return false;
    }
    if (target === '/library/my') {
        return path === '/library/my' || /^\/library\/[^/]+(?:\/.*)?$/.test(path);
    }
    // "Library" links to /library but cart/checkout live under /library/cart, /library/purchase/… — don't highlight Library there.
    if (target === '/library') {
        const rest = path.slice('/library/'.length);
        const first = rest.split('/')[0] ?? '';
        if (first === 'cart' || first === 'purchase') {
            return false;
        }
    }

    return true;
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
const isServiceNeeder = computed(() => hasSeekerPortal.value);
const showEmailVerificationBanner = computed(() => {
    return Boolean(user.value) && isServiceNeeder.value && !user.value?.email_verified_at;
});

const sendingVerificationEmail = ref(false);
const sendVerificationEmail = () => {
    if (sendingVerificationEmail.value) {
        return;
    }

    sendingVerificationEmail.value = true;
    router.post(route('verification.send'), {}, {
        preserveScroll: true,
        onFinish: () => {
            sendingVerificationEmail.value = false;
        },
    });
};
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
                <Link :href="primaryHomeHref" class="block w-full pr-8 lg:pr-0" @click="sidebarOpen = false">
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
                <template v-for="item in userNavigation" :key="item.name + item.href">
                    <a
                        v-if="item.external"
                        :href="item.href"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="group flex w-full items-center gap-3 rounded-2xl px-3 py-3 text-sm font-medium text-slate-600 transition-all duration-150 hover:bg-slate-50 hover:text-slate-900"
                        @click="sidebarOpen = false"
                    >
                        <span class="relative inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-slate-100 text-slate-600 transition-colors group-hover:bg-blue-50 group-hover:text-blue-700">
                            <component :is="item.icon" class="h-5 w-5" />
                        </span>
                        <span class="min-w-0 flex-1 truncate">{{ item.name }}</span>
                    </a>
                    <Link
                        v-else
                        :href="item.href"
                        :aria-label="
                            item.name === 'Messages' && unreadMessages > 0
                                ? `${item.name}, ${unreadMessages} unread`
                                : item.name === 'Cart' && libraryCartCount > 0
                                  ? `${item.name}, ${libraryCartCount} items`
                                  : item.name
                        "
                        :class="[
                            'group flex w-full items-center gap-3 rounded-2xl px-3 py-3 text-sm font-medium transition-all duration-150',
                            isActive(item.href)
                                ? 'bg-blue-600 text-white shadow-[0_10px_24px_-12px_rgba(37,99,235,0.65)]'
                                : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900',
                        ]"
                        @click="sidebarOpen = false"
                    >
                        <span
                            :class="[
                                'relative inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl transition-colors',
                                isActive(item.href)
                                    ? 'bg-white/20 text-white'
                                    : 'bg-slate-100 text-slate-600 group-hover:bg-blue-50 group-hover:text-blue-700',
                            ]"
                        >
                            <component :is="item.icon" class="h-5 w-5" />
                            <span
                                v-if="item.name === 'Messages' && unreadMessages > 0"
                                class="absolute -right-1 -top-1 z-10 flex min-h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[9px] font-bold leading-none text-white shadow-sm ring-2 ring-white tabular-nums"
                            >
                                {{ unreadMessagesLabel }}
                            </span>
                            <span
                                v-if="item.name === 'Cart' && libraryCartCount > 0"
                                class="absolute -right-1 -top-1 z-10 flex min-h-4 min-w-[1.1rem] items-center justify-center rounded-full bg-blue-600 px-1 text-[9px] font-bold leading-none text-white shadow-sm ring-2 ring-white tabular-nums"
                            >
                                {{ cartCountLabel }}
                            </span>
                        </span>
                        <span class="min-w-0 flex-1 truncate">{{ item.name }}</span>
                    </Link>
                </template>

            </nav>

            <div class="shrink-0 border-t border-slate-200/80 p-3">
                <Link
                    :href="hasSeekerPortal ? '/profile' : primaryHomeHref"
                    class="flex items-center gap-3 rounded-xl border border-slate-200/80 bg-white px-3 py-2.5 text-sm text-slate-700 shadow-sm transition-colors hover:border-slate-300 hover:bg-slate-50"
                    :aria-label="hasSeekerPortal ? 'Profile' : 'Account'"
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
                        <p class="truncate text-xs text-slate-500">
                            {{ hasSeekerPortal ? 'Account' : 'Advertiser account' }}
                        </p>
                    </div>
                </Link>
            </div>
        </aside>

        <div class="flex min-h-screen flex-col lg:pl-64">
            <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/90 backdrop-blur">
                <div class="flex h-20 items-center justify-between px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            class="rounded-2xl p-2.5 text-slate-500 hover:bg-slate-100 lg:hidden"
                            aria-label="Open menu"
                            @click="sidebarOpen = true"
                        >
                            <Bars3Icon class="h-6 w-6" />
                        </button>

                        <div class="hidden sm:block">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                                {{ useAdvertiserNav ? 'Advertiser portal' : 'User portal' }}
                            </p>
                            <h1 class="text-lg font-semibold text-slate-900">
                                Welcome back
                            </h1>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 sm:gap-3">
                        <Link
                            v-if="hasSeekerPortal"
                            href="/messages"
                            class="relative inline-flex rounded-lg p-2 text-slate-500 transition-colors hover:bg-slate-100"
                            title="Messages"
                            aria-label="Messages"
                        >
                            <ChatBubbleLeftRightIcon class="h-6 w-6" />
                            <span
                                v-if="unreadMessages > 0"
                                class="absolute -top-0.5 -right-0.5 flex min-h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-rose-500 px-1.5 text-[10px] font-semibold leading-none text-white ring-2 ring-white tabular-nums"
                            >
                                {{ unreadMessagesLabel }}
                            </span>
                        </Link>
                        <Link
                            :href="route('library.cart')"
                            class="relative inline-flex rounded-lg p-2 text-slate-500 transition-colors hover:bg-slate-100"
                            title="Cart"
                            :aria-label="libraryCartCount > 0 ? `Cart, ${libraryCartCount} items` : 'Cart'"
                        >
                            <ShoppingCartIcon class="h-6 w-6" />
                            <span
                                v-if="libraryCartCount > 0"
                                class="absolute -top-0.5 -right-0.5 flex min-h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-sky-600 px-1.5 text-[10px] font-semibold leading-none text-white ring-2 ring-white tabular-nums"
                            >
                                {{ cartCountLabel }}
                            </span>
                        </Link>
                        <Link
                            :href="hasSeekerPortal ? route('library.my') : route('library.index')"
                            class="inline-flex items-center justify-center rounded-lg p-2 text-slate-500 transition-colors hover:bg-slate-100"
                            :title="hasSeekerPortal ? 'My Library' : 'Library'"
                            :aria-label="hasSeekerPortal ? 'My Library' : 'Library'"
                        >
                            <BookOpenIcon class="h-6 w-6" />
                        </Link>
                        <SiteSharePanel variant="icon" menu-align="right" />
                        <Link
                            href="/"
                            class="inline-flex items-center justify-center rounded-lg p-2 text-slate-500 transition-colors hover:bg-slate-100"
                            title="View Site"
                            aria-label="View Site"
                        >
                            <HomeIcon class="h-6 w-6" />
                        </Link>
                        <Link
                            v-if="dvLottery.show_in_menu && hasSeekerPortal"
                            :href="route('user.dv-lottery.index')"
                            class="hidden rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 sm:inline-flex"
                        >
                            DV Lottery
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

            <main class="w-full flex-1 px-1.5 py-1.5 sm:px-2 sm:py-2 lg:px-3 lg:py-3">
                <ImpersonationBanner />
                <div
                    v-if="showEmailVerificationBanner"
                    class="mb-3 flex flex-col gap-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-amber-900 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p class="text-sm font-medium">
                        Your account is not verified yet. Verify your email to secure your account and unlock full access.
                    </p>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-md border border-amber-400 bg-amber-200 px-3 py-1.5 text-sm font-semibold text-amber-900 transition hover:bg-amber-300 disabled:cursor-not-allowed disabled:opacity-70"
                        :disabled="sendingVerificationEmail"
                        @click="sendVerificationEmail"
                    >
                        {{ sendingVerificationEmail ? 'Sending...' : 'Verify account' }}
                    </button>
                </div>
                <div class="w-full [&>*]:!mx-0 [&>*]:!max-w-none [&>*]:w-full">
                    <slot />
                </div>
            </main>

            <SiteLegalFooter variant="light" />
        </div>
    </div>

    <!-- Guest: marketing-style top nav -->
    <div v-else class="flex min-h-screen flex-col bg-slate-50">
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
                        <template v-for="item in navigation" :key="item.name">
                            <a
                                v-if="item.external"
                                :href="item.href"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 transition-all duration-200 hover:bg-slate-100 hover:text-slate-900"
                            >
                                {{ item.name }}
                            </a>
                            <Link
                                v-else
                                :href="item.href"
                                :class="[
                                    'rounded-lg px-4 py-2 text-sm font-medium transition-all duration-200',
                                    isActive(item.href)
                                        ? 'bg-sky-50 text-sky-700'
                                        : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900',
                                ]"
                            >
                                {{ item.name }}
                            </Link>
                        </template>
                    </div>

                    <div class="ml-auto flex items-center space-x-3 sm:space-x-4">
                        <SiteSharePanel variant="outline" menu-align="right" class="hidden sm:inline-flex" />
                        <SiteSharePanel variant="icon" menu-align="right" class="sm:hidden" />
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

            <main class="flex-1 pt-16">
                <slot />
            </main>

            <SiteLegalFooter variant="dark">
                <template #extra>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Share</span>
                        <SiteSharePanel variant="footer" menu-align="right" />
                    </div>
                </template>
            </SiteLegalFooter>
    </div>
</template>
