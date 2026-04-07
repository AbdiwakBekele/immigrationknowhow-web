<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-primary-50 via-white to-accent-50">
        <!-- Decorative background -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div class="absolute -top-40 -right-32 w-96 h-96 bg-primary-200/30 rounded-full blur-3xl"></div>
            <div class="absolute top-1/2 -left-32 w-80 h-80 bg-accent-200/20 rounded-full blur-3xl"></div>
            <div class="absolute -bottom-20 right-1/4 w-72 h-72 bg-secondary-200/20 rounded-full blur-3xl"></div>
        </div>

        <div class="relative flex min-h-screen flex-col items-center justify-center py-12 sm:px-6 lg:px-8">
            <!-- Logo -->
            <div class="sm:mx-auto sm:w-full sm:max-w-md">
                <Link :href="route('home')" class="flex justify-center items-center gap-3 group">
                    <div class="w-14 h-14 bg-gradient-to-br from-primary-500 to-primary-700 rounded-2xl flex items-center justify-center shadow-soft group-hover:shadow-glow transition-all duration-300">
                        <span class="text-white font-display font-bold text-2xl">IK</span>
                    </div>
                </Link>
                <h2 class="mt-6 text-center text-3xl font-display font-bold tracking-tight text-neutral-900">
                    <slot name="title">Welcome</slot>
                </h2>
                <p class="mt-2 text-center text-sm text-neutral-600">
                    <slot name="subtitle" />
                </p>
            </div>

            <!-- Content card -->
            <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
                <div class="bg-white/80 backdrop-blur-xl py-8 px-4 shadow-soft-lg sm:rounded-2xl sm:px-10 ring-1 ring-neutral-900/5">
                    <slot />
                </div>

                <p v-if="$slots.footer" class="mt-6 text-center text-sm text-neutral-500">
                    <slot name="footer" />
                </p>
            </div>

            <!-- Footer links -->
            <div class="mt-8 flex items-center justify-center gap-6 text-sm text-neutral-500">
                <Link :href="route('home')" class="hover:text-primary-600 transition-colors">Home</Link>
                <span class="w-1 h-1 bg-neutral-300 rounded-full"></span>
                <a href="#" class="hover:text-primary-600 transition-colors">Privacy</a>
                <span class="w-1 h-1 bg-neutral-300 rounded-full"></span>
                <a href="#" class="hover:text-primary-600 transition-colors">Terms</a>
            </div>
        </div>
    </div>
</template>
