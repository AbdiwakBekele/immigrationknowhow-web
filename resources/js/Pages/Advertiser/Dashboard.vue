<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdvertiserLayout from '@/Layouts/AdvertiserLayout.vue';

defineProps({
    stats: { type: Object, default: () => ({}) },
    recentAds: { type: Array, default: () => [] },
});
</script>

<template>
    <Head title="Advertiser Dashboard" />

    <AdvertiserLayout>
        <div class="mx-auto max-w-6xl space-y-6">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h1 class="text-2xl font-semibold text-slate-900">Advertiser dashboard</h1>
                <p class="mt-1 text-sm text-slate-500">Manage paid ad publishing and performance in one place.</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <p class="text-sm text-slate-500">Total ads</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">{{ stats.total_ads ?? 0 }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <p class="text-sm text-slate-500">Published ads</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">{{ stats.published_ads ?? 0 }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <p class="text-sm text-slate-500">Views / Clicks</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">{{ stats.views ?? 0 }} / {{ stats.clicks ?? 0 }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <p class="text-sm text-slate-500">CTR</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">{{ stats.ctr ?? 0 }}%</p>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                    <h2 class="text-lg font-semibold text-slate-900">Recent ads</h2>
                    <Link href="/advertiser/ads/create" class="rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-700">
                        New ad
                    </Link>
                </div>
                <div v-if="recentAds.length" class="divide-y divide-slate-100">
                    <div v-for="ad in recentAds" :key="ad.uuid" class="flex items-center justify-between px-6 py-4">
                        <div>
                            <p class="font-medium text-slate-900">{{ ad.title }}</p>
                            <p class="text-sm text-slate-500">{{ ad.analytics.views }} views • {{ ad.analytics.clicks }} clicks • CTR {{ ad.analytics.ctr }}%</p>
                        </div>
                        <Link :href="route('advertiser.ads.edit', ad.uuid)" class="text-sm font-medium text-primary-700 hover:text-primary-800">
                            Manage
                        </Link>
                    </div>
                </div>
                <div v-else class="px-6 py-8 text-sm text-slate-500">No ads yet. Create your first ad to begin.</div>
            </div>
        </div>
    </AdvertiserLayout>
</template>

