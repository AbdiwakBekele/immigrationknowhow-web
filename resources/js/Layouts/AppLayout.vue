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
    StarIcon,
    UserCircleIcon,
    ArrowRightOnRectangleIcon,
} from '@heroicons/vue/24/outline';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const unreadMessages = computed(() => page.props.unreadMessages || 0);
const userLogoSrc = computed(() => {
    const u = page.props.branding?.site_logo_url;
    return typeof u === 'string' && u.trim() !== '' ? u : '/images/logo.svg';
});

const mobileMenuOpen = ref(false);
const sidebarOpen = ref(false);
const scrolled = ref(false);

const navigation = [
    { name: 'Home', href: '/', icon: HomeIcon },
    { name: 'Find Services', href: '/providers', icon: MagnifyingGlassIcon },
    { name: 'Library', href: '/library', icon: BookOpenIcon },
];

const userNavigation = computed(() => {
    const profileLabel = user.value?.first_name?.trim() || 'Profile';
    return [
        { name: 'Dashboard', href: '/dashboard', icon: HomeIcon },
        { name: 'Messages', href: '/messages', icon: ChatBubbleLeftRightIcon },
        { name: 'Library', href: '/library', icon: BookOpenIcon },
        { name: 'Reviews', href: '/reviews', icon: StarIcon },
        { name: profileLabel, href: '/profile', icon: UserCircleIcon },
    ];
});

onMounted(() => {
    window.addEventListener('scroll', () => {
        scrolled.value = window.scrollY > 20;
    });
});

const logout = () => {
    router.post('/logout');
};

const isActive = (href) => page.url.startsWith(href);

const userAvatarSrc = computed(() => {
    const u = user.value;
    if (!u) return '/img/default-avatar.png';
    const url = u.avatar_url;
    return typeof url === 'string' && url.trim() !== '' ? url : '/img/default-avatar.png';
});
</script>

