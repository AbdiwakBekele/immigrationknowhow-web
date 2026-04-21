<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatAdStatus } from '@/utils/formatAdStatus';

defineProps({
    summary: { type: Object, default: () => ({ views: 0, clicks: 0, ctr: 0 }) },
    ads: { type: Array, default: () => [] },
});
</script>

<template>
    <Head title="Ad Analytics" />

    <AdminLayout>
        <div class="admin-page-container">
            <section class="admin-hero-card">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                    Platform-wide
                </p>
                <h1 class="mt-2 admin-title">Ad analytics</h1>
                <p class="admin-subtitle">
                    Aggregate views, clicks, and CTR across all sponsored ads.
                </p>
                <div class="mt-4">
                    <Link
                        href="/admin/ads"
                        class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50"
                    >
                        Back to ads
                    </Link>
                </div>
            </section>

            <section class="grid gap-4 sm:grid-cols-3">
                <div class="admin-panel-compact">
                    <p class="text-sm text-slate-500">Total views</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">{{ summary.views ?? 0 }}</p>
                </div>
                <div class="admin-panel-compact">
                    <p class="text-sm text-slate-500">Total clicks</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">{{ summary.clicks ?? 0 }}</p>
                </div>
                <div class="admin-panel-compact">
                    <p class="text-sm text-slate-500">Overall CTR</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">{{ summary.ctr ?? 0 }}%</p>
                </div>
            </section>

            <section class="admin-table-wrap overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="admin-table-head">
                        <tr>
                            <th class="admin-table-th">Ad</th>
                            <th class="admin-table-th">Advertiser</th>
                            <th class="admin-table-th">Status</th>
                            <th class="admin-table-th text-right">Views</th>
                            <th class="admin-table-th text-right">Clicks</th>
                            <th class="admin-table-th text-right">CTR</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr v-for="ad in ads" :key="ad.uuid">
                            <td class="px-4 py-3 font-medium text-slate-900">{{ ad.title }}</td>
                            <td class="px-4 py-3 text-slate-700">
                                <p>{{ ad.user?.name }}</p>
                                <p class="text-xs text-slate-500">{{ ad.user?.email }}</p>
                            </td>
                            <td class="px-4 py-3 text-slate-700">{{ formatAdStatus(ad.status) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ ad.views }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ ad.clicks }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ ad.ctr }}%</td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="!ads.length" class="px-4 py-10 text-center text-sm text-slate-500">
                    No ad analytics yet.
                </div>
            </section>
        </div>
    </AdminLayout>
</template>
