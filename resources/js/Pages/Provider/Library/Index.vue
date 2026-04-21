<script setup>
import { Head, Link } from '@inertiajs/vue3';
import ProviderLayout from '@/Layouts/ProviderLayout.vue';
import { BookOpenIcon, HeartIcon, LockClosedIcon, ShoppingCartIcon } from '@heroicons/vue/24/outline';
import { HeartIcon as HeartSolidIcon } from '@heroicons/vue/24/solid';

defineProps({
    purchasedItems: { type: Object, required: true },
    availableItems: { type: Object, required: true },
});

const priceLabel = (item) => {
    const amount = Number(item?.price || 0);
    if (!amount) return 'Free';
    return `${item.currency ?? 'USD'} ${amount.toFixed(2)}`;
};

const accessLabel = (item) => (item?.type === 'audiobook' ? 'Listen now' : 'Read now');
const userAccessRecord = (item) => {
    if (Array.isArray(item?.user_access)) {
        return item.user_access[0] || null;
    }

    return item?.user_access || null;
};

const isFavorited = (item) => Boolean(userAccessRecord(item)?.is_favorite);
</script>

<template>
    <Head title="Provider Library" />

    <ProviderLayout>
        <div class="mx-auto max-w-7xl space-y-8">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h1 class="text-2xl font-bold text-slate-900">My Library</h1>
                <p class="mt-1 text-sm text-slate-600">
                    Access your purchased titles and browse more books to unlock.
                </p>
            </section>

            <section>
                <div class="mb-3 flex items-center gap-2">
                    <BookOpenIcon class="h-5 w-5 text-emerald-600" />
                    <h2 class="text-lg font-semibold text-slate-900">Purchased and available now</h2>
                </div>
                <div v-if="purchasedItems?.data?.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <article
                        v-for="item in purchasedItems.data"
                        :key="item.uuid"
                        class="flex h-full flex-col rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
                    >
                        <img
                            v-if="item.cover_image_url"
                            :src="item.cover_image_url"
                            :alt="item.title"
                            class="h-40 w-full rounded-lg object-cover object-top"
                        >
                        <div v-else class="flex h-40 w-full items-center justify-center rounded-lg bg-slate-100 text-slate-500">
                            <BookOpenIcon class="h-8 w-8" />
                        </div>
                        <div class="mt-3 flex-1">
                            <h3 class="line-clamp-2 font-semibold leading-snug text-slate-950">{{ item.title }}</h3>
                            <p class="text-sm font-medium text-slate-700">{{ item.author }}</p>
                        </div>
                        <div class="mt-4 flex items-center gap-2">
                            <Link
                                :href="route('library.show', item.slug)"
                                class="inline-flex flex-1 items-center justify-center rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                View details
                            </Link>
                            <Link
                                :href="route('library.read', item.slug)"
                                :title="accessLabel(item)"
                                :aria-label="accessLabel(item)"
                                class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-primary-600 text-white hover:bg-primary-700"
                            >
                                <BookOpenIcon class="h-5 w-5" />
                            </Link>
                            <Link
                                :href="route('library.favorite', item.slug)"
                                method="post"
                                as="button"
                                preserve-scroll
                                :title="isFavorited(item) ? 'Favorited' : 'Mark as favorite'"
                                :aria-label="isFavorited(item) ? 'Favorited' : 'Mark as favorite'"
                                class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-rose-200 text-rose-700 hover:bg-rose-50"
                            >
                                <HeartSolidIcon v-if="isFavorited(item)" class="h-4 w-4" />
                                <HeartIcon v-else class="h-4 w-4" />
                            </Link>
                        </div>
                    </article>
                </div>
                <div v-else class="rounded-xl border border-dashed border-slate-300 bg-white p-6 text-sm text-slate-600">
                    No purchased titles yet.
                </div>
            </section>

            <section>
                <div class="mb-3 flex items-center gap-2">
                    <ShoppingCartIcon class="h-5 w-5 text-blue-600" />
                    <h2 class="text-lg font-semibold text-slate-900">Available to purchase</h2>
                </div>
                <div v-if="availableItems?.data?.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <article
                        v-for="item in availableItems.data"
                        :key="item.uuid"
                        class="flex h-full flex-col rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
                    >
                        <img
                            v-if="item.cover_image_url"
                            :src="item.cover_image_url"
                            :alt="item.title"
                            class="h-40 w-full rounded-lg object-cover object-top"
                        >
                        <div v-else class="flex h-40 w-full items-center justify-center rounded-lg bg-slate-100 text-slate-500">
                            <BookOpenIcon class="h-8 w-8" />
                        </div>
                        <div class="mt-3 flex-1">
                            <h3 class="line-clamp-2 font-semibold leading-snug text-slate-950">{{ item.title }}</h3>
                            <p class="text-sm font-medium text-slate-700">{{ item.author }}</p>
                            <p class="mt-2 text-sm font-semibold text-slate-800">{{ priceLabel(item) }}</p>
                        </div>
                        <div class="mt-4 flex gap-2">
                            <Link
                                :href="route('library.show', item.slug)"
                                class="inline-flex flex-1 items-center justify-center rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                Details
                            </Link>
                            <a
                                :href="route('library.pay', item.slug)"
                                class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg bg-primary-600 px-3 py-2 text-sm font-semibold text-white hover:bg-primary-700"
                            >
                                <LockClosedIcon class="h-4 w-4" />
                                Buy
                            </a>
                        </div>
                    </article>
                </div>
                <div v-else class="rounded-xl border border-dashed border-slate-300 bg-white p-6 text-sm text-slate-600">
                    No additional titles available right now.
                </div>
            </section>
        </div>
    </ProviderLayout>
</template>
