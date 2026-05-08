<script setup>
import { Head, Link } from '@inertiajs/vue3';
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

const reviewsPageLink = (query = {}) => {
    const params = new URLSearchParams();

    Object.entries(query).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
            params.set(key, String(value));
        }
    });

    const qs = params.toString();
    return qs ? `/admin/reviews?${qs}` : '/admin/reviews';
};

const formatDate = (value) => {
    if (!value) return '';
    return new Date(value).toLocaleDateString();
};
</script>

<template>
    <Head title="Reviews" />

    <AdminLayout>
        <div class="space-y-5">
            <section class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                <h1 class="text-lg font-semibold text-slate-900">Reviews</h1>
                <p class="text-sm text-slate-500">
                    Review feedback and keep moderation simple.
                </p>
            </section>

            <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <Link
                    :href="reviewsPageLink()"
                    class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm transition hover:border-slate-300"
                >
                    <p class="text-xl font-semibold tracking-tight text-slate-900">{{ stats.total ?? 0 }}</p>
                    <p class="mt-1 text-xs text-slate-500">Total reviews</p>
                </Link>
                <Link
                    :href="reviewsPageLink()"
                    class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm transition hover:border-slate-300"
                >
                    <div class="inline-flex rounded-lg bg-amber-100 p-1.5">
                        <StarIcon class="h-4 w-4 text-amber-700" />
                    </div>
                    <p class="mt-2 text-xl font-semibold tracking-tight text-slate-900">{{ stats.average_rating ?? 0 }}</p>
                    <p class="mt-1 text-xs text-slate-500">Average rating</p>
                </Link>
                <Link
                    :href="reviewsPageLink({ today: 'yes' })"
                    class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm transition hover:border-slate-300"
                >
                    <div class="inline-flex rounded-lg bg-sky-100 p-1.5">
                        <ChatBubbleLeftRightIcon class="h-4 w-4 text-sky-600" />
                    </div>
                    <p class="mt-2 text-xl font-semibold tracking-tight text-slate-900">{{ stats.today ?? 0 }}</p>
                    <p class="mt-1 text-xs text-slate-500">New today</p>
                </Link>
                <Link
                    :href="reviewsPageLink({ flagged: 'yes' })"
                    class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm transition hover:border-slate-300"
                >
                    <div class="inline-flex rounded-lg bg-rose-100 p-1.5">
                        <ExclamationTriangleIcon class="h-4 w-4 text-rose-600" />
                    </div>
                    <p class="mt-2 text-xl font-semibold tracking-tight text-slate-900">{{ stats.pending ?? 0 }}</p>
                    <p class="mt-1 text-xs text-slate-500">Pending moderation</p>
                </Link>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                <p class="text-sm font-semibold text-slate-800">Reviews by rating</p>
                <p class="mt-1 text-xs text-slate-500">Each rating is grouped with compact cards.</p>
            </section>

            <section class="space-y-4">
                <div
                    v-for="bucket in reviewsByRating"
                    :key="bucket.rating"
                    class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm"
                >
                    <div class="mb-3 flex items-center justify-between border-b border-slate-100 pb-3">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-semibold text-slate-900">{{ bucket.rating }}-Star Reviews</span>
                            <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700">
                                {{ bucket.count }}
                            </span>
                        </div>
                    </div>

                    <div v-if="bucket.reviews?.length" class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                        <article
                            v-for="review in bucket.reviews"
                            :key="review.id"
                            class="rounded-lg border border-slate-200 bg-slate-50 p-3"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p class="text-sm font-medium text-slate-900">
                                        {{ review.user?.name || 'Unknown user' }}
                                    </p>
                                    <p class="text-xs text-slate-500">{{ review.user?.email || 'No email' }}</p>
                                </div>
                                <div class="text-right text-xs text-slate-500">
                                    <p class="font-medium text-slate-700">
                                        {{ review.provider?.business_name || 'Unknown provider' }}
                                    </p>
                                    <p>{{ formatDate(review.created_at) }}</p>
                                </div>
                            </div>
                            <p v-if="review.comment" class="mt-2 text-sm text-slate-700">
                                {{ review.comment }}
                            </p>
                        </article>
                    </div>

                    <div v-else class="py-4 text-sm text-slate-500">
                        No reviews in this rating.
                    </div>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>
