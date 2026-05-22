<script setup>
import { Link } from '@inertiajs/vue3';
import BrandLogo from '@/Components/Brand/BrandLogo.vue';
import ImpersonationBanner from '@/Components/ImpersonationBanner.vue';
import SiteLegalFooter from '@/Components/legal/SiteLegalFooter.vue';

defineProps({
    panelBadge: {
        type: String,
        default: '',
    },
    panelTitle: {
        type: String,
        default: 'Starting fresh shouldn’t mean starting alone!',
    },
    panelDescription: {
        type: String,
        default: 'Designed to feel easier to use, easier to read, and more comfortable to complete.',
    },
    panelPoints: {
        type: Array,
        default: () => [
            'Language-matched professionals',
            'Cultural understanding built in',
            'Verified with background checks',
        ],
    },
});
</script>

<template>
    <div class="flex min-h-screen flex-col bg-slate-200 px-4 py-6 sm:px-6 lg:px-10 lg:py-10">
        <div
            class="mx-auto min-h-[calc(100vh-2rem)] w-full max-w-[1600px] flex-1 overflow-hidden rounded-[2.5rem] border border-white/50 bg-[#f4f5f7] shadow-[0_30px_80px_-30px_rgba(15,23,42,0.28)] lg:min-h-[900px]"
        >
            <div class="grid h-full min-h-[inherit] lg:grid-cols-[1.1fr_0.9fr]">
                <!-- LEFT PANEL -->
                <div class="flex h-full items-center px-5 py-8 sm:px-8 lg:px-8 xl:px-10">
                    <div class="mx-auto flex w-full max-w-[760px] flex-col">
                        <div class="mb-10 flex justify-center">
                            <Link :href="route('home')" class="group inline-flex">
                                <BrandLogo
                                    context="site"
                                    :show-name="false"
                                    mark-class="flex items-center justify-center"
                                    image-class="h-14 w-auto object-contain sm:h-16"
                                    initials-class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-primary-600 text-base font-bold uppercase tracking-wide text-white shadow-sm transition group-hover:bg-primary-700"
                                />
                            </Link>
                        </div>

                        <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-[0_20px_60px_-35px_rgba(30,41,59,0.12)] sm:p-8 lg:p-9 xl:p-10">
                            <ImpersonationBanner />
                            <div class="mb-8 text-center lg:text-left">
                                <h1 class="text-4xl font-semibold tracking-tight text-slate-900 sm:text-5xl">
                                    <slot name="title">Welcome</slot>
                                </h1>

                                <p
                                    v-if="$slots.subtitle"
                                    class="mx-auto mt-4 max-w-xl text-lg leading-8 text-slate-600 lg:mx-0"
                                >
                                    <slot name="subtitle" />
                                </p>

                                <div v-if="$slots.progress" class="mt-6">
                                    <slot name="progress" />
                                </div>
                            </div>

                            <slot />

                            <div
                                v-if="$slots.footer"
                                class="mt-8 border-t border-slate-200 pt-6 text-center text-base text-slate-600 lg:text-left"
                            >
                                <slot name="footer" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT PANEL -->
                <div class="hidden h-full p-4 lg:block xl:p-5">
                    <div class="relative h-full w-full overflow-hidden rounded-[2.25rem]">
                        <!-- Background image -->
                        <div
                            class="absolute inset-0 bg-cover bg-center"
                            style="background-image: url('/images/immigrationlawyer.jpg');"
                        />

                        <!-- Overlays -->
                        <div class="absolute inset-0 bg-slate-900/22" />
                        <div class="absolute inset-0 bg-gradient-to-br from-slate-900/8 via-transparent to-slate-950/22" />
                        <div class="absolute inset-y-0 left-0 w-[62%] bg-gradient-to-r from-slate-950/50 via-slate-950/18 to-transparent" />

                        <!-- Content -->
                        <div class="relative z-10 flex h-full items-center px-8 py-10 xl:px-12">
                            <div
                                class="max-w-[560px] rounded-[2rem] border border-white/15 bg-white/10 p-8 text-white shadow-[0_20px_70px_-30px_rgba(0,0,0,0.6)] backdrop-blur-md xl:p-10"
                            >
                                <div
                                    v-if="panelBadge"
                                    class="mb-4 inline-flex rounded-full border border-white/20 bg-white/10 px-4 py-1.5 text-xs font-semibold uppercase tracking-[0.22em] text-white/80"
                                >
                                    {{ panelBadge }}
                                </div>

                                <h2 class="text-4xl font-semibold leading-tight xl:text-5xl">
                                    {{ panelTitle }}
                                </h2>

                                <p class="mt-5 text-lg leading-8 text-white/88 xl:text-xl">
                                    {{ panelDescription }}
                                </p>

                                <div class="mt-8 space-y-4">
                                    <div
                                        v-for="point in panelPoints"
                                        :key="point"
                                        class="flex items-start gap-3"
                                    >
                                        <span class="mt-2 inline-flex h-2.5 w-2.5 shrink-0 rounded-full bg-blue-300" />
                                        <span class="text-base leading-7 text-white/92">
                                            {{ point }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Floating elements -->
                        <div class="pointer-events-none absolute right-8 top-8 z-10 rounded-2xl bg-blue-400/90 px-5 py-3 text-slate-950 shadow-xl backdrop-blur">
                            <div class="text-sm font-semibold">Secure account access</div>
                            <div class="text-xs text-slate-900/70">Sign-up with confidence</div>
                        </div>

                        <div class="pointer-events-none absolute bottom-10 left-10 z-10 rounded-[1.5rem] border border-white/25 bg-white/12 px-6 py-5 text-white shadow-xl backdrop-blur-md">
                            <div class="text-base font-semibold">The right help, from the right people</div>
                            <div class="mt-1 text-sm text-white/80">Trusted support for your next chapter</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <SiteLegalFooter variant="light" class="mt-6" />
    </div>
</template>
