<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { BriefcaseIcon, CheckBadgeIcon, Squares2X2Icon, StarIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    providers: { type: Object, required: true },
    serviceTypes: { type: Array, default: () => [] },
});

const providerRows = computed(() => props.providers?.data || []);

const totalProviders = computed(() => props.providers?.total || providerRows.value.length);
const verifiedProviders = computed(() => providerRows.value.filter((p) => p.is_verified).length);
const activeProviders = computed(() => providerRows.value.filter((p) => p.is_active).length);

const categoryCards = computed(() => {
    const map = new Map();

    for (const provider of providerRows.value) {
        const key = provider.primary_service_type || 'other';
        const current = map.get(key) || { key, label: key, count: 0 };
        current.count += 1;
        map.set(key, current);
    }

    for (const entry of props.serviceTypes) {
        if (map.has(entry.value)) {
            map.get(entry.value).label = entry.label;
        }
    }

    return Array.from(map.values()).sort((a, b) => b.count - a.count);
});

const categoryLabel = (value) => {
    if (!value) return 'Other';
    return value
        .replaceAll('_', ' ')
        .replaceAll('-', ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());
};

const initial = (provider) => {
    const letter = provider?.user?.first_name?.trim()?.charAt(0);
    return letter ? letter.toUpperCase() : '?';
};
</script>

<template>
    <Head title="Providers" />

    <AdminLayout>
        <div class="space-y-4">
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                <h1 class="text-lg font-bold text-slate-900">Providers</h1>
                <p class="mt-0.5 text-xs text-slate-500">All provider profiles with category-based summary cards.</p>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="mb-2 inline-flex rounded-lg bg-sky-100 p-2">
                        <BriefcaseIcon class="h-5 w-5 text-sky-600" />
                    </div>
                    <p class="text-2xl font-semibold text-slate-900">{{ totalProviders }}</p>
                    <p class="text-xs text-slate-500">Total providers</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="mb-2 inline-flex rounded-lg bg-emerald-100 p-2">
                        <CheckBadgeIcon class="h-5 w-5 text-emerald-600" />
                    </div>
                    <p class="text-2xl font-semibold text-slate-900">{{ verifiedProviders }}</p>
                    <p class="text-xs text-slate-500">Verified providers</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="mb-2 inline-flex rounded-lg bg-indigo-100 p-2">
                        <Squares2X2Icon class="h-5 w-5 text-indigo-600" />
                    </div>
                    <p class="text-2xl font-semibold text-slate-900">{{ activeProviders }}</p>
                    <p class="text-xs text-slate-500">Active providers</p>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="mb-3 flex items-center gap-2">
                    <Squares2X2Icon class="h-4 w-4 text-slate-600" />
                    <h2 class="text-sm font-semibold text-slate-900">Providers by Category</h2>
                </div>
                <div v-if="categoryCards.length" class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
                    <div
                        v-for="category in categoryCards"
                        :key="category.key"
                        class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2"
                    >
                        <p class="truncate text-xs font-medium text-slate-600">{{ category.label || categoryLabel(category.key) }}</p>
                        <p class="mt-1 text-lg font-semibold text-slate-900">{{ category.count }}</p>
                    </div>
                </div>
                <p v-else class="text-xs text-slate-500">No category data found.</p>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold text-slate-900">Provider Profiles</h2>
                <div v-if="providerRows.length" class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <div
                        v-for="provider in providerRows"
                        :key="provider.id"
                        class="rounded-xl border border-slate-200 bg-white p-3"
                    >
                        <div class="flex items-start gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-sky-100 text-sm font-semibold text-sky-700">
                                {{ initial(provider) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-slate-900">{{ provider.business_name }}</p>
                                <p class="truncate text-xs text-slate-500">
                                    {{ provider.user?.first_name }} {{ provider.user?.last_name }} · {{ provider.user?.email }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-1.5">
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-700">
                                {{ categoryLabel(provider.primary_service_type) }}
                            </span>
                            <span
                                class="rounded-full px-2 py-0.5 text-xs"
                                :class="provider.is_verified ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'"
                            >
                                {{ provider.is_verified ? 'Verified' : 'Pending' }}
                            </span>
                            <span class="rounded-full bg-violet-100 px-2 py-0.5 text-xs text-violet-700">
                                {{ provider.leads_count || 0 }} Leads
                            </span>
                            <span class="inline-flex items-center gap-1 rounded-full bg-yellow-100 px-2 py-0.5 text-xs text-yellow-700">
                                <StarIcon class="h-3 w-3" />
                                {{ provider.reviews_count || 0 }} Reviews
                            </span>
                        </div>

                        <div class="mt-3">
                            <Link
                                :href="`/admin/providers/${provider.slug}`"
                                class="inline-flex rounded-lg bg-sky-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-sky-700"
                            >
                                View Profile
                            </Link>
                        </div>
                    </div>
                </div>
                <p v-else class="text-xs text-slate-500">No providers found.</p>
            </div>

            <div v-if="providers.links?.length > 3" class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                <nav class="flex flex-wrap justify-center gap-1">
                    <Link
                        v-for="link in providers.links"
                        :key="`${link.label}-${link.url}`"
                        :href="link.url"
                        class="rounded-md px-2.5 py-1.5 text-xs"
                        :class="link.active ? 'bg-sky-600 text-white' : 'text-slate-600 hover:bg-slate-100'"
                        v-html="link.label"
                    />
                </nav>
            </div>
        </div>
    </AdminLayout>
</template>
