<script setup>
import { ref, computed, watch, onMounted, onUnmounted, Transition } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import BrandLogo from '@/Components/Brand/BrandLogo.vue';
import ImpersonationBanner from '@/Components/ImpersonationBanner.vue';
import {
    HomeIcon,
    InboxIcon,
    ChatBubbleLeftRightIcon,
    StarIcon,
    UserCircleIcon,
    ShieldCheckIcon,
    ChartBarIcon,
    CreditCardIcon,
    Bars3Icon,
    BellIcon,
    BookOpenIcon,
    SparklesIcon,
    MegaphoneIcon,
    ArrowRightOnRectangleIcon,
    XMarkIcon,
    ShoppingCartIcon,
} from '@heroicons/vue/24/outline';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const dvLottery = computed(() => page.props.dvLottery ?? {});
const providerRequiresBackgroundCheck = computed(() => Boolean(page.props.provider_requires_background_check));
const showProviderEmailVerificationBanner = computed(() => {
    return Boolean(user.value) && !providerRequiresBackgroundCheck.value && !user.value?.email_verified_at;
});
const sendingVerificationEmail = ref(false);

const providerLogoSrc = computed(() => {
    const u = page.props.branding?.site_logo_url;
    return typeof u === 'string' && u.trim() !== '' ? u : '/images/logo.svg';
});

const sidebarOpen = ref(false);

const unreadNotificationsCount = computed(() => page.props.unread_notifications_count ?? 0);

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
const libraryCartBadge = computed(() => {
    const n = libraryCartCount.value;
    if (n < 1) return '';
    if (n > 99) return '99+';
    return String(n);
});

const navigation = computed(() => {
    const items = [
        { name: 'Dashboard', href: '/provider/dashboard', icon: HomeIcon },
        { name: 'Notifications', href: '/provider/notifications', icon: BellIcon },
        { name: 'Leads', href: '/provider/leads', icon: InboxIcon },
        { name: 'Messages', href: '/provider/messages', icon: ChatBubbleLeftRightIcon },
        { name: 'My Ads', href: '/provider/ads', icon: MegaphoneIcon },
        { name: 'My Library', href: route('provider.library.index'), icon: BookOpenIcon },
        { name: 'Community', href: '/community', icon: ChatBubbleLeftRightIcon },
        { name: 'AI Assistant', href: route('provider.ai-assistant.index'), icon: SparklesIcon },
        ...(dvLottery.value.show_in_menu
            ? [{
                name: 'DV Lottery',
                href: '/provider/dv-lottery',
                icon: BookOpenIcon,
            }]
            : []),
        { name: 'Subscriptions', href: '/provider/subscriptions', icon: CreditCardIcon },
        ...(providerRequiresBackgroundCheck.value
            ? [{ name: 'Background Check', href: '/provider/background-check', icon: ShieldCheckIcon }]
            : []),
        { name: 'Analytics', href: '/provider/analytics', icon: ChartBarIcon },
        { name: 'Reviews', href: '/provider/portal-reviews', icon: StarIcon },
    ];

    const seen = new Set();

    return items.filter((item) => {
        const key = (item.href || '').trim();
        if (!key || seen.has(key)) {
            return false;
        }
        seen.add(key);
        return true;
    });
});

const logout = () => {
    router.post('/logout');
};

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

const isActive = (href) => {
    const path = page.url.split('?')[0] ?? '';

    if (href === '/provider/ads') {
        return path === href || path.startsWith(`${href}/`);
    }

    let target = typeof href === 'string' ? href : '';
    if (target.startsWith('http://') || target.startsWith('https://')) {
        try {
            target = new URL(target).pathname;
        } catch {
            /* keep target as-is */
        }
    }
    if (path === target) {
        return true;
    }
    return path.startsWith(`${target}/`);
};

const readXsrfCookie = () => {
    const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    return m ? decodeURIComponent(m[1]) : '';
};

