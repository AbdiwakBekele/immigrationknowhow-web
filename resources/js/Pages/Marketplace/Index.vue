<script setup>
import { ref, reactive, computed, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { debounce } from 'lodash-es';
import AppLayout from '@/Layouts/AppLayout.vue';
import ProviderCard from '@/Components/marketplace/ProviderCard.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import Button from '@/Components/ui/Button.vue';
import {
    MagnifyingGlassIcon,
    AdjustmentsHorizontalIcon,
    XMarkIcon,
    SparklesIcon,
    FunnelIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    providers: Object,
    filters: Object,
    serviceTypes: Array,
    languages: Object,
    featuredProviders: Array,
});

const showFilters = ref(false);

const form = reactive({
    search: props.filters?.search || '',
    service_type: props.filters?.service_type || '',
    language: props.filters?.language || '',
    location: props.filters?.location || '',
    remote_only: props.filters?.remote_only || false,
    free_consultation: props.filters?.free_consultation || false,
    favorites: [true, 'true', 1, '1', 'on'].includes(props.filters?.favorites),
    sort: props.filters?.sort || 'rating',
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

const applyFilters = () => {
    router.get(route('marketplace.index'), form, {
        preserveState: true,
        preserveScroll: true,
    });
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
                            @click="showFilters = !showFilters"
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

                <transition
                    enter-active-class="transition-all duration-200 ease-out"
                    enter-from-class="opacity-0 -translate-y-2"
                    enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition-all duration-150 ease-in"
                    leave-from-class="opacity-100 translate-y-0"
                    leave-to-class="opacity-0 -translate-y-2"
                >
                    <div v-if="showFilters" class="mt-3 grid grid-cols-1 gap-3 border-t border-slate-100 pt-3 sm:grid-cols-2 lg:grid-cols-4">
                        <Select
                            v-model="form.language"
                            :options="languageOptions"
                            label="Language"
                            size="auth"
                            @update:model-value="applyFilters"
                        />
                        <Input
                            v-model="form.location"
                            label="Location"
                            placeholder="City or state"
                            @blur="applyFilters"
                        />
                        <div>
                            <p class="mb-3 block text-base font-medium text-slate-700">Options</p>
                            <div class="space-y-3">
                            <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5">
                                <input
                                    v-model="form.remote_only"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300 text-primary-600"
                                    @change="applyFilters"
                                />
                                <span class="text-sm text-slate-700">Remote services only</span>
                            </label>
                            <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5">
                                <input
                                    v-model="form.free_consultation"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300 text-primary-600"
                                    @change="applyFilters"
                                />
                                <span class="text-sm text-slate-700">Free consultation</span>
                            </label>
                            <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5">
                                <input
                                    v-model="form.favorites"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300 text-primary-600"
                                    @change="applyFilters"
                                />
                                <span class="text-sm text-slate-700">My favorites</span>
                            </label>
                            </div>
                        </div>
                        <div class="flex flex-col">
                            <span class="mb-3 block text-base font-medium text-transparent select-none">Actions</span>
                            <Button v-if="hasActiveFilters || form.search" variant="ghost" size="sm" @click="clearFilters">
                                <XMarkIcon class="h-4 w-4" />
                                Clear all
                            </Button>
                        </div>
                    </div>
                </transition>
            </section>

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
