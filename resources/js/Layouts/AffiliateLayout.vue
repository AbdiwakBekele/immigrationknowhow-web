<script setup>
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import BrandLogo from '@/Components/Brand/BrandLogo.vue';
import ImpersonationBanner from '@/Components/ImpersonationBanner.vue';
import {
    ArrowRightOnRectangleIcon,
    BanknotesIcon,
    Bars3Icon,
    ChartBarIcon,
    HomeIcon,
    LinkIcon,
    UserCircleIcon,
} from '@heroicons/vue/24/outline';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const sidebarOpen = ref(false);
const dvLottery = computed(() => page.props.dvLottery ?? {});

const navigation = computed(() => [
    { name: 'Dashboard', href: '/affiliate/dashboard', icon: HomeIcon },
    ...(dvLottery.value.show_in_menu
        ? [{
            name: 'DV Lottery',
            href: dvLottery.value.official_url || 'https://dvprogram.state.gov/',
            icon: LinkIcon,
            external: true,
        }]
        : []),
    { name: 'Earnings', href: '/affiliate/earnings', icon: ChartBarIcon },
    { name: 'Payouts', href: '/affiliate/payouts', icon: BanknotesIcon },
    { name: 'Profile', href: '/affiliate/profile', icon: UserCircleIcon },
]);

const isActive = (href) => page.url.startsWith(href);
const logout = () => router.post('/logout');
</script>

<template>
    <div class="min-h-screen bg-slate-50">
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
                'fixed inset-y-0 left-0 z-50 flex w-64 flex-col border-r border-slate-200 bg-white shadow-sm transition-transform duration-300 lg:translate-x-0',
                sidebarOpen ? 'translate-x-0' : '-translate-x-full',
            ]"
        >
            <div class="flex h-16 items-center gap-3 border-b border-slate-100 px-6">
                <Link href="/" class="flex items-center gap-3">
                    <BrandLogo context="site" subtitle="Affiliate Portal" mark-class="flex h-9 w-9 items-center justify-center overflow-hidden rounded-lg bg-gradient-to-br from-violet-500 to-indigo-600 text-white" />
                </Link>
            </div>

            <div class="border-b border-slate-100 p-4">
                <div class="rounded-xl bg-slate-50 p-3">
                    <p class="text-sm font-semibold text-slate-900">{{ user?.full_name }}</p>
                    <p class="mt-1 text-xs text-slate-500">Referral partner account</p>
                    <div class="mt-3 inline-flex items-center gap-2 rounded-full bg-violet-100 px-3 py-1 text-xs font-medium text-violet-700">
                        <LinkIcon class="h-3.5 w-3.5" />
                        Share your referral link from the dashboard
                    </div>
                </div>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
                <template v-for="item in navigation" :key="item.name">
                    <a
                        v-if="item.external"
                        :href="item.href"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900"
                    >
                        <component :is="item.icon" class="h-5 w-5" />
                        {{ item.name }}
                    </a>
                    <Link
                        v-else
                        :href="item.href"
                        :class="[
                            'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                            isActive(item.href)
                                ? 'bg-violet-50 text-violet-700'
                                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900',
                        ]"
                    >
                        <component :is="item.icon" class="h-5 w-5" />
                        {{ item.name }}
                    </Link>
                </template>
            </nav>
        </aside>

        <div class="lg:pl-64">
            <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur-sm">
                <div class="flex h-16 items-center justify-between px-4 sm:px-6">
                    <button class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:hidden" @click="sidebarOpen = true">
                        <Bars3Icon class="h-6 w-6" />
                    </button>
                    <div class="flex-1 lg:flex-none" />
                    <div class="flex items-center gap-3">
                        <Link href="/affiliate/dashboard" class="hidden rounded-lg px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-100 sm:inline-flex">
                            Dashboard home
                        </Link>
                        <button class="inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-100" @click="logout">
                            <ArrowRightOnRectangleIcon class="h-4 w-4" />
                            Sign out
                        </button>
                    </div>
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
