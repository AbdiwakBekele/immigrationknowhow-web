<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { StarIcon as StarSolid } from '@heroicons/vue/24/solid';
import {
    ArrowTopRightOnSquareIcon,
    CalendarDaysIcon,
    ChatBubbleLeftRightIcon,
    StarIcon as StarOutline,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    reviews: {
        type: Object,
        required: true,
    },
});

const formatDate = (date) => {
    if (!date) return '';
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
};
</script>

<template>
    <Head title="My Reviews" />

    <AppLayout>
        <div class="min-h-full bg-gradient-to-b from-slate-50 via-white to-slate-50">
            <div class="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <div class="mb-8 overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-sm">
                    <div class="relative px-5 py-6 sm:px-7">
                        <div class="pointer-events-none absolute -right-16 -top-20 h-44 w-44 rounded-full bg-primary-100 blur-3xl"></div>
                        <div class="relative flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-primary-600">Review history</p>
                                <h1 class="mt-2 text-2xl font-display font-bold text-slate-950 sm:text-3xl">My Reviews</h1>
                                <p class="mt-2 max-w-2xl text-sm text-slate-500">
                                    Reviews you have submitted to service providers.
                                </p>
                            </div>

                            <div class="inline-flex w-fit items-center gap-2 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                <StarSolid class="h-5 w-5 text-yellow-400" />
                                <div>
                                    <p class="text-xs font-medium text-slate-500">Total reviews</p>
                                    <p class="text-lg font-semibold text-slate-950">{{ reviews.total || reviews.data?.length || 0 }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="reviews.data?.length" class="grid gap-4 md:grid-cols-2">
                    <div
                        v-for="review in reviews.data"
                        :key="review.id"
                        class="group flex h-full flex-col rounded-3xl border border-slate-200/80 bg-white p-5 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-primary-100 hover:shadow-xl hover:shadow-slate-200/70"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="mb-2 flex flex-wrap items-center gap-2">
                                    <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-primary-50 text-primary-700 ring-1 ring-primary-100">
                                        <ChatBubbleLeftRightIcon class="h-5 w-5" />
                                    </span>
                                    <div class="min-w-0">
                                        <h2 class="truncate text-lg font-semibold text-slate-950">
                                            {{ review.service_provider?.business_name || 'Service Provider' }}
                                        </h2>
                                        <span class="mt-1 inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">
                                            <CalendarDaysIcon class="h-3.5 w-3.5" />
                                            {{ formatDate(review.created_at) }}
                                        </span>
                                    </div>
                                </div>
                                <Link
                                    v-if="review.service_provider?.slug"
                                    :href="route('marketplace.show', review.service_provider.slug)"
                                    class="inline-flex items-center gap-1.5 rounded-full text-sm font-semibold text-primary-600 transition hover:text-primary-700"
                                >
                                    View provider profile
                                    <ArrowTopRightOnSquareIcon class="h-4 w-4" />
                                </Link>
                            </div>

                            <div class="flex shrink-0 items-center gap-1 rounded-full bg-yellow-50 px-2.5 py-1.5 ring-1 ring-yellow-100">
                                <template v-for="i in 5" :key="i">
                                    <StarSolid v-if="i <= review.rating" class="h-4 w-4 text-yellow-500" />
                                    <StarOutline v-else class="h-4 w-4 text-yellow-200" />
                                </template>
                            </div>
                        </div>

                        <div class="mt-5 flex-1 rounded-2xl bg-slate-50/80 p-4 ring-1 ring-slate-100">
                            <p class="text-sm leading-6 text-slate-700 whitespace-pre-line">{{ review.comment }}</p>
                        </div>

                        <div
                            v-if="review.provider_response"
                            class="mt-4 rounded-2xl border border-primary-100 bg-primary-50/70 p-4"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2 text-sm font-semibold text-slate-950">
                                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-white text-primary-600 shadow-sm">
                                        <ChatBubbleLeftRightIcon class="h-4 w-4" />
                                    </span>
                                    Provider response
                                </div>
                                <p v-if="review.provider_responded_at" class="shrink-0 text-xs font-medium text-slate-500">
                                    {{ formatDate(review.provider_responded_at) }}
                                </p>
                            </div>
                            <p class="mt-3 text-sm leading-6 text-slate-700 whitespace-pre-line">{{ review.provider_response }}</p>
                        </div>
                    </div>
                </div>

                <div v-else class="rounded-3xl border border-dashed border-slate-300 bg-white p-12 text-center shadow-sm">
                    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100">
                        <StarOutline class="h-8 w-8 text-slate-400" />
                    </div>
                    <h3 class="text-lg font-semibold text-slate-950">No reviews yet</h3>
                    <p class="mt-1 text-slate-500">When you review a provider, it will appear here.</p>
                    <Link
                        :href="route('marketplace.index')"
                        class="mt-5 inline-flex rounded-2xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700"
                    >
                        Find providers
                    </Link>
                </div>

                <div v-if="reviews.links?.length > 3" class="mt-8 flex justify-center">
                    <nav class="flex flex-wrap justify-center gap-1 rounded-2xl border border-slate-200 bg-white p-1 shadow-sm">
                        <Link
                            v-for="link in reviews.links"
                            :key="link.label"
                            :href="link.url || '#'"
                            class="rounded-xl px-3 py-2 text-sm font-medium transition"
                            :class="[
                                link.active ? 'bg-primary-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100',
                                !link.url ? 'pointer-events-none opacity-50' : '',
                            ]"
                            v-html="link.label"
                        />
                    </nav>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