const pollUnreadMessages = async () => {
    try {
        const res = await fetch('/provider/messages/unread-count', {
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
    pollUnreadMessages();
    unreadPollTimer = window.setInterval(() => {
        if (document.visibilityState === 'visible') {
            pollUnreadMessages();
        }
    }, 12000);
    document.addEventListener('visibilitychange', onVisibilityForUnread);
});

onUnmounted(() => {
    if (unreadPollTimer !== null) {
        clearInterval(unreadPollTimer);
    }
    document.removeEventListener('visibilitychange', onVisibilityForUnread);
});
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
            />
        </Transition>

        <aside
            :class="[
                'fixed inset-y-0 left-0 z-50 flex min-h-screen w-64 flex-col border-r border-slate-200/90 bg-gradient-to-b from-white to-slate-50/90 shadow-[4px_0_32px_-12px_rgba(15,23,42,0.12)] backdrop-blur-sm transition-transform duration-300 lg:translate-x-0',
                sidebarOpen ? 'translate-x-0' : '-translate-x-full',
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
                <Link href="/provider/dashboard" class="block pr-10 lg:pr-0" @click="sidebarOpen = false">
                    <BrandLogo
                        context="site"
                        :mark-src="providerLogoSrc"
                        :show-name="false"
                        container-class="flex items-center"
                        mark-class="flex h-12 w-full max-w-[190px] items-center justify-start overflow-hidden rounded-none bg-transparent text-slate-900 shadow-none"
                        image-class="h-full w-full object-contain object-left"
                        initials-class="font-bold text-lg uppercase tracking-wide"
                    />
                </Link>

                <div class="mt-3">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">
                        Provider portal
                    </p>
                    <p class="mt-1 text-sm text-slate-500">
                        Manage leads, messages, profile visibility, and subscriptions.
                    </p>
                </div>
            </div>

            <nav class="flex-1 overflow-y-auto px-4 py-5">
                <p class="mb-3 px-3 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">
                    Main navigation
                </p>

                <div class="space-y-1.5">
                    <template v-for="item in navigation" :key="item.href">
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
                            :aria-label="item.name === 'Messages' && unreadMessages > 0 ? `${item.name}, ${unreadMessages} unread` : item.name"
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
                                        ? 'bg-white/18 text-white'
                                        : 'bg-slate-100 text-slate-600 group-hover:bg-blue-50 group-hover:text-blue-700',
                                ]"
                            >
                                <component :is="item.icon" class="h-5 w-5" />
                                <span
                                    v-if="item.name === 'Messages' && unreadMessages > 0"
                                    class="absolute -right-0.5 -top-0.5 z-10 flex min-h-[1.125rem] min-w-[1.125rem] items-center justify-center rounded-full bg-rose-500 px-1 text-[9px] font-bold leading-none text-white shadow-sm ring-2 ring-white tabular-nums"
                                >
                                    {{ unreadMessagesLabel }}
                                </span>
                            </span>
                            <span class="min-w-0 flex-1 truncate">{{ item.name }}</span>
                        </Link>
                    </template>
                </div>
            </nav>

            <div class="shrink-0 border-t border-slate-200 p-4">
                <Link
                    href="/provider/profile"
                    class="flex items-center gap-3 rounded-xl border border-slate-200/80 bg-white px-3 py-2.5 text-sm text-slate-700 shadow-sm transition-colors hover:border-slate-300 hover:bg-slate-50"
                    @click="sidebarOpen = false"
                >
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-primary-600 text-white ring-1 ring-primary-500/50">
                        <UserCircleIcon class="h-6 w-6" />
                    </span>
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
                                Provider portal
                            </p>
                            <h1 class="text-lg font-semibold text-slate-900">
                                Welcome back
                            </h1>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 sm:gap-3">
                        <Link
                            :href="route('provider.notifications.index')"
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
                            href="/provider/profile"
                            title="Profile"
                            aria-label="Profile"
                            class="inline-flex h-11 w-11 items-center justify-center rounded-2xl text-slate-500 transition hover:bg-slate-100"
                        >
                            <UserCircleIcon class="h-6 w-6" />
                        </Link>
                        <Link
                            :href="route('provider.library.cart')"
                            class="relative inline-flex h-11 w-11 items-center justify-center rounded-2xl text-slate-500 transition hover:bg-slate-100"
                            title="Library cart"
                            aria-label="Library cart"
                        >
                            <ShoppingCartIcon class="h-5 w-5" />
                            <span
                                v-if="libraryCartCount > 0"
                                class="absolute -right-0.5 -top-0.5 flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-blue-600 px-1 text-[9px] font-bold leading-none text-white ring-2 ring-white tabular-nums"
                            >
                                {{ libraryCartBadge }}
                            </span>
                        </Link>
                        <Link
                            v-if="dvLottery.show_in_menu"
                            href="/provider/dv-lottery"
                            class="hidden rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 sm:inline-flex"
                        >
                            DV Lottery
                        </Link>
                        <button
                            type="button"
                            title="Sign out"
                            aria-label="Sign out"
                            class="inline-flex h-11 w-11 items-center justify-center rounded-2xl text-slate-500 transition hover:bg-slate-100"
                            @click="logout"
                        >
                            <ArrowRightOnRectangleIcon class="h-5 w-5" />
                        </button>
                    </div>
                </div>
            </header>

            <main class="w-full px-1.5 py-1.5 sm:px-2 sm:py-2 lg:px-3 lg:py-3">
                <ImpersonationBanner />
                <div
                    v-if="showProviderEmailVerificationBanner"
                    class="mb-3 flex flex-col gap-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-amber-900 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p class="text-sm font-medium">
                        Your provider account email is not verified yet. Please verify it to secure your account.
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
        </div>
    </div>
</template>
