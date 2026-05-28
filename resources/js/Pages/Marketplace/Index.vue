<script setup>
import { ref, reactive, computed, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Dialog,
    DialogPanel,
    DialogTitle,
    TransitionChild,
    TransitionRoot,
} from '@headlessui/vue';
import { debounce } from 'lodash-es';
import AppLayout from '@/Layouts/AppLayout.vue';
import ProviderCard from '@/Components/marketplace/ProviderCard.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import Button from '@/Components/ui/Button.vue';
import {
    MagnifyingGlassIcon,
    AdjustmentsHorizontalIcon,
    ChatBubbleLeftRightIcon,
    XMarkIcon,
    SparklesIcon,
    FunnelIcon,
    HeartIcon,
    LanguageIcon,
    MapPinIcon,
    VideoCameraIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    providers: Object,
    filters: Object,
    serviceTypes: Array,
    languages: Object,
    featuredProviders: Array,
});

const showFilters = ref(false);
const isFilterEnabled = (value) => [true, 'true', 1, '1', 'on'].includes(value);

const form = reactive({
    search: props.filters?.search || '',
    service_type: props.filters?.service_type || '',
    language: props.filters?.language || '',
    location: props.filters?.location || '',
    remote_only: isFilterEnabled(props.filters?.remote_only),
    free_consultation: isFilterEnabled(props.filters?.free_consultation),
    favorites: isFilterEnabled(props.filters?.favorites),
    sort: props.filters?.sort || 'rating',
});

const filterDraft = reactive({
    service_type: '',
    language: '',
    location: '',
    remote_only: false,
    free_consultation: false,
    favorites: false,
});

const sortOptions = [
    { value: 'rating', label: 'Top Rated' },
    { value: 'reviews', label: 'Most Reviews' },
    { value: 'newest', label: 'Newest' },
    { value: 'experience', label: 'Most Experienced' },
];

const languageOptions = computed(() => {
    return [
        { value: '', label: 'All Languages' },
        ...Object.entries(props.languages).map(([code, name]) => ({
            value: code,
            label: name,
        })),
    ];
});

const serviceTypeOptions = computed(() => {
    return [
        { value: '', label: 'All Services' },
        ...props.serviceTypes,
    ];
});

const hasActiveFilters = computed(() => {
    return form.service_type || form.language || form.location || form.remote_only || form.free_consultation || form.favorites;
});
const activeFilterCount = computed(() => {
    return [
        Boolean(form.service_type),
        Boolean(form.language),
        Boolean(form.location),
        Boolean(form.remote_only),
        Boolean(form.free_consultation),
        Boolean(form.favorites),
    ].filter(Boolean).length;
});

const draftFilterCount = computed(() => {
    return [
        Boolean(filterDraft.service_type),
        Boolean(filterDraft.language),
        Boolean(filterDraft.location),
        Boolean(filterDraft.remote_only),
        Boolean(filterDraft.free_consultation),
        Boolean(filterDraft.favorites),
    ].filter(Boolean).length;
});

const activeFilterPills = computed(() => {
    const pills = [];
    if (form.service_type) {
        const match = serviceTypeOptions.value.find((item) => item.value === form.service_type);
        pills.push(`Service: ${match?.label || form.service_type}`);
    }
    if (form.language) {
        const match = languageOptions.value.find((item) => item.value === form.language);
        pills.push(`Language: ${match?.label || form.language}`);
    }
    if (form.location) {
        pills.push(`Location: ${form.location}`);
    }
    if (form.remote_only) {
        pills.push('Remote only');
    }
    if (form.free_consultation) {
        pills.push('Free consultation');
    }
    if (form.favorites) {
        pills.push('Favorites');
    }
    return pills;
});

const syncFilterDraft = () => {
    filterDraft.service_type = form.service_type;
    filterDraft.language = form.language;
    filterDraft.location = form.location;
    filterDraft.remote_only = form.remote_only;
    filterDraft.free_consultation = form.free_consultation;
    filterDraft.favorites = form.favorites;
};

const applyFilters = () => {
    router.get(route('marketplace.index'), form, {
        preserveState: true,
        preserveScroll: true,
    });
};

const openFilters = () => {
    syncFilterDraft();
    showFilters.value = true;
};

