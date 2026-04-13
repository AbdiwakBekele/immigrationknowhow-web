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
    return form.service_type || form.language || form.location || form.remote_only || form.free_consultation;
});
const activeFilterCount = computed(() => {
    return [
        Boolean(form.service_type),
        Boolean(form.language),
        Boolean(form.location),
        Boolean(form.remote_only),
        Boolean(form.free_consultation),
    ].filter(Boolean).length;
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
        <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <section class="overflow-hidden rounded-3xl bg-gradient-to-r from-sky-600 via-indigo-600 to-violet-600 px-6 py-8 text-white shadow-xl sm:px-8">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-2xl">
                        <p class="text-xs font-semibold uppercase tracking-wider text-sky-100">Marketplace</p>
                        <h1 class="mt-2 text-3xl font-display font-bold sm:text-4xl">Find trusted service providers</h1>
                        <p class="mt-3 text-sm text-sky-50 sm:text-base">
                            Browse verified professionals for legal support, taxes, language services, and more.
                        </p>
                    </div>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        <div class="rounded-xl bg-white/15 px-4 py-3 backdrop-blur-sm">
                            <p class="text-xs text-sky-100">Providers</p>
                            <p class="text-lg font-semibold">{{ providers.total }}</p>
                        </div>
                        <div class="rounded-xl bg-white/15 px-4 py-3 backdrop-blur-sm">
                            <p class="text-xs text-sky-100">Featured</p>
                            <p class="text-lg font-semibold">{{ featuredProviders?.length || 0 }}</p>
                        </div>
                        <div class="rounded-xl bg-white/15 px-4 py-3 backdrop-blur-sm col-span-2 sm:col-span-1">
                            <p class="text-xs text-sky-100">Active filters</p>
                            <p class="text-lg font-semibold">{{ activeFilterCount }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
                    <div class="relative flex-1">
                        <MagnifyingGlassIcon class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                        <input
                            v-model="form.search"
                            type="text"
                            placeholder="Search by provider, service, language, or keyword..."
                            class="w-full rounded-xl border border-slate-200 bg-slate-50 py-3 pl-11 pr-4 text-sm text-slate-700 outline-none transition focus:border-primary-300 focus:bg-white focus:ring-2 focus:ring-primary-100"
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
                                showFilters || hasActiveFilters ? 'bg-primary-50 text-primary-700 border-primary-100' : ''
                            ]"
                        >
                            <AdjustmentsHorizontalIcon class="h-5 w-5" />
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

                <transition
                    enter-active-class="transition-all duration-200 ease-out"
                    enter-from-class="opacity-0 -translate-y-2"
                    enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition-all duration-150 ease-in"
                    leave-from-class="opacity-100 translate-y-0"
                    leave-to-class="opacity-0 -translate-y-2"
                >
                    <div v-if="showFilters" class="mt-4 grid grid-cols-1 gap-4 border-t border-slate-100 pt-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Select
                            v-model="form.language"
                            :options="languageOptions"
                            label="Language"
                            @update:model-value="applyFilters"
                        />
                        <Input
                            v-model="form.location"
                            label="Location"
                            placeholder="City or state"
                            @blur="applyFilters"
                        />
                        <div class="space-y-3 pt-6">
                            <label class="flex cursor-pointer items-center gap-2">
                                <input
                                    v-model="form.remote_only"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300 text-primary-600"
                                    @change="applyFilters"
                                />
                                <span class="text-sm text-slate-700">Remote services only</span>
                            </label>
                            <label class="flex cursor-pointer items-center gap-2">
                                <input
                                    v-model="form.free_consultation"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300 text-primary-600"
                                    @change="applyFilters"
                                />
                                <span class="text-sm text-slate-700">Free consultation</span>
                            </label>
                        </div>
                        <div class="flex items-end">
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
                    <h2 class="text-lg font-semibold text-slate-900">Featured Providers</h2>
                    <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-medium text-amber-700">Recommended</span>
                </div>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <ProviderCard
                        v-for="provider in featuredProviders"
                        :key="provider.id"
                        :provider="provider"
                    />
                </div>
            </section>

            <section class="space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-slate-900">
                        {{ hasActiveFilters || form.search ? 'Search Results' : 'All Providers' }}
                        <span class="font-normal text-slate-400">({{ providers.total }})</span>
                    </h2>
                </div>

                <div v-if="providers.data.length" class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
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
                            :href="link.url"
                            :class="[
                                'rounded-lg px-4 py-2 text-sm transition-colors',
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
