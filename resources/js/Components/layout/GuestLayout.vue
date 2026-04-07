<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-screen bg-neutral-50">
        <div class="relative flex min-h-screen flex-col items-center justify-center py-10 sm:px-6 lg:px-8">
            <div class="sm:mx-auto sm:w-full sm:max-w-lg">
                <Link :href="route('home')" class="group flex justify-center">
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-xl bg-primary-600 shadow-sm transition group-hover:bg-primary-700"
                    >
                        <span class="font-display text-lg font-bold text-white">IK</span>
                    </div>
                </Link>
                <h2 class="mt-5 text-center text-2xl font-display font-bold tracking-tight text-neutral-900">
                    <slot name="title">Welcome</slot>
                </h2>
                <p class="mt-1.5 text-center text-sm text-neutral-600">
                    <slot name="subtitle" />
                </p>
            </div>

            <div class="mt-6 sm:mx-auto sm:w-full sm:max-w-lg">
                <div class="rounded-2xl border border-neutral-200/80 bg-white px-5 py-8 shadow-sm sm:px-8">
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
