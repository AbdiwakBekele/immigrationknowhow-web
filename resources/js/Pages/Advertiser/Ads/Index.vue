<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { EyeIcon, TrashIcon } from '@heroicons/vue/24/outline';
import AdvertiserLayout from '@/Layouts/AdvertiserLayout.vue';
import ProviderLayout from '@/Layouts/ProviderLayout.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatAdStatus } from '@/utils/formatAdStatus';

const props = defineProps({
    ads: { type: Array, default: () => [] },
    adPostingPrice: { type: Object, default: () => ({ amount_cents: 0, currency: 'USD' }) },
    adsRouteNamePrefix: { type: String, default: 'advertiser.ads' },
    adPortal: { type: Object, default: () => ({ portal: 'advertiser' }) },
});

const layoutComponent = computed(() => {
    if (props.adPortal?.portal === 'provider') return ProviderLayout;
    if (props.adPortal?.portal === 'user') return AppLayout;
    return AdvertiserLayout;
});
const isProviderPortal = computed(() => props.adPortal?.portal === 'provider');

/** Provider & service-seeker (user) cards: align Preview / Manage / Delete to the right. */
const cardActionsJustifyClass = computed(() => {
    const p = props.adPortal?.portal;
    if (p === 'provider' || p === 'user') {
        return 'justify-end';
    }
    return 'justify-start';
});

const destroyAd = (uuid) => {
    if (!window.confirm('Delete this ad?')) return;
    router.delete(route(`${props.adsRouteNamePrefix}.destroy`, uuid));
};

const statusClass = (status) => {
    if (status === 'published') return 'bg-emerald-100 text-emerald-700';
    if (status === 'pending_approval') return 'bg-amber-100 text-amber-800';
    if (status === 'pending_payment') return 'bg-sky-100 text-sky-800';
    if (status === 'rejected') return 'bg-rose-100 text-rose-800';
    if (status === 'pending') return 'bg-amber-100 text-amber-700';
    return 'bg-slate-100 text-slate-700';
};
</script>

<template>
    <Head title="My Ads" />

    <component :is="layoutComponent">
        <div :class="isProviderPortal ? 'admin-page-container' : 'mx-auto max-w-6xl space-y-6'">
            <div
                :class="[
                    'flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white shadow-sm sm:flex-row sm:items-start sm:justify-between',
                    isProviderPortal ? 'p-5' : 'p-6',
                ]"
            >
                <div class="min-w-0">
                    <h1 class="text-2xl font-semibold text-slate-900">My ads</h1>
                    <p class="mt-1 text-sm text-slate-500">
                        One-time publish fee:
                        {{ adPostingPrice.currency }} {{ (Number(adPostingPrice.amount_cents || 0) / 100).toFixed(2) }} per ad.
                    </p>
                </div>
                <Link
                    :href="route(`${adsRouteNamePrefix}.create`)"
                    class="inline-flex shrink-0 items-center justify-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 sm:self-center"
                >
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
                                {{ formatAdStatus(ad.status) }}
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

                        <div :class="['flex flex-wrap items-center gap-3', cardActionsJustifyClass]">
                            <a
                                v-if="ad.status === 'published'"
                                :href="route('ads.public.show', { ad: ad.uuid })"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50"
                                aria-label="Preview ad"
                            >
                                <EyeIcon class="h-4 w-4" />
                                Preview
                            </a>
                            <span
                                v-else
                                class="inline-flex cursor-not-allowed items-center gap-1.5 rounded-lg border border-dashed border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-400"
                                title="Preview is available after the ad is published"
                            >
                                <EyeIcon class="h-4 w-4" />
                                Preview
                            </span>
                            <Link
                                :href="route(`${adsRouteNamePrefix}.edit`, ad.uuid)"
                                class="inline-flex items-center rounded-lg px-3 py-2 text-sm font-medium text-primary-700 hover:bg-primary-50"
                            >
                                Manage
                            </Link>
                            <button
                                type="button"
                                class="inline-flex items-center rounded-lg p-2 text-rose-600 hover:bg-rose-50 hover:text-rose-700"
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
    </component>
</template>

