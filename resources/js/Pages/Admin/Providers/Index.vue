<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { debounce } from 'lodash-es';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import {
    BriefcaseIcon,
    CheckBadgeIcon,
    MagnifyingGlassIcon,
    PlusIcon,
    Squares2X2Icon,
    StarIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    providers: { type: Object, required: true },
    serviceTypes: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    stats: {
        type: Object,
        default: () => ({ total: 0, verified: 0, active: 0 }),
    },
});

const providerRows = computed(() => props.providers?.data || []);

const search = ref(props.filters?.search || '');
const serviceType = ref(props.filters?.service_type || '');
const verified = ref(props.filters?.verified || '');
const active = ref(props.filters?.active || '');

const applyFilters = debounce(() => {
    router.get(
        route('admin.providers.index'),
        {
            search: search.value || undefined,
            service_type: serviceType.value || undefined,
            verified: verified.value || undefined,
            active: active.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
}, 300);

watch(search, () => applyFilters());
watch([serviceType, verified, active], () => applyFilters());

const clearFilters = () => {
    search.value = '';
    serviceType.value = '';
    verified.value = '';
    active.value = '';
    router.get(route('admin.providers.index'), {}, { preserveState: true, preserveScroll: true });
};

const primaryServiceValue = (provider) => {
    const types = provider?.service_types;
    if (!Array.isArray(types) || types.length === 0) {
        return null;
    }
    const raw = types[0];
    return typeof raw === 'string' && raw.trim() !== '' ? raw : null;
};

const serviceTypeLabelForValue = (value) => {
    if (!value) return 'Other';
    const match = (props.serviceTypes || []).find((t) => t.value === value);
    if (match?.label) return match.label;
    return categoryLabel(value);
};

const categoryCards = computed(() => {
    const map = new Map();

    for (const provider of providerRows.value) {
        const valueKey = primaryServiceValue(provider) || 'other';
        const current = map.get(valueKey) || { key: valueKey, label: valueKey, count: 0 };
        current.count += 1;
        map.set(valueKey, current);
    }

    for (const entry of props.serviceTypes || []) {
        if (map.has(entry.value)) {
            map.get(entry.value).label = entry.label;
        }
    }

    for (const card of map.values()) {
        if (card.key === 'other') {
            card.label = 'Other';
        } else if (card.label === card.key) {
            card.label = serviceTypeLabelForValue(card.key);
        }
    }

    return Array.from(map.values()).sort((a, b) => b.count - a.count);
});

const categoryLabel = (value) => {
    if (!value || value === 'other') return 'Other';
    return value
        .replaceAll('_', ' ')
        .replaceAll('-', ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());
};

const initial = (provider) => {
    const letter = provider?.user?.first_name?.trim()?.charAt(0);
    return letter ? letter.toUpperCase() : '?';
};

const avatarUrl = (provider) => {
    const u = provider?.user?.avatar_url ?? provider?.user?.avatar;
    return typeof u === 'string' && u.trim() !== '' ? u : null;
};
</script>

<template>
    <Head title="Providers" />

    <AdminLayout>
        <div class="mx-auto max-w-7xl space-y-5">
            <div
                class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white px-4 py-4 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:px-5"
            >
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-400">
                        Directory
                    </p>
                    <h1 class="text-xl font-display font-bold text-slate-900">Providers</h1>
                    <p class="mt-0.5 text-sm text-slate-500">
                        Manage provider profiles, verification, and activity.
                    </p>
                </div>
                <Link
                    href="/admin/providers/create"
                    class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-sky-700"
                >
                    <PlusIcon class="h-5 w-5" />
                    Add Provider
                </Link>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="mb-3 inline-flex rounded-lg bg-sky-100 p-2">
                        <BriefcaseIcon class="h-5 w-5 text-sky-600" />
                    </div>
                    <p class="text-2xl font-display font-bold text-slate-900">{{ stats.total }}</p>
                    <p class="text-xs text-slate-500">Total providers</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="mb-3 inline-flex rounded-lg bg-emerald-100 p-2">
                        <CheckBadgeIcon class="h-5 w-5 text-emerald-600" />
                    </div>
                    <p class="text-2xl font-display font-bold text-slate-900">{{ stats.verified }}</p>
                    <p class="text-xs text-slate-500">Verified</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="mb-3 inline-flex rounded-lg bg-indigo-100 p-2">
                        <Squares2X2Icon class="h-5 w-5 text-indigo-600" />
                    </div>
                    <p class="text-2xl font-display font-bold text-slate-900">{{ stats.active }}</p>
                    <p class="text-xs text-slate-500">Active listings</p>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
                    <div class="min-w-0 flex-1">
                        <label class="mb-1 block text-xs font-medium text-slate-500">Search</label>
                        <div class="relative">
                            <MagnifyingGlassIcon
                                class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                            />
                            <input
                                v-model="search"
                                type="search"
                                autocomplete="off"
                                placeholder="Business name, contact, email…"
                                class="w-full rounded-lg border border-slate-200 bg-white py-2.5 pl-10 pr-3 text-sm text-slate-900 placeholder-slate-400 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20"
                            />
                        </div>
                    </div>
                    <div class="grid w-full grid-cols-1 gap-3 sm:grid-cols-3 lg:w-auto lg:min-w-[42rem]">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">Service type</label>
                            <select
                                v-model="serviceType"
                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20"
                            >
                                <option value="">All types</option>
                                <option v-for="t in serviceTypes" :key="t.value" :value="t.value">
                                    {{ t.label }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">Verification</label>
                            <select
                                v-model="verified"
                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20"
                            >
                                <option value="">All</option>
                                <option value="yes">Verified</option>
                                <option value="no">Pending</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">Status</label>
                            <select
                                v-model="active"
                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20"
                            >
                                <option value="">All</option>
                                <option value="yes">Active</option>
                                <option value="no">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="mb-4 flex items-center gap-2 border-b border-slate-100 pb-3">
                    <Squares2X2Icon class="h-5 w-5 text-slate-500" />
                    <h2 class="text-sm font-semibold text-slate-900">On this page — by category</h2>
                </div>
                <div v-if="categoryCards.length" class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
                    <div
                        v-for="category in categoryCards"
                        :key="category.key"
                        class="rounded-lg border border-slate-100 bg-slate-50/80 px-3 py-2.5 transition-colors hover:border-sky-200 hover:bg-sky-50/50"
                    >
                        <p class="truncate text-xs font-medium text-slate-600">
                            {{ category.label || categoryLabel(category.key) }}
                        </p>
                        <p class="mt-1 font-display text-xl font-bold text-slate-900">{{ category.count }}</p>
                    </div>
                </div>
                <p v-else class="text-sm text-slate-500">No category data on this page.</p>
            </div>

            <div>
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-slate-900">Provider profiles</h2>
                    <span v-if="providers.total != null" class="text-xs text-slate-500">
                        {{ providers.total }} total
                    </span>
                </div>
                <div v-if="providerRows.length" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <div
                        v-for="provider in providerRows"
                        :key="provider.id"
                        class="group rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition-all hover:border-sky-200/80 hover:shadow-md"
                    >
                        <div class="flex items-start gap-3">
                            <div
                                class="relative flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-full bg-gradient-to-br from-sky-100 to-indigo-100 text-sm font-bold text-sky-800 ring-2 ring-white"
                            >
                                <img
                                    v-if="avatarUrl(provider)"
                                    :src="avatarUrl(provider)"
                                    alt=""
                                    class="h-full w-full object-cover"
                                />
                                <span v-else>{{ initial(provider) }}</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-semibold text-slate-900">
                                    {{ provider.business_name }}
                                </p>
                                <p class="truncate text-xs text-slate-500">
                                    {{ provider.user?.first_name }} {{ provider.user?.last_name }}
                                </p>
                                <p class="truncate text-xs text-slate-400">
                                    {{ provider.user?.email }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-1.5">
                            <span
                                class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700"
                            >
                                {{ serviceTypeLabelForValue(primaryServiceValue(provider)) }}
                            </span>
                            <span
                                class="rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="
                                    provider.is_verified
                                        ? 'bg-emerald-100 text-emerald-800'
                                        : 'bg-amber-100 text-amber-800'
                                "
                            >
                                {{ provider.is_verified ? 'Verified' : 'Pending' }}
                            </span>
                            <span
                                class="rounded-full bg-violet-100 px-2 py-0.5 text-xs font-medium text-violet-800"
                            >
                                {{ provider.leads_count || 0 }} leads
                            </span>
                            <span
                                class="inline-flex items-center gap-0.5 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800"
                            >
                                <StarIcon class="h-3 w-3" />
                                {{ provider.reviews_count || 0 }}
                            </span>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2 border-t border-slate-100 pt-3">
                            <Link
                                :href="`/admin/providers/${provider.slug}`"
                                class="inline-flex flex-1 items-center justify-center rounded-lg bg-sky-600 px-3 py-2 text-xs font-semibold text-white transition-colors hover:bg-sky-700 sm:flex-none"
                            >
                                View
                            </Link>
                            <Link
                                :href="`/admin/providers/${provider.slug}/edit`"
                                class="inline-flex flex-1 items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition-colors hover:bg-slate-50 sm:flex-none"
                            >
                                Edit
                            </Link>
                        </div>
                    </div>
                </div>
                <div
                    v-else
                    class="rounded-xl border border-dashed border-slate-200 bg-slate-50/50 py-12 text-center"
                >
                    <p class="text-sm text-slate-600">No providers match your filters.</p>
                    <button
                        type="button"
                        class="mt-3 text-sm font-medium text-sky-700 hover:text-sky-800"
                        @click="clearFilters"
                    >
                        Clear filters
                    </button>
                </div>
            </div>

            <div
                v-if="providers.links?.length > 3"
                class="flex justify-center rounded-xl border border-slate-200 bg-white py-3 shadow-sm"
            >
                <nav class="flex flex-wrap items-center justify-center gap-1">
                    <Link
                        v-for="link in providers.links"
                        :key="`${link.label}-${link.url}`"
                        :href="link.url"
                        class="min-w-[2.25rem] rounded-lg px-3 py-2 text-sm font-medium transition-colors"
                        :class="
                            link.active
                                ? 'bg-sky-600 text-white shadow-sm'
                                : link.url
                                  ? 'text-slate-600 hover:bg-slate-100'
                                  : 'cursor-not-allowed text-slate-300'
                        "
                        v-html="link.label"
                    />
                </nav>
            </div>
        </div>
    </AdminLayout>
</template>