const closeFilters = () => {
    showFilters.value = false;
    syncFilterDraft();
};

const resetFilterDraft = () => {
    filterDraft.service_type = '';
    filterDraft.language = '';
    filterDraft.location = '';
    filterDraft.remote_only = false;
    filterDraft.free_consultation = false;
    filterDraft.favorites = false;
};

const applyFilterModal = () => {
    form.service_type = filterDraft.service_type;
    form.language = filterDraft.language;
    form.location = filterDraft.location;
    form.remote_only = filterDraft.remote_only;
    form.free_consultation = filterDraft.free_consultation;
    form.favorites = filterDraft.favorites;
    showFilters.value = false;
    applyFilters();
};

const clearFilters = () => {
    form.search = '';
    form.service_type = '';
    form.language = '';
    form.location = '';
    form.remote_only = false;
    form.free_consultation = false;
    form.favorites = false;
    form.sort = 'rating';
    resetFilterDraft();
    showFilters.value = false;
    applyFilters();
};

// Debounced search
const debouncedSearch = debounce(() => {
    applyFilters();
}, 300);

watch(() => form.search, () => {
    debouncedSearch();
});

watch(() => form.sort, () => {
    applyFilters();
});
</script>

<template>
    <Head title="Find Service Providers" />

    <AppLayout>
        <div class="mx-auto max-w-[1440px] space-y-4 px-4 py-4 sm:px-6 sm:py-5 lg:px-8 lg:py-6">
            <section class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-sky-700 via-indigo-700 to-violet-700 px-5 py-5 text-white shadow-xl sm:px-7 sm:py-6">
                <div class="pointer-events-none absolute -left-12 -top-8 h-40 w-40 rounded-full bg-white/15 blur-2xl"></div>
                <div class="pointer-events-none absolute -right-12 top-5 h-44 w-44 rounded-full bg-fuchsia-200/20 blur-2xl"></div>
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-2xl">
                        <p class="text-xs font-semibold uppercase tracking-wider text-sky-100">Marketplace</p>
                        <h1 class="mt-1.5 text-3xl font-display font-bold sm:text-4xl">Find trusted service providers</h1>
                        <p class="mt-2.5 text-sm text-sky-50 sm:text-base">
                            Browse verified professionals for legal support, taxes, language services, and more.
                        </p>
                    </div>
                    <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3">
                        <div class="rounded-2xl border border-white/20 bg-white/15 px-4 py-2.5 backdrop-blur-sm">
                            <p class="text-xs text-sky-100">Providers</p>
                            <p class="text-lg font-semibold">{{ providers.total }}</p>
                        </div>
                        <div class="rounded-2xl border border-white/20 bg-white/15 px-4 py-2.5 backdrop-blur-sm">
                            <p class="text-xs text-sky-100">Featured</p>
                            <p class="text-lg font-semibold">{{ featuredProviders?.length || 0 }}</p>
                        </div>
                        <div class="col-span-2 rounded-2xl border border-white/20 bg-white/15 px-4 py-2.5 backdrop-blur-sm sm:col-span-1">
                            <p class="text-xs text-sky-100">Active filters</p>
                            <p class="text-lg font-semibold">{{ activeFilterCount }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="sticky top-20 z-20 rounded-2xl border border-slate-200/80 bg-white/95 p-3.5 shadow-sm backdrop-blur sm:p-4">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
                    <div class="relative flex-1">
                        <MagnifyingGlassIcon class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                        <input
                            v-model="form.search"
                            type="text"
                            placeholder="Search by provider, service, language, or keyword..."
                            class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pl-11 pr-4 text-sm text-slate-700 outline-none transition focus:border-primary-300 focus:bg-white focus:ring-2 focus:ring-primary-100"
                        />
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <Select
                            v-model="form.service_type"
                            :options="serviceTypeOptions"
                            placeholder="Service Type"
                            class="w-44"
                            @update:model-value="applyFilters"
                        />
                        <Select
                            v-model="form.sort"
                            :options="sortOptions"
                            class="w-40"
                        />
                        <Button
                            variant="ghost"
                            @click="openFilters"
                            :class="[
                                'border border-transparent',
                                showFilters || hasActiveFilters ? 'bg-primary-50 text-primary-700 border-primary-100' : 'hover:bg-slate-100'
                            ]"
                        >
                            <FunnelIcon class="h-5 w-5" />
                            Filters
                            <span
                                v-if="activeFilterCount"
                                class="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-primary-600 px-1 text-xs font-semibold text-white"
                            >
                                {{ activeFilterCount }}
                            </span>
                        </Button>
                    </div>
                </div>

                <div
                    v-if="activeFilterPills.length || form.search"
                    class="mt-3 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3"
                >
                    <span
                        v-for="pill in activeFilterPills"
                        :key="pill"
                        class="inline-flex items-center rounded-full border border-sky-100 bg-sky-50 px-3 py-1 text-xs font-medium text-sky-700"
                    >
                        {{ pill }}
                    </span>
                    <span
                        v-if="form.search"
                        class="inline-flex items-center rounded-full border border-violet-100 bg-violet-50 px-3 py-1 text-xs font-medium text-violet-700"
                    >
                        Search: "{{ form.search }}"
                    </span>
                    <button
                        v-if="hasActiveFilters || form.search"
                        type="button"
                        class="ml-auto inline-flex items-center gap-1 rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-600 transition hover:bg-slate-100"
                        @click="clearFilters"
                    >
                        <XMarkIcon class="h-3.5 w-3.5" />
                        Clear all
                    </button>
                </div>
            </section>

            <TransitionRoot as="template" :show="showFilters">
                <Dialog as="div" class="relative z-50" @close="closeFilters">
                    <TransitionChild
                        as="template"
                        enter="duration-200 ease-out"
                        enter-from="opacity-0"
                        enter-to="opacity-100"
                        leave="duration-150 ease-in"
                        leave-from="opacity-100"
                        leave-to="opacity-0"
                    >
                        <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm" />
                    </TransitionChild>

                    <div class="fixed inset-0 overflow-y-auto">
                        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-6">
                            <TransitionChild
                                as="template"
                                enter="duration-200 ease-out"
                                enter-from="opacity-0 translate-y-6 scale-95"
                                enter-to="opacity-100 translate-y-0 scale-100"
                                leave="duration-150 ease-in"
                                leave-from="opacity-100 translate-y-0 scale-100"
                                leave-to="opacity-0 translate-y-6 scale-95"
                            >
                                <DialogPanel class="w-full max-w-3xl transform rounded-3xl bg-white text-left align-middle shadow-2xl transition-all">
                                    <div class="relative overflow-hidden rounded-t-3xl bg-gradient-to-r from-sky-700 via-indigo-700 to-violet-700 px-5 py-5 text-white sm:px-6">
                                        <div class="pointer-events-none absolute -right-8 -top-10 h-32 w-32 rounded-full bg-white/20 blur-2xl"></div>
                                        <div class="pointer-events-none absolute bottom-0 left-8 h-20 w-20 rounded-full bg-sky-200/20 blur-2xl"></div>
                                        <div class="relative flex items-start justify-between gap-4">
                                            <div class="flex gap-3">
                                                <span class="mt-1 inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/25">
                                                    <AdjustmentsHorizontalIcon class="h-5 w-5" />
                                                </span>
                                                <div>
                                                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-sky-100">Provider Filters</p>
                                                    <DialogTitle as="h2" class="mt-1 text-2xl font-display font-bold">
                                                        Refine your provider search
                                                    </DialogTitle>
                                                    <p class="mt-2 max-w-xl text-sm text-sky-50">
                                                        Choose service, language, location, and preferences to find the right match faster.
                                                    </p>
                                                </div>
                                            </div>

                                            <button
                                                type="button"
                                                class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/60"
                                                aria-label="Close filters"
                                                @click="closeFilters"
                                            >
                                                <XMarkIcon class="h-5 w-5" />
                                            </button>
                                        </div>
                                    </div>

                                    <form class="space-y-5 p-5 sm:p-6" @submit.prevent="applyFilterModal">
                                        <div class="grid gap-4 md:grid-cols-2">
                                            <div class="rounded-2xl border border-slate-100 bg-slate-50/80 p-4">
                                                <div class="mb-3 flex items-center gap-2">
                                                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-blue-100 text-blue-700">
                                                        <FunnelIcon class="h-4 w-4" />
                                                    </span>
                                                    <div>
                                                        <p class="text-sm font-semibold text-slate-900">Service type</p>
                                                        <p class="text-xs text-slate-500">Pick the expertise you need.</p>
                                                    </div>
                                                </div>
                                                <Select
                                                    v-model="filterDraft.service_type"
                                                    :options="serviceTypeOptions"
                                                    placeholder="All Services"
                                                />
                                            </div>

                                            <div class="rounded-2xl border border-slate-100 bg-slate-50/80 p-4">
                                                <div class="mb-3 flex items-center gap-2">
                                                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-violet-100 text-violet-700">
                                                        <LanguageIcon class="h-4 w-4" />
                                                    </span>
                                                    <div>
                                                        <p class="text-sm font-semibold text-slate-900">Language</p>
                                                        <p class="text-xs text-slate-500">Find providers you can speak with.</p>
                                                    </div>
                                                </div>
                                                <Select
                                                    v-model="filterDraft.language"
                                                    :options="languageOptions"
                                                    placeholder="All Languages"
                                                />
                                            </div>

                                            <div class="rounded-2xl border border-slate-100 bg-slate-50/80 p-4 md:col-span-2">
                                                <div class="mb-3 flex items-center gap-2">
                                                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">
                                                        <MapPinIcon class="h-4 w-4" />
                                                    </span>
                                                    <div>
                                                        <p class="text-sm font-semibold text-slate-900">Location</p>
                                                        <p class="text-xs text-slate-500">Search by city, state, or nearby area.</p>
                                                    </div>
                                                </div>
                                                <Input
                                                    v-model="filterDraft.location"
                                                    placeholder="City or state"
                                                />
                                            </div>
                                        </div>

                                        <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                                            <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                                                <div>
                                                    <p class="text-sm font-semibold text-slate-900">Preferences</p>
                                                    <p class="text-xs text-slate-500">Select any options that matter to your search.</p>
                                                </div>
                                                <p v-if="draftFilterCount" class="text-xs font-medium text-primary-700">
                                                    {{ draftFilterCount }} selected
                                                </p>
                                            </div>

                                            <div class="grid gap-3 sm:grid-cols-3">
                                                <label
                                                    :class="[
                                                        'flex cursor-pointer gap-3 rounded-2xl border p-4 transition',
                                                        filterDraft.remote_only ? 'border-primary-200 bg-primary-50 ring-2 ring-primary-100' : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50'
                                                    ]"
                                                >
                                                    <input v-model="filterDraft.remote_only" type="checkbox" class="sr-only" />
                                                    <span
                                                        :class="[
                                                            'inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl',
                                                            filterDraft.remote_only ? 'bg-primary-600 text-white' : 'bg-slate-100 text-slate-500'
                                                        ]"
                                                    >
                                                        <VideoCameraIcon class="h-5 w-5" />
                                                    </span>
                                                    <span>
                                                        <span class="block text-sm font-semibold text-slate-900">Remote only</span>
                                                        <span class="mt-1 block text-xs leading-5 text-slate-500">Meet online from anywhere.</span>
                                                    </span>
                                                </label>

                                                <label
                                                    :class="[
                                                        'flex cursor-pointer gap-3 rounded-2xl border p-4 transition',
                                                        filterDraft.free_consultation ? 'border-primary-200 bg-primary-50 ring-2 ring-primary-100' : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50'
                                                    ]"
                                                >
                                                    <input v-model="filterDraft.free_consultation" type="checkbox" class="sr-only" />
                                                    <span
                                                        :class="[
                                                            'inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl',
                                                            filterDraft.free_consultation ? 'bg-primary-600 text-white' : 'bg-slate-100 text-slate-500'
                                                        ]"
                                                    >
                                                        <ChatBubbleLeftRightIcon class="h-5 w-5" />
                                                    </span>
                                                    <span>
                                                        <span class="block text-sm font-semibold text-slate-900">Free consultation</span>
                                                        <span class="mt-1 block text-xs leading-5 text-slate-500">Start with a no-cost call.</span>
                                                    </span>
                                                </label>

                                                <label
                                                    :class="[
                                                        'flex cursor-pointer gap-3 rounded-2xl border p-4 transition',
                                                        filterDraft.favorites ? 'border-primary-200 bg-primary-50 ring-2 ring-primary-100' : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50'
                                                    ]"
                                                >
                                                    <input v-model="filterDraft.favorites" type="checkbox" class="sr-only" />
                                                    <span
                                                        :class="[
                                                            'inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl',
                                                            filterDraft.favorites ? 'bg-primary-600 text-white' : 'bg-slate-100 text-slate-500'
                                                        ]"
                                                    >
                                                        <HeartIcon class="h-5 w-5" />
                                                    </span>
                                                    <span>
                                                        <span class="block text-sm font-semibold text-slate-900">My favorites</span>
                                                        <span class="mt-1 block text-xs leading-5 text-slate-500">Show saved providers first.</span>
                                                    </span>
                                                </label>
                                            </div>
                                        </div>

                                        <div class="flex flex-col gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:items-center sm:justify-between">
                                            <Button type="button" variant="secondary" size="sm" @click="resetFilterDraft">
                                                <XMarkIcon class="h-4 w-4" />
                                                Clear filters
                                            </Button>

                                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                                                <Button type="button" variant="ghost" size="sm" @click="closeFilters">
                                                    Cancel
                                                </Button>
                                                <Button type="submit" size="sm">
                                                    Apply filters
                                                    <span
                                                        v-if="draftFilterCount"
                                                        class="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-white/20 px-1 text-xs font-semibold text-white"
                                                    >
                                                        {{ draftFilterCount }}
                                                    </span>
                                                </Button>
                                            </div>
                                        </div>
                                    </form>
                                </DialogPanel>
                            </TransitionChild>
                        </div>
                    </div>
                </Dialog>
            </TransitionRoot>

            <section v-if="featuredProviders?.length && !hasActiveFilters && !form.search" class="space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="inline-flex items-center gap-2 text-lg font-semibold text-slate-900">
                        <SparklesIcon class="h-5 w-5 text-amber-500" />
                        Featured Providers
                    </h2>
                    <span class="rounded-full border border-amber-100 bg-amber-50 px-3 py-1 text-xs font-medium text-amber-700">Recommended</span>
                </div>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <ProviderCard
                        v-for="provider in featuredProviders"
                        :key="provider.id"
                        :provider="provider"
                    />
                </div>
            </section>

            <section class="space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-slate-900">
                        {{ hasActiveFilters || form.search ? 'Search Results' : 'All Providers' }}
                        <span class="font-normal text-slate-400">({{ providers.total }})</span>
                    </h2>
                    <p class="hidden text-sm text-slate-500 md:block">
                        Page {{ providers.current_page }} of {{ providers.last_page }}
                    </p>
                </div>

                <div v-if="providers.data.length" class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <ProviderCard
                        v-for="provider in providers.data"
                        :key="provider.id"
                        :provider="provider"
                    />
                </div>

                <div v-else class="rounded-2xl border border-dashed border-slate-300 bg-slate-50/60 p-10 text-center">
                    <MagnifyingGlassIcon class="mx-auto h-11 w-11 text-slate-300" />
                    <h3 class="mt-3 text-lg font-semibold text-slate-900">No providers found</h3>
                    <p class="mt-1 text-sm text-slate-500">Try adjusting your filters or search terms to find better matches.</p>
                    <Button
                        v-if="hasActiveFilters || form.search"
                        variant="outline"
                        class="mt-4"
                        @click="clearFilters"
                    >
                        Clear all filters
                    </Button>
                </div>

                <div v-if="providers.last_page > 1" class="flex flex-wrap items-center justify-center gap-2 pt-2">
                    <template v-for="link in providers.links" :key="link.label">
                        <Link
                            v-if="link.url"
                            :href="link.url || '#'"
                            :class="[
                                'rounded-xl px-4 py-2 text-sm transition-colors',
                                link.active
                                    ? 'bg-primary-600 text-white'
                                    : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50'
                            ]"
                            v-html="link.label"
                            preserve-scroll
                        />
                        <span
                            v-else
                            class="cursor-not-allowed rounded-lg bg-slate-100 px-4 py-2 text-sm text-slate-400"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
