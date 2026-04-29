<script setup>
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Bars3Icon,
    XMarkIcon,
    MegaphoneIcon,
    HomeIcon,
    ChartBarIcon,
    LinkIcon,
    ArrowRightOnRectangleIcon,
} from '@heroicons/vue/24/outline';
import ImpersonationBanner from '@/Components/ImpersonationBanner.vue';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const sidebarOpen = ref(false);
const adPortal = computed(() => page.props.adPortal || {});
const dvLottery = computed(() => page.props.dvLottery ?? {});
const navigation = computed(() => [
    { name: 'Dashboard', href: adPortal.value.dashboardHref || '/advertiser/dashboard', icon: HomeIcon },
    ...(dvLottery.value.show_in_menu
        ? [{
            name: 'DV Lottery',
            href: dvLottery.value.official_url || 'https://dvprogram.state.gov/',
            icon: LinkIcon,
            external: true,
        }]
        : []),
    { name: 'My Ads', href: adPortal.value.adsHref || '/advertiser/ads', icon: MegaphoneIcon },
    { name: 'Ad Analytics', href: adPortal.value.analyticsHref || '/advertiser/analytics', icon: ChartBarIcon },
]);

const isActive = (href) => {
    const path = page.url.split('?')[0] ?? '';
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
    if (!path.startsWith(`${target}/`)) {
        return false;
    }
    const hasMoreSpecificMatch = navigation.value.some((item) => {
        const candidateHref = typeof item.href === 'string' ? item.href : '';
        if (!candidateHref || candidateHref === href) {
            return false;
        }
        let candidate = candidateHref;
        if (candidate.startsWith('http://') || candidate.startsWith('https://')) {
            try {
                candidate = new URL(candidate).pathname;
            } catch {
                /* keep candidate as-is */
            }
        }
        if (!candidate.startsWith(`${target}/`)) {
            return false;
        }
        return path === candidate || path.startsWith(`${candidate}/`);
    });
    return !hasMoreSpecificMatch;
};

const logout = () => {
    router.post('/logout');
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
    <div class="min-h-screen bg-slate-100">
        <div v-if="sidebarOpen" class="fixed inset-0 z-40 bg-slate-900/50 lg:hidden" @click="sidebarOpen = false"></div>

        <aside
            class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-slate-200 bg-white transition-transform duration-300 lg:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <div class="relative border-b border-slate-200 px-5 pb-5 pt-6">
                <button
                    type="button"
                    class="absolute right-4 top-4 rounded-xl p-2 text-slate-500 hover:bg-slate-100 lg:hidden"
                    @click="sidebarOpen = false"
                >
                    <XMarkIcon class="h-5 w-5" />
                </button>
                <Link :href="adPortal.dashboardHref || '/advertiser/dashboard'" class="inline-flex items-center gap-2 text-slate-900">
                    <MegaphoneIcon class="h-7 w-7 text-primary-600" />
                    <span class="text-lg font-semibold">Advertiser Portal</span>
                </Link>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto px-4 py-5">
                <template v-for="item in navigation" :key="item.href + item.name">
                    <a
                        v-if="item.external"
                        :href="item.href"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="flex items-center gap-3 rounded-2xl px-3 py-3 text-sm font-medium text-slate-600 transition hover:bg-slate-50 hover:text-slate-900"
                        @click="sidebarOpen = false"
                    >
                        <component :is="item.icon" class="h-5 w-5" />
                        <span>{{ item.name }}</span>
                    </a>
                    <Link
                        v-else
                        :href="item.href"
                        :class="[
                            'flex items-center gap-3 rounded-2xl px-3 py-3 text-sm font-medium transition',
                            isActive(item.href) ? 'bg-primary-600 text-white' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900',
                        ]"
                        @click="sidebarOpen = false"
                    >
                        <component :is="item.icon" class="h-5 w-5" />
                        <span>{{ item.name }}</span>
                    </Link>
                </template>
            </nav>

            <div class="shrink-0 border-t border-slate-200 p-4">
                <div class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-3 py-3">
                    <img
                        v-if="hasUserAvatar"
                        :src="userAvatarSrc"
                        alt="User profile photo"
                        class="h-10 w-10 shrink-0 rounded-full object-cover ring-1 ring-slate-200"
                    />
                    <div
                        v-else
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-600 text-xs font-bold text-white ring-1 ring-primary-500/50"
                        aria-hidden="true"
                    >
                        {{ userAvatarInitial }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold text-slate-900">
                            {{ user?.full_name || user?.email || 'Profile' }}
                        </p>
                        <p class="truncate text-xs text-slate-500">Account</p>
                    </div>
                </div>
            </div>
        </aside>

        <div class="lg:pl-72">
            <header class="sticky top-0 z-30 border-b border-slate-200 bg-white">
                <div class="flex h-16 items-center justify-between px-4 sm:px-6">
                    <button
                        type="button"
                        class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:hidden"
                        @click="sidebarOpen = true"
                    >
                        <Bars3Icon class="h-6 w-6" />
                    </button>

                    <div class="text-sm text-slate-500">
                        {{ user?.full_name || user?.email }}
                    </div>

                    <button
                        type="button"
                        class="inline-flex items-center rounded-lg p-2 text-slate-500 hover:bg-slate-100"
                        @click="logout"
                    >
                        <ArrowRightOnRectangleIcon class="h-5 w-5" />
                    </button>
                </div>
            </header>

            <main class="w-full px-2 py-2 sm:px-3 sm:py-3 lg:px-4 lg:py-4">
                <ImpersonationBanner />
                <div class="w-full [&>*]:!mx-0 [&>*]:!max-w-none [&>*]:w-full">
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>

