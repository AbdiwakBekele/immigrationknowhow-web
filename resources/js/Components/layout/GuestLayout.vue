<script setup>
import { Link } from '@inertiajs/vue3';
import BrandLogo from '@/Components/Brand/BrandLogo.vue';
</script>

<template>
    <div class="min-h-screen bg-neutral-50">
        <div class="min-h-screen w-full">
            <div class="min-h-screen w-full overflow-hidden bg-white lg:grid lg:grid-cols-[42%_58%]">
                <div class="flex flex-col px-3 py-3 sm:px-5 sm:py-4 lg:items-center lg:justify-center">
                    <div class="sm:mx-auto sm:w-full sm:max-w-lg">
                        <Link :href="route('home')" class="group flex justify-center">
                            <BrandLogo
                                context="site"
                                :show-name="false"
                                mark-class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-lg bg-primary-600 shadow-sm transition group-hover:bg-primary-700"
                            />
                        </Link>
                        <h2 class="mt-1.5 text-center text-xl font-display font-bold tracking-tight text-neutral-900">
                            <slot name="title">Welcome</slot>
                        </h2>
                        <p class="mt-0.5 text-center text-sm text-neutral-600">
                            <slot name="subtitle" />
                        </p>
                        <div v-if="$slots.progress" class="mt-2">
                            <slot name="progress" />
                        </div>
                    </div>

                    <div class="mt-3 sm:mx-auto sm:w-full sm:max-w-lg">
                        <slot />
                    </div>

                    <p v-if="$slots.footer" class="mt-3 text-center text-sm text-neutral-500">
                        <slot name="footer" />
                    </p>

                </div>

                <div class="flex min-h-[32vh] items-stretch justify-center bg-primary-50 sm:min-h-[38vh] lg:min-h-screen">
                    <slot name="side-image">
                        <div class="h-full w-full bg-gradient-to-br from-primary-100 via-white to-primary-200"></div>
                    </slot>
                </div>
            </div>
        </div>
    </div>
</template>
