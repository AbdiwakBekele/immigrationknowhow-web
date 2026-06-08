<script setup>
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { EyeIcon } from '@heroicons/vue/24/outline';
import AdvertiserLayout from '@/Layouts/AdvertiserLayout.vue';
import ProviderLayout from '@/Layouts/ProviderLayout.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatAdStatus } from '@/utils/formatAdStatus';

const props = defineProps({
    ads: { type: Array, default: () => [] },
    adPostingPrice: { type: Object, default: () => ({ amount_cents: 0, currency: 'USD', free_limit: 0, free_remaining: 0, next_ad_price_cents: 0 }) },
    adsRouteNamePrefix: { type: String, default: 'advertiser.ads' },
    adPortal: { type: Object, default: () => ({ portal: 'advertiser' }) },
    analyticsSummary: { type: Object, default: () => ({ views: 0, clicks: 0, ctr: 0 }) },
});

const page = usePage();
const layoutComponent = computed(() => {
    if (props.adPortal?.portal === 'provider') return ProviderLayout;
    if (props.adPortal?.portal === 'user') return AppLayout;
    return AdvertiserLayout;
});
const isProviderPortal = computed(() => props.adPortal?.portal === 'provider');

const activeTab = computed(() => {
    if (!isProviderPortal.value) return 'ads';
    const url = String(page.url ?? '');
    const qs = url.includes('?') ? url.split('?')[1] : '';
    const params = new URLSearchParams(qs);
    const tab = (params.get('tab') || 'ads').toLowerCase();
    return tab === 'analytics' ? 'analytics' : 'ads';
});

/** Provider & service-seeker (user) cards: align Preview / Manage / Delete to the right. */
const cardActionsJustifyClass = computed(() => {
    const p = props.adPortal?.portal;
    if (p === 'provider' || p === 'user') {
        return 'justify-end';
    }
    return 'justify-start';
});

const destroyAd = (uuid, status) => {
    const extra =
        status === 'published'
            ? ' This ad is currently live and will be removed immediately.'
            : '';
    if (!window.confirm(`Delete this ad?${extra}`)) return;
    router.delete(route(`${props.adsRouteNamePrefix}.destroy`, uuid));
};

const statusClass = (status) => {
    if (status === 'published') return 'bg-emerald-100 text-emerald-700';
    if (status === 'pending_approval') return 'bg-amber-100 text-amber-800';
    if (status === 'pending_payment') return 'bg-sky-100 text-sky-800';
    if (status === 'rejected') return 'bg-rose-100 text-rose-800';
    if (status === 'suspended') return 'bg-violet-100 text-violet-800';
    if (status === 'pending') return 'bg-amber-100 text-amber-700';
    return 'bg-slate-100 text-slate-700';
};

const maxViews = computed(() => {
    const values = props.ads.map((ad) => Number(ad?.analytics?.views ?? 0)).filter((n) => Number.isFinite(n));
    return Math.max(1, ...values);
});

const maxClicks = computed(() => {
    const values = props.ads.map((ad) => Number(ad?.analytics?.clicks ?? 0)).filter((n) => Number.isFinite(n));
    return Math.max(1, ...values);
});
</script>

