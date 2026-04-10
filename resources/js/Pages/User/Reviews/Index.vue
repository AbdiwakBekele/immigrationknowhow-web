<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { StarIcon as StarSolid } from '@heroicons/vue/24/solid';
import { StarIcon as StarOutline, ChatBubbleLeftRightIcon } from '@heroicons/vue/24/outline';

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
        <div class="min-h-screen bg-slate-50">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div class="mb-8">
                    <h1 class="text-2xl font-display font-bold text-slate-900">My Reviews</h1>
                    <p class="text-slate-500 mt-1">Reviews you have submitted to service providers.</p>
                </div>

                <div v-if="reviews.data?.length" class="space-y-4">
                    <div
                        v-for="review in reviews.data"
                        :key="review.id"
                        class="bg-white border border-slate-200 rounded-2xl p-5 shadow-soft"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="flex items-center gap-2 mb-1">
                                    <h2 class="text-lg font-semibold text-slate-900">
                                        {{ review.service_provider?.business_name || 'Service Provider' }}
                                    </h2>
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">
                                        {{ formatDate(review.created_at) }}
                                    </span>
                                </div>
                                <Link
                                    v-if="review.service_provider?.slug"
                                    :href="route('marketplace.show', review.service_provider.slug)"
                                    class="text-sm text-primary-600 hover:text-primary-700"
                                >
                                    View provider profile
                                </Link>
                            </div>

                            <div class="flex items-center gap-1">
                                <template v-for="i in 5" :key="i">
                                    <StarSolid v-if="i <= review.rating" class="h-5 w-5 text-yellow-500" />
                                    <StarOutline v-else class="h-5 w-5 text-slate-300" />
                                </template>
                            </div>
                        </div>

                        <p class="text-slate-700 mt-4 whitespace-pre-line">{{ review.comment }}</p>

                        <div
                            v-if="review.provider_response"
                            class="mt-4 p-4 rounded-xl bg-slate-50 border-l-4 border-primary-500"
                        >
                            <div class="flex items-center gap-2 text-sm font-medium text-slate-900">
                                <ChatBubbleLeftRightIcon class="h-4 w-4 text-primary-600" />
                                Provider response
                            </div>
                            <p class="text-sm text-slate-700 mt-2 whitespace-pre-line">{{ review.provider_response }}</p>
                            <p v-if="review.provider_responded_at" class="text-xs text-slate-500 mt-2">
                                Responded on {{ formatDate(review.provider_responded_at) }}
                            </p>
                        </div>
                    </div>
                </div>

                <div v-else class="bg-white border border-slate-200 rounded-2xl p-12 text-center">
                    <StarOutline class="h-12 w-12 text-slate-300 mx-auto mb-3" />
                    <h3 class="text-lg font-semibold text-slate-900">No reviews yet</h3>
                    <p class="text-slate-500 mt-1">When you review a provider, it will appear here.</p>
                    <Link
                        :href="route('marketplace.index')"
                        class="inline-flex mt-5 px-4 py-2 rounded-xl bg-primary-600 text-white hover:bg-primary-700"
                    >
                        Find providers
                    </Link>
                </div>

                <div v-if="reviews.links?.length > 3" class="mt-6 flex justify-center">
                    <nav class="flex gap-1">
                        <Link
                            v-for="link in reviews.links"
                            :key="link.label"
                            :href="link.url || '#'"
                            class="px-3 py-2 text-sm rounded-lg"
                            :class="[
                                link.active ? 'bg-primary-600 text-white' : 'text-slate-600 hover:bg-slate-100',
                                !link.url ? 'opacity-50 pointer-events-none' : '',
                            ]"
                            v-html="link.label"
                        />
                    </nav>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
