<script setup>
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { StarIcon, ChatBubbleLeftRightIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';

defineProps({
    stats: {
        type: Object,
        default: () => ({
            total: 0,
            pending: 0,
            today: 0,
            average_rating: 0,
        }),
    },
    reviewsByRating: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <Head title="Reviews" />

    <AdminLayout>
        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Reviews</h1>
                <p class="mt-1 text-gray-500">Moderate feedback quality and monitor trust signals.</p>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                    <div class="mb-3 inline-flex rounded-lg bg-yellow-100 p-2">
                        <StarIcon class="h-5 w-5 text-yellow-600" />
                    </div>
                    <p class="text-3xl font-semibold text-gray-900">{{ stats.average_rating ?? 0 }}</p>
                    <p class="text-sm text-gray-500">Average rating</p>
                </div>
                <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                    <div class="mb-3 inline-flex rounded-lg bg-sky-100 p-2">
                        <ChatBubbleLeftRightIcon class="h-5 w-5 text-sky-600" />
                    </div>
                    <p class="text-3xl font-semibold text-gray-900">{{ stats.today ?? 0 }}</p>
                    <p class="text-sm text-gray-500">New today</p>
                </div>
                <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                    <div class="mb-3 inline-flex rounded-lg bg-rose-100 p-2">
                        <ExclamationTriangleIcon class="h-5 w-5 text-rose-600" />
                    </div>
                    <p class="text-3xl font-semibold text-gray-900">{{ stats.pending ?? 0 }}</p>
                    <p class="text-sm text-gray-500">Pending moderation</p>
                </div>
            </div>

            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center">
                <p class="text-lg font-semibold text-gray-800">Reviews by rating</p>
                <p class="mt-2 text-gray-500">Users are grouped below by the star rating they gave.</p>
            </div>

            <div class="space-y-4">
                <div
                    v-for="bucket in reviewsByRating"
                    :key="bucket.rating"
                    class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm"
                >
                    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                        <div class="flex items-center gap-2">
                            <span class="text-base font-semibold text-gray-900">{{ bucket.rating }}-Star Reviews</span>
                            <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-700">
                                {{ bucket.count }}
                            </span>
                        </div>
                    </div>

                    <div v-if="bucket.reviews?.length" class="divide-y divide-gray-100">
                        <div v-for="review in bucket.reviews" :key="review.id" class="px-5 py-4">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">
                                        {{ review.user?.name || 'Unknown user' }}
                                    </p>
                                    <p class="text-xs text-gray-500">{{ review.user?.email || 'No email' }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-medium text-gray-800">
                                        {{ review.provider?.business_name || 'Unknown provider' }}
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        {{ review.created_at ? new Date(review.created_at).toLocaleDateString() : '' }}
                                    </p>
                                </div>
                            </div>
                            <p v-if="review.comment" class="mt-2 text-sm text-gray-700">
                                {{ review.comment }}
                            </p>
                        </div>
                    </div>

                    <div v-else class="px-5 py-6 text-sm text-gray-500">
                        No reviews in this rating.
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
