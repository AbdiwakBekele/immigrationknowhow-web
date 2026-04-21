<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AdvertiserLayout from '@/Layouts/AdvertiserLayout.vue';
import ProviderLayout from '@/Layouts/ProviderLayout.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    summary: { type: Object, default: () => ({ views: 0, clicks: 0, ctr: 0 }) },
    ads: { type: Array, default: () => [] },
    adsRouteNamePrefix: { type: String, default: 'advertiser.ads' },
    adPortal: { type: Object, default: () => ({ portal: 'advertiser' }) },
});

const layoutComponent = computed(() => {
    if (props.adPortal?.portal === 'provider') return ProviderLayout;
    if (props.adPortal?.portal === 'user') return AppLayout;
    return AdvertiserLayout;
});
</script>

<template>
    <Head title="Ad Analytics" />

    <component :is="layoutComponent">
        <div class="mx-auto max-w-6xl space-y-6">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h1 class="text-2xl font-semibold text-slate-900">Ad analytics</h1>
                <p class="mt-1 text-sm text-slate-500">Performance overview across all your ads.</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <p class="text-sm text-slate-500">Total views</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">{{ summary.views ?? 0 }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <p class="text-sm text-slate-500">Total clicks</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">{{ summary.clicks ?? 0 }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <p class="text-sm text-slate-500">Overall CTR</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">{{ summary.ctr ?? 0 }}%</p>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                    <h2 class="text-lg font-semibold text-slate-900">Per-ad performance</h2>
                    <Link :href="route(`${adsRouteNamePrefix}.index`)" class="text-sm font-medium text-primary-700 hover:text-primary-800">
                        Manage ads
                    </Link>
                </div>
                <div v-if="ads.length" class="divide-y divide-slate-100">
                    <div v-for="ad in ads" :key="ad.uuid" class="grid grid-cols-1 gap-3 px-6 py-4 sm:grid-cols-5 sm:items-center">
                        <div class="sm:col-span-2">
                            <p class="font-medium text-slate-900">{{ ad.title }}</p>
                            <p class="text-sm text-slate-500">{{ ad.status }}</p>
                        </div>
                        <p class="text-sm text-slate-700"><span class="text-slate-500">Views:</span> {{ ad.views }}</p>
                        <p class="text-sm text-slate-700"><span class="text-slate-500">Clicks:</span> {{ ad.clicks }}</p>
                        <p class="text-sm font-medium text-slate-900">CTR {{ ad.ctr }}%</p>
                    </div>
                </div>
                <div v-else class="px-6 py-8 text-sm text-slate-500">No ad analytics yet.</div>
            </div>
        </div>
    </component>
</template>

