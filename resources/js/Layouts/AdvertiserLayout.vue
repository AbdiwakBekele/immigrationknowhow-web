<script setup>
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Bars3Icon,
    XMarkIcon,
    MegaphoneIcon,
    HomeIcon,
    ChartBarIcon,
    ArrowRightOnRectangleIcon,
} from '@heroicons/vue/24/outline';
import ImpersonationBanner from '@/Components/ImpersonationBanner.vue';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const sidebarOpen = ref(false);

const navigation = [
    { name: 'Dashboard', href: '/advertiser/dashboard', icon: HomeIcon },
    { name: 'My Ads', href: '/advertiser/ads', icon: MegaphoneIcon },
    { name: 'Ad Analytics', href: '/advertiser/analytics', icon: ChartBarIcon },
];

const isActive = (href) => {
    const path = page.url.split('?')[0] ?? '';
    return path === href || path.startsWith(`${href}/`);
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
                <Link href="/advertiser/dashboard" class="inline-flex items-center gap-2 text-slate-900">
                    <MegaphoneIcon class="h-7 w-7 text-primary-600" />
                    <span class="text-lg font-semibold">Advertiser Portal</span>
                </Link>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto px-4 py-5">
                <Link
                    v-for="item in navigation"
                    :key="item.href + item.name"
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

            <main class="p-1 sm:p-2 lg:p-5">
                <ImpersonationBanner />
                <slot />
            </main>
        </div>
    </div>
</template>