<template>
    <Head title="My Ads" />

    <component :is="layoutComponent">
        <div :class="isProviderPortal ? 'admin-page-container' : 'mx-auto max-w-6xl space-y-6'">
            <div v-if="isProviderPortal" class="mb-4">
                <nav class="flex flex-wrap gap-2 border-b border-slate-200">
                    <Link
                        :href="`${route(`${adsRouteNamePrefix}.index`)}?tab=ads`"
                        class="relative -mb-px inline-flex items-center gap-2 rounded-t-lg px-3 pb-3 pt-2 text-sm font-semibold transition focus:outline-none focus:ring-4 focus:ring-primary-100"
                        :class="activeTab === 'ads'
                            ? 'bg-white text-slate-900'
                            : 'text-slate-600 hover:bg-white/70 hover:text-slate-900'"
                    >
                        <span
                            class="absolute inset-x-0 bottom-0 h-0.5 rounded-full"
                            :class="activeTab === 'ads' ? 'bg-primary-600' : 'bg-transparent'"
                        />
                        My Ads
                    </Link>
                    <Link
                        :href="`${route(`${adsRouteNamePrefix}.index`)}?tab=analytics`"
                        class="relative -mb-px inline-flex items-center gap-2 rounded-t-lg px-3 pb-3 pt-2 text-sm font-semibold transition focus:outline-none focus:ring-4 focus:ring-primary-100"
                        :class="activeTab === 'analytics'
                            ? 'bg-white text-slate-900'
                            : 'text-slate-600 hover:bg-white/70 hover:text-slate-900'"
                    >
                        <span
                            class="absolute inset-x-0 bottom-0 h-0.5 rounded-full"
                            :class="activeTab === 'analytics' ? 'bg-primary-600' : 'bg-transparent'"
                        />
                        Ad Analytics
                    </Link>
                </nav>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm" :class="isProviderPortal ? 'p-5' : 'p-6'">

                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <h1 class="text-2xl font-semibold text-slate-900">
                            {{ activeTab === 'analytics' ? 'Ad analytics' : 'My ads' }}
                        </h1>
                        <p class="mt-1 text-sm text-slate-500">
                            <template v-if="activeTab === 'analytics'">
                                Performance overview across all your ads.
                            </template>
                            <template v-else-if="Number(adPostingPrice.free_remaining || 0) > 0">
                                {{ Number(adPostingPrice.free_remaining) }} of
                                {{ Number(adPostingPrice.free_limit || 0) }} complimentary publish
                                {{ Number(adPostingPrice.free_remaining) === 1 ? 'slot' : 'slots' }} remaining.
                                After that, {{ adPostingPrice.currency }}
                                {{ (Number(adPostingPrice.amount_cents || 0) / 100).toFixed(2) }} per ad.
                            </template>
                            <template v-else>
                                One-time publish fee:
                                {{ adPostingPrice.currency }} {{ (Number(adPostingPrice.amount_cents || 0) / 100).toFixed(2) }} per ad.
                            </template>
                        </p>
                    </div>
                    <Link
                        v-if="activeTab !== 'analytics'"
                        :href="route(`${adsRouteNamePrefix}.create`)"
                        class="inline-flex shrink-0 items-center justify-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 sm:self-center"
                    >
                        Create ad
                    </Link>
                </div>
            </div>

            <div v-if="activeTab === 'ads'">
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

                        <p
                            v-if="ad.status === 'suspended'"
                            class="rounded-xl border border-violet-200 bg-violet-50 px-3 py-2 text-sm text-violet-900"
                        >
                            This ad was <span class="font-semibold">suspended</span> by an administrator and is not visible to anyone on the public site.
                        </p>

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
                                v-if="ad.is_publicly_visible"
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
                                :title="ad.status === 'suspended'
                                    ? 'This ad was suspended and is hidden from the public site'
                                    : 'Preview is available after the ad is published'"
                            >
                                <EyeIcon class="h-4 w-4" />
                                Preview
                            </span>
                            <Link
                                :href="route(`${adsRouteNamePrefix}.edit`, ad.uuid)"
                                class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-primary-700 shadow-sm hover:bg-primary-50"
                            >
                                Edit
                            </Link>
                            <button
                                type="button"
                                class="inline-flex items-center rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-medium text-rose-700 hover:bg-rose-100"
                                aria-label="Delete ad"
                                title="Delete ad"
                                @click="destroyAd(ad.uuid, ad.status)"
                            >
                                Delete
                            </button>
                        </div>
                    </div>
                </article>
                </div>
                <div v-else class="rounded-2xl border border-slate-200 bg-white px-6 py-8 text-sm text-slate-500 shadow-sm">No ads yet.</div>
            </div>

            <div v-else class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-sm text-slate-500">Total views</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-900">{{ analyticsSummary.views ?? 0 }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-sm text-slate-500">Total clicks</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-900">{{ analyticsSummary.clicks ?? 0 }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-sm text-slate-500">Overall CTR</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-900">{{ analyticsSummary.ctr ?? 0 }}%</p>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900">Per-ad performance</h2>
                            <p class="mt-1 text-sm text-slate-500">Quick scan of views, clicks, and CTR.</p>
                        </div>
                    </div>

                    <div v-if="ads.length" class="divide-y divide-slate-100">
                        <div
                            v-for="ad in ads"
                            :key="ad.uuid"
                            class="grid grid-cols-1 gap-3 px-4 py-4 lg:grid-cols-[minmax(0,1fr)_140px_140px_90px]"
                        >
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <p class="truncate font-medium text-slate-900">{{ ad.title }}</p>
                                    <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium" :class="statusClass(ad.status)">
                                        {{ formatAdStatus(ad.status) }}
                                    </span>
                                </div>
                                <p class="mt-1 text-sm text-slate-500 truncate">{{ ad.cta_url }}</p>
                            </div>

                            <div>
                                <p class="text-xs font-medium text-slate-500">Views</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900">{{ ad.analytics?.views ?? 0 }}</p>
                                <div class="mt-2 h-2 rounded-full bg-slate-100">
                                    <div
                                        class="h-2 rounded-full bg-sky-500"
                                        :style="{ width: `${Math.min(100, Math.round((Number(ad.analytics?.views ?? 0) / maxViews) * 100))}%` }"
                                    />
                                </div>
                            </div>

                            <div>
                                <p class="text-xs font-medium text-slate-500">Clicks</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900">{{ ad.analytics?.clicks ?? 0 }}</p>
                                <div class="mt-2 h-2 rounded-full bg-slate-100">
                                    <div
                                        class="h-2 rounded-full bg-emerald-500"
                                        :style="{ width: `${Math.min(100, Math.round((Number(ad.analytics?.clicks ?? 0) / maxClicks) * 100))}%` }"
                                    />
                                </div>
                            </div>

                            <div>
                                <p class="text-xs font-medium text-slate-500">CTR</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900">{{ ad.analytics?.ctr ?? 0 }}%</p>
                            </div>
                        </div>
                    </div>

                    <div v-else class="px-4 py-8 text-sm text-slate-500">No ad analytics yet.</div>
                </div>
            </div>
        </div>
    </component>
</template>

