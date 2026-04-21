<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { TrashIcon } from '@heroicons/vue/24/outline';
import AdvertiserLayout from '@/Layouts/AdvertiserLayout.vue';

const props = defineProps({
    ads: { type: Array, default: () => [] },
    adPostingPrice: { type: Object, default: () => ({ amount_cents: 0, currency: 'USD' }) },
});

const destroyAd = (uuid) => {
    if (!window.confirm('Delete this ad?')) return;
    router.delete(route('advertiser.ads.destroy', uuid));
};

const statusClass = (status) => {
    if (status === 'published') return 'bg-emerald-100 text-emerald-700';
    if (status === 'pending') return 'bg-amber-100 text-amber-700';
    return 'bg-slate-100 text-slate-700';
};
</script>

<template>
    <Head title="My Ads" />

    <AdvertiserLayout>
        <div class="mx-auto max-w-6xl space-y-6">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h1 class="text-2xl font-semibold text-slate-900">My ads</h1>
                <p class="mt-1 text-sm text-slate-500">
                    One-time publish fee:
                    {{ adPostingPrice.currency }} {{ (Number(adPostingPrice.amount_cents || 0) / 100).toFixed(2) }} per ad.
                </p>
            </div>

            <div class="flex justify-end">
                <Link href="/advertiser/ads/create" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">
                    Create ad
                </Link>
            </div>

            <div v-if="ads.length" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="ad in ads"
                    :key="ad.uuid"
                    class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                >
                    <div class="relative">
                        <img
                            v-if="ad.image_url"
                            :src="ad.image_url"
                            :alt="`${ad.title} cover image`"
                            class="h-44 w-full object-cover"
                        />
                        <div v-else class="flex h-44 w-full items-center justify-center bg-slate-100 text-sm font-medium text-slate-500">
                            No cover image
                        </div>
                        <div class="absolute inset-x-0 top-0 flex items-center justify-between p-3">
                            <span class="inline-flex items-center rounded-full bg-black/60 px-2.5 py-1 text-xs font-medium text-white">
                                Ad preview
                            </span>
                            <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-medium" :class="statusClass(ad.status)">
                                {{ ad.status }}
                            </span>
                        </div>
                    </div>

                    <div class="space-y-4 p-5">
                        <div>
                            <h2 class="line-clamp-1 text-base font-semibold text-slate-900">{{ ad.title }}</h2>
                            <p class="mt-1 line-clamp-2 text-sm text-slate-500">
                                {{ ad.description || 'No description provided yet.' }}
                            </p>
                        </div>

                        <dl class="grid grid-cols-3 gap-2 rounded-xl bg-slate-50 p-3 text-center">
                            <div>
                                <dt class="text-xs text-slate-500">Views</dt>
                                <dd class="text-sm font-semibold text-slate-900">{{ ad.analytics.views }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-slate-500">Clicks</dt>
                                <dd class="text-sm font-semibold text-slate-900">{{ ad.analytics.clicks }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-slate-500">CTR</dt>
                                <dd class="text-sm font-semibold text-slate-900">{{ ad.analytics.ctr }}%</dd>
                            </div>
                        </dl>

                        <div class="flex items-center justify-between">
                            <Link :href="route('advertiser.ads.edit', ad.uuid)" class="text-sm font-medium text-primary-700 hover:text-primary-800">
                                Manage
                            </Link>
                            <button
                                type="button"
                                class="inline-flex items-center rounded-lg p-1.5 text-rose-600 hover:bg-rose-50 hover:text-rose-700"
                                aria-label="Delete ad"
                                title="Delete ad"
                                @click="destroyAd(ad.uuid)"
                            >
                                <TrashIcon class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </article>
            </div>
            <div v-else class="rounded-2xl border border-slate-200 bg-white px-6 py-8 text-sm text-slate-500 shadow-sm">No ads yet.</div>
        </div>
    </AdvertiserLayout>
</template>

