<script setup>
import { ref, computed } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import { 
    Bars3Icon, 
    XMarkIcon,
    HomeIcon,
    UsersIcon,
    BriefcaseIcon,
    ShieldCheckIcon,
    StarIcon,
    BookOpenIcon,
    LinkIcon,
    VideoCameraIcon,
    ChartBarIcon,
    Cog6ToothIcon,
    Squares2X2Icon,
    ArrowRightOnRectangleIcon,
    BellIcon,
} from '@heroicons/vue/24/outline';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const sidebarOpen = ref(false);

const navigation = [
    { name: 'Dashboard', href: '/admin/dashboard', icon: HomeIcon },
    { name: 'Users', href: '/admin/users', icon: UsersIcon },
    { name: 'Providers', href: '/admin/providers', icon: BriefcaseIcon },
    { name: 'Background Checks', href: '/admin/background-checks', icon: ShieldCheckIcon },
    { name: 'Reviews', href: '/admin/reviews', icon: StarIcon },
    { name: 'Library', href: '/admin/library', icon: BookOpenIcon },
    { name: 'Affiliates', href: '/admin/affiliates', icon: LinkIcon },
    { name: 'Videos', href: '/admin/videos', icon: VideoCameraIcon },
    { name: 'Service Types', href: '/admin/service-types', icon: Squares2X2Icon },
    { name: 'Reports', href: '/admin/reports', icon: ChartBarIcon },
    { name: 'Settings', href: '/admin/settings', icon: Cog6ToothIcon },
];

const logout = () => {
    router.post('/logout');
};

const isActive = (href) => {
    return page.url.startsWith(href);
};
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
            :class="[
                'fixed inset-y-0 left-0 z-50 w-64 bg-slate-900 transform transition-transform duration-300 lg:translate-x-0',
                sidebarOpen ? 'translate-x-0' : '-translate-x-full'
            ]"
        >
            <!-- Logo -->
            <div class="flex h-16 items-center gap-3 px-6 border-b border-slate-800">
                <div class="h-9 w-9 rounded-lg bg-gradient-to-br from-sky-500 to-indigo-600 flex items-center justify-center">
                    <span class="text-white font-bold">IK</span>
                </div>
                <div>
                    <span class="text-white font-semibold">ImmigrationKnowHow</span>
                    <span class="block text-xs text-slate-400">Admin Panel</span>
                </div>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
                <Link
                    v-for="item in navigation"
                    :key="item.name"
                    :href="item.href"
                    :class="[
                        'flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors',
                        isActive(item.href)
                            ? 'bg-sky-600 text-white'
                            : 'text-slate-300 hover:bg-slate-800 hover:text-white'
                    ]"
                >
                    <component :is="item.icon" class="h-5 w-5 flex-shrink-0" />
                    {{ item.name }}
                </Link>
            </nav>

            <!-- User section -->
            <div class="p-4 border-t border-slate-800">
                <div class="flex items-center gap-3 px-3 py-2">
                    <div class="h-9 w-9 rounded-lg bg-gradient-to-br from-violet-500 to-purple-600 flex items-center justify-center text-white text-sm font-semibold">
                        {{ user?.initials }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-white truncate">{{ user?.full_name }}</p>
                        <p class="text-xs text-slate-400 truncate">Administrator</p>
                    </div>
                </div>
                <button
                    @click="logout"
                    class="mt-2 w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-slate-300 hover:bg-slate-800 hover:text-white transition-colors"
                >
                    <ArrowRightOnRectangleIcon class="h-5 w-5" />
                    Sign out
                </button>
            </div>
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

                    <div class="flex items-center gap-4">
                        <button class="relative p-2 rounded-lg text-slate-500 hover:bg-slate-100 transition-colors">
                            <BellIcon class="h-6 w-6" />
                            <span class="absolute top-1.5 right-1.5 h-2 w-2 rounded-full bg-rose-500"></span>
                        </button>
                        <Link
                            href="/"
                            class="hidden sm:inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm text-slate-600 hover:bg-slate-100 transition-colors"
                        >
                            View Site
                        </Link>
                    </div>
                </div>
            </header>

            <main class="p-4 sm:p-6 lg:p-8">
                <slot />
            </main>
        </div>
    </div>
</template>