<template>
    <div class="min-h-screen bg-slate-50">
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
                v-if="user && sidebarOpen"
                class="fixed inset-0 z-40 bg-slate-900/50 lg:hidden"
                @click="sidebarOpen = false"
            ></div>
        </Transition>

        <!-- Left sidebar -->
        <aside
            v-if="user"
            :class="[
                'fixed inset-y-0 left-0 z-50 flex w-64 flex-col border-r border-slate-200 bg-white shadow-sm transition-transform duration-300 lg:translate-x-0',
                sidebarOpen ? 'translate-x-0' : '-translate-x-full'
            ]"
        >
            <div class="flex shrink-0 items-center border-b border-slate-200 px-4 py-4">
                <Link href="/" class="block w-full">
                    <BrandLogo
                        context="site"
                        :mark-src="userLogoSrc"
                        :show-name="false"
                        container-class="flex items-center"
                        mark-class="flex min-h-[2.75rem] w-full max-w-[180px] items-center justify-center overflow-visible rounded-xl border border-slate-200/80 bg-white px-2 py-2 shadow-sm"
                        image-class="block h-9 w-auto max-w-full object-contain object-left"
                        initials-class="text-sm font-bold uppercase tracking-wide text-slate-600"
                    />
                </Link>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
                <Link
                    v-for="item in userNavigation"
                    :key="item.name"
                    :href="item.href"
                    class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors"
                    :class="[
                        isActive(item.href)
                            ? 'bg-sky-50 text-sky-700 border border-sky-100'
                            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                    ]"
                    @click="sidebarOpen = false"
                >
                    <component :is="item.icon" class="h-5 w-5 flex-shrink-0" />
                    {{ item.name }}
                </Link>
            </nav>

            <div class="shrink-0 border-t border-slate-200 p-3">
                <Link
                    href="/profile"
                    class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-left transition-colors"
                    :class="[
                        isActive('/profile')
                            ? 'bg-sky-50 text-sky-700 ring-1 ring-sky-100'
                            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                    ]"
                    aria-label="Profile"
                    @click="sidebarOpen = false"
                >
                    <img
                        :src="userAvatarSrc"
                        alt=""
                        class="h-10 w-10 shrink-0 rounded-full object-cover ring-1 ring-slate-200"
                    />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold">
                            {{ user?.first_name?.trim() || 'Profile' }}
                        </p>
                        <p
                            class="truncate text-xs font-medium"
                            :class="isActive('/profile') ? 'text-sky-600' : 'text-slate-500'"
                        >
                            Account
                        </p>
                    </div>
                </Link>
            </div>

        </aside>

        <!-- Navigation -->
        <nav
            :class="[
                'fixed top-0 right-0 z-30 transition-all duration-300',
                user ? 'left-0 lg:left-64' : 'left-0',
                scrolled
                    ? 'bg-white/95 backdrop-blur-md shadow-sm border-b border-slate-200/50'
                    : 'bg-transparent'
            ]"
        >
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-16 items-center justify-between">
                    <!-- Logo (guest only; authenticated users use sidebar logo) -->
                    <div v-if="!user" class="flex items-center">
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

                    <!-- Desktop Navigation -->
                    <div v-if="!user" class="hidden md:flex items-center space-x-1">
                        <Link
                            v-for="item in navigation"
                            :key="item.name"
                            :href="item.href"
                            :class="[
                                'px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200',
                                $page.url === item.href
                                    ? 'bg-sky-50 text-sky-700'
                                    : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'
                            ]"
                        >
                            {{ item.name }}
                        </Link>
                    </div>

                    <!-- Right side -->
                    <div class="ml-auto flex items-center space-x-4">
                        <!-- Messages with badge -->
                        <Link
                            v-if="user"
                            href="/messages"
                            class="relative p-2 rounded-lg text-slate-500 hover:text-slate-700 hover:bg-slate-100 transition-colors"
                        >
                            <ChatBubbleLeftRightIcon class="h-6 w-6" />
                            <span
                                v-if="unreadMessages > 0"
                                class="absolute -top-1 -right-1 h-5 w-5 rounded-full bg-rose-500 text-white text-xs font-medium flex items-center justify-center ring-2 ring-white"
                            >
                                {{ unreadMessages > 9 ? '9+' : unreadMessages }}
                            </span>
                        </Link>

                        <Link
                            v-if="user"
                            href="/profile"
                            class="p-2 rounded-lg text-slate-500 hover:text-slate-700 hover:bg-slate-100 transition-colors"
                            title="Profile"
                            aria-label="Profile"
                        >
                            <UserCircleIcon class="h-6 w-6" />
                        </Link>

                        <button
                            v-if="user"
                            type="button"
                            class="p-2 rounded-lg text-slate-500 hover:text-slate-700 hover:bg-slate-100 transition-colors"
                            title="Sign out"
                            aria-label="Sign out"
                            @click="logout"
                        >
                            <ArrowRightOnRectangleIcon class="h-6 w-6" />
                        </button>

                        <!-- Guest buttons -->
                        <template v-else>
                            <Link
                                href="/login"
                                class="hidden sm:inline-flex px-4 py-2 text-sm font-medium text-slate-700 hover:text-slate-900 transition-colors"
                            >
                                Sign in
                            </Link>
                            <Link
                                href="/register"
                                class="inline-flex items-center px-4 py-2.5 rounded-xl bg-gradient-to-r from-sky-500 to-indigo-600 text-white text-sm font-semibold shadow-lg shadow-sky-500/25 hover:shadow-xl hover:shadow-sky-500/30 hover:-translate-y-0.5 transition-all duration-200"
                            >
                                Get Started
                            </Link>
                        </template>

                        <!-- Mobile menu button -->
                        <button
                            @click="user ? (sidebarOpen = !sidebarOpen) : (mobileMenuOpen = !mobileMenuOpen)"
                            class="md:hidden p-2 rounded-lg text-slate-500 hover:text-slate-700 hover:bg-slate-100"
                        >
                            <Bars3Icon class="h-6 w-6" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- Mobile menu -->
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0 -translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-2"
            >
                <div v-if="!user && mobileMenuOpen" class="md:hidden bg-white border-t border-slate-200">
                    <div class="px-4 py-4 space-y-1">
                        <Link
                            v-for="item in navigation"
                            :key="item.name"
                            :href="item.href"
                            @click="mobileMenuOpen = false"
                            class="flex items-center space-x-3 px-3 py-3 rounded-lg text-slate-700 hover:bg-slate-100"
                        >
                            <component :is="item.icon" class="h-5 w-5 text-slate-400" />
                            <span>{{ item.name }}</span>
                        </Link>
                    </div>
                </div>
            </Transition>
        </nav>

        <!-- Main content -->
        <main :class="['pt-16', user ? 'lg:pl-64' : '']">
            <slot />
        </main>
    </div>
</template>
