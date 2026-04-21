<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { computed, ref, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { EyeIcon, MegaphoneIcon } from '@heroicons/vue/24/outline';
import { formatAdStatus } from '@/utils/formatAdStatus';

const props = defineProps({
    ads: { type: Object, required: true },
    filters: { type: Object, default: () => ({ status: '', search: '' }) },
    pendingApprovalCount: { type: Number, default: 0 },
    requireAdminApproval: { type: Boolean, default: true },
});

const statusFilter = ref(props.filters.status || '');
const search = ref(props.filters.search || '');

let searchTimer = null;
watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 350);
});

watch(statusFilter, () => {
    applyFilters();
});

function applyFilters() {
    router.get(
        route('admin.ads.index'),
        { status: statusFilter.value || undefined, search: search.value || undefined },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

const statusClass = (status) => {
    if (status === 'published') return 'bg-emerald-100 text-emerald-800';
    if (status === 'pending_approval') return 'bg-amber-100 text-amber-800';
    if (status === 'pending_payment') return 'bg-sky-100 text-sky-800';
    if (status === 'rejected') return 'bg-rose-100 text-rose-800';
    return 'bg-slate-100 text-slate-700';
};

const resolvedImageSrc = (url) => {
    const u = (url || '').trim();
    if (!u) return '';
    if (u.startsWith('http://') || u.startsWith('https://') || u.startsWith('/')) return u;
    return `/storage/${u}`;
};

const rows = computed(() => props.ads?.data ?? []);

const approve = (uuid) => {
    if (!window.confirm('Approve and publish this ad?')) return;
    router.post(route('admin.ads.approve', uuid), {}, { preserveScroll: true });
};

const reject = (uuid) => {
    const reason = window.prompt('Optional note for the advertiser (leave blank for none):');
    if (reason === null) return;
    router.post(route('admin.ads.reject', uuid), { reason: reason || null }, { preserveScroll: true });
};
</script>

<template>
    <Head title="Ads" />

    <AdminLayout>
        <div class="admin-page-container">
            <section class="admin-hero-card">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                            Sponsored content
                        </p>
                        <h1 class="mt-2 admin-title">Ads</h1>
                        <p class="admin-subtitle">
                            Review ads from all accounts. Approve paid submissions before they go live
                            <span v-if="requireAdminApproval">(required)</span>
                            <span v-else>(auto-publish is enabled — approvals are optional)</span>.
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            v-if="pendingApprovalCount > 0"
                            class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-900"
                        >
                            <MegaphoneIcon class="h-4 w-4" />
                            {{ pendingApprovalCount }} pending approval
                        </span>
                        <Link
                            href="/admin/ads/analytics"
                            class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50"
                        >
                            Ad analytics
                        </Link>
                    </div>
                </div>
            </section>

            <section class="admin-panel">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div class="grid w-full gap-3 sm:max-w-xl sm:grid-cols-2">
                        <div>
                            <label class="admin-label" for="ad-status-filter">Status</label>
                            <select
                                id="ad-status-filter"
                                v-model="statusFilter"
                                class="admin-select"
                            >
                                <option value="">All</option>
                                <option value="pending_approval">Pending approval</option>
                                <option value="pending_payment">Pending payment</option>
                                <option value="published">Published</option>
                                <option value="rejected">Rejected</option>
                                <option value="draft">Draft</option>
                            </select>
                        </div>
                        <div>
                            <label class="admin-label" for="ad-search">Search</label>
                            <input
                                id="ad-search"
                                v-model="search"
                                type="search"
                                class="admin-input"
                                placeholder="Title or advertiser email…"
                                autocomplete="off"
                            >
                        </div>
                    </div>
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
                            <th class="admin-table-th text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr v-for="ad in rows" :key="ad.uuid">
                            <td class="px-4 py-3 align-top">
                                <div class="flex gap-3">
                                    <div class="h-14 w-20 shrink-0 overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
                                        <img
                                            v-if="ad.image_url"
                                            :src="resolvedImageSrc(ad.image_url)"
                                            alt=""
                                            class="h-full w-full object-cover"
                                        >
                                        <div v-else class="flex h-full w-full items-center justify-center text-[10px] text-slate-400">
                                            No image
                                        </div>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-semibold text-slate-900 line-clamp-2">{{ ad.title }}</p>
                                        <p class="mt-0.5 text-xs text-slate-500 line-clamp-2">{{ ad.description }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 align-top text-slate-700">
                                <p class="font-medium">{{ ad.user?.first_name }} {{ ad.user?.last_name }}</p>
                                <p class="text-xs text-slate-500">{{ ad.user?.email }}</p>
                            </td>
                            <td class="px-4 py-3 align-top">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                    :class="statusClass(ad.status)"
                                >
                                    {{ formatAdStatus(ad.status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-slate-800">{{ ad.views_count ?? 0 }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-slate-800">{{ ad.clicks_count ?? 0 }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-slate-800">
                                {{
                                    (ad.views_count ?? 0) > 0
                                        ? (((ad.clicks_count ?? 0) / (ad.views_count ?? 1)) * 100).toFixed(2)
                                        : '0.00'
                                }}%
                            </td>
                            <td class="px-4 py-3 align-top text-right">
                                <div class="flex flex-wrap items-center justify-end gap-2">
                                    <a
                                        v-if="ad.status === 'published'"
                                        :href="route('ads.public.show', { ad: ad.uuid })"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                    >
                                        <EyeIcon class="h-3.5 w-3.5" />
                                        Preview
                                    </a>
                                    <span
                                        v-else
                                        class="inline-flex cursor-not-allowed items-center gap-1 rounded-lg border border-dashed border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-400"
                                        title="Live preview after publish"
                                    >
                                        <EyeIcon class="h-3.5 w-3.5" />
                                        Preview
                                    </span>
                                    <template v-if="ad.status === 'pending_approval'">
                                        <button
                                            type="button"
                                            class="rounded-lg bg-emerald-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700"
                                            @click="approve(ad.uuid)"
                                        >
                                            Approve
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1.5 text-xs font-semibold text-rose-800 hover:bg-rose-100"
                                            @click="reject(ad.uuid)"
                                        >
                                            Reject
                                        </button>
                                    </template>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div v-if="!rows.length" class="px-4 py-10 text-center text-sm text-slate-500">
                    No ads match these filters.
                </div>

                <div
                    v-if="ads.links && ads.links.length > 3"
                    class="flex flex-wrap items-center justify-center gap-2 border-t border-slate-100 px-4 py-3"
                >
                    <template v-for="(link, i) in ads.links" :key="i">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            class="rounded-lg px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-100"
                            :class="{ 'bg-blue-600 font-semibold text-white hover:bg-blue-600': link.active }"
                            preserve-scroll
                        >
                            <span v-html="link.label" />
                        </Link>
                        <span
                            v-else
                            class="cursor-default rounded-lg px-3 py-1.5 text-sm text-slate-400"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>
