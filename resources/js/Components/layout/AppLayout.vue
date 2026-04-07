<script setup>
import { ref, computed, onMounted, Transition } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/vue';
import {
    Bars3Icon,
    XMarkIcon,
    HomeIcon,
    MagnifyingGlassIcon,
    ChatBubbleLeftRightIcon,
    BookOpenIcon,
    UserCircleIcon,
    ArrowRightOnRectangleIcon,
} from '@heroicons/vue/24/outline';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const unreadMessages = computed(() => page.props.unreadMessages || 0);

const mobileMenuOpen = ref(false);
const scrolled = ref(false);

const navigation = [
    { name: 'Home', href: '/', icon: HomeIcon },
    { name: 'Find Services', href: '/providers', icon: MagnifyingGlassIcon },
    { name: 'Library', href: '/library', icon: BookOpenIcon },
];

const userNavigation = [
    { name: 'Dashboard', href: '/dashboard' },
    { name: 'Messages', href: '/messages' },
    { name: 'Profile', href: '/profile' },
];

onMounted(() => {
    window.addEventListener('scroll', () => {
        scrolled.value = window.scrollY > 20;
    });
});

const logout = () => {
    router.post('/logout');
};
</script>

<template>
    <div class="min-h-screen bg-slate-50">
        <!-- Navigation -->
        <nav
            :class="[
                'fixed top-0 left-0 right-0 z-50 transition-all duration-300',
                scrolled
                    ? 'bg-white/95 backdrop-blur-md shadow-sm border-b border-slate-200/50'
                    : 'bg-transparent'
            ]"
        >
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-16 items-center justify-between">
                    <!-- Logo -->
                    <div class="flex items-center">
                        <Link href="/" class="flex items-center space-x-3">
                            <div class="relative">
                                <div class="h-10 w-10 rounded-xl bg-gradient-to-br from-sky-500 to-indigo-600 flex items-center justify-center shadow-lg shadow-sky-500/25">
                                    <span class="text-white font-bold text-lg">IK</span>
                                </div>
                                <div class="absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full bg-emerald-400 border-2 border-white"></div>
                            </div>
                            <span class="hidden sm:block text-xl font-semibold bg-gradient-to-r from-slate-900 to-slate-700 bg-clip-text text-transparent">
                                ImmigrationKnowHow
                            </span>
                        </Link>
                    </div>

                    <!-- Desktop Navigation -->
                    <div class="hidden md:flex items-center space-x-1">
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
                    <div class="flex items-center space-x-4">
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

                        <!-- User Menu -->
                        <Menu v-if="user" as="div" class="relative">
                            <MenuButton class="flex items-center space-x-3 p-1.5 rounded-xl hover:bg-slate-100 transition-colors">
                                <div class="h-9 w-9 rounded-lg bg-gradient-to-br from-violet-500 to-purple-600 flex items-center justify-center text-white font-semibold text-sm shadow-md">
                                    {{ user.initials }}
                                </div>
                                <span class="hidden lg:block text-sm font-medium text-slate-700">
                                    {{ user.first_name }}
                                </span>
                            </MenuButton>

                            <Transition
                                enter-active-class="transition duration-100 ease-out"
                                enter-from-class="transform scale-95 opacity-0"
                                enter-to-class="transform scale-100 opacity-100"
                                leave-active-class="transition duration-75 ease-in"
                                leave-from-class="transform scale-100 opacity-100"
                                leave-to-class="transform scale-95 opacity-0"
                            >
                                <MenuItems class="absolute right-0 mt-2 w-56 origin-top-right rounded-xl bg-white shadow-xl ring-1 ring-black/5 focus:outline-none divide-y divide-slate-100 overflow-hidden">
                                    <div class="px-4 py-3 bg-slate-50">
                                        <p class="text-sm font-medium text-slate-900">{{ user.full_name }}</p>
                                        <p class="text-xs text-slate-500 truncate">{{ user.email }}</p>
                                    </div>
                                    <div class="py-1">
                                        <MenuItem v-for="item in userNavigation" :key="item.name" v-slot="{ active }">
                                            <Link
                                                :href="item.href"
                                                :class="[
                                                    active ? 'bg-slate-50' : '',
                                                    'block px-4 py-2.5 text-sm text-slate-700'
                                                ]"
                                            >
                                                {{ item.name }}
                                            </Link>
                                        </MenuItem>
                                    </div>
                                    <div class="py-1">
                                        <MenuItem v-slot="{ active }">
                                            <button
                                                @click="logout"
                                                :class="[
                                                    active ? 'bg-slate-50' : '',
                                                    'w-full text-left px-4 py-2.5 text-sm text-slate-700 flex items-center space-x-2'
                                                ]"
                                            >
                                                <ArrowRightOnRectangleIcon class="h-4 w-4" />
                                                <span>Sign out</span>
                                            </button>
                                        </MenuItem>
                                    </div>
                                </MenuItems>
                            </Transition>
                        </Menu>

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
                            @click="mobileMenuOpen = !mobileMenuOpen"
                            class="md:hidden p-2 rounded-lg text-slate-500 hover:text-slate-700 hover:bg-slate-100"
                        >
                            <Bars3Icon v-if="!mobileMenuOpen" class="h-6 w-6" />
                            <XMarkIcon v-else class="h-6 w-6" />
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
                <div v-if="mobileMenuOpen" class="md:hidden bg-white border-t border-slate-200">
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
        <main class="pt-16">
            <slot />
        </main>

        <!-- Footer -->
        <footer class="bg-slate-900 mt-auto">
            <div class="mx-auto max-w-7xl px-6 py-12 lg:px-8">
                <div class="grid grid-cols-2 gap-8 md:grid-cols-4">
                    <div>
                        <h3 class="text-sm font-semibold text-white">Services</h3>
                        <ul class="mt-4 space-y-3">
                            <li><a href="#" class="text-sm text-slate-400 hover:text-white transition-colors">Find an Attorney</a></li>
                            <li><a href="#" class="text-sm text-slate-400 hover:text-white transition-colors">Find an Accountant</a></li>
                            <li><a href="#" class="text-sm text-slate-400 hover:text-white transition-colors">Find a Tutor</a></li>
                            <li><a href="#" class="text-sm text-slate-400 hover:text-white transition-colors">All Services</a></li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-white">Resources</h3>
                        <ul class="mt-4 space-y-3">
                            <li><a href="#" class="text-sm text-slate-400 hover:text-white transition-colors">Digital Library</a></li>
                            <li><a href="/dv-lottery" class="text-sm text-slate-400 hover:text-white transition-colors">DV Lottery</a></li>
                            <li><a href="#" class="text-sm text-slate-400 hover:text-white transition-colors">Videos</a></li>
                            <li><a href="#" class="text-sm text-slate-400 hover:text-white transition-colors">Guides</a></li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-white">For Providers</h3>
                        <ul class="mt-4 space-y-3">
                            <li><a href="#" class="text-sm text-slate-400 hover:text-white transition-colors">Join as Provider</a></li>
                            <li><a href="#" class="text-sm text-slate-400 hover:text-white transition-colors">Provider Dashboard</a></li>
                            <li><a href="#" class="text-sm text-slate-400 hover:text-white transition-colors">Pricing</a></li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-white">Company</h3>
                        <ul class="mt-4 space-y-3">
                            <li><a href="#" class="text-sm text-slate-400 hover:text-white transition-colors">About</a></li>
                            <li><a href="#" class="text-sm text-slate-400 hover:text-white transition-colors">Contact</a></li>
                            <li><a href="#" class="text-sm text-slate-400 hover:text-white transition-colors">Privacy Policy</a></li>
                            <li><a href="#" class="text-sm text-slate-400 hover:text-white transition-colors">Terms of Service</a></li>
                        </ul>
                    </div>
                </div>
                <div class="mt-12 pt-8 border-t border-slate-800 flex flex-col md:flex-row justify-between items-center">
                    <p class="text-sm text-slate-400">
                        © {{ new Date().getFullYear() }} ImmigrationKnowHow. All rights reserved.
                    </p>
                    <div class="flex space-x-6 mt-4 md:mt-0">
                        <a href="#" class="text-slate-400 hover:text-white transition-colors">
                            <span class="sr-only">Facebook</span>
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z"/></svg>
                        </a>
                        <a href="#" class="text-slate-400 hover:text-white transition-colors">
                            <span class="sr-only">Twitter</span>
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M8.29 20.251c7.547 0 11.675-6.253 11.675-11.675 0-.178 0-.355-.012-.53A8.348 8.348 0 0022 5.92a8.19 8.19 0 01-2.357.646 4.118 4.118 0 001.804-2.27 8.224 8.224 0 01-2.605.996 4.107 4.107 0 00-6.993 3.743 11.65 11.65 0 01-8.457-4.287 4.106 4.106 0 001.27 5.477A4.072 4.072 0 012.8 9.713v.052a4.105 4.105 0 003.292 4.022 4.095 4.095 0 01-1.853.07 4.108 4.108 0 003.834 2.85A8.233 8.233 0 012 18.407a11.616 11.616 0 006.29 1.84"/></svg>
                        </a>
                        <a href="#" class="text-slate-400 hover:text-white transition-colors">
                            <span class="sr-only">LinkedIn</span>
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/></svg>
                        </a>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</template>
