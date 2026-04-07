<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { debounce } from 'lodash-es';
import AppLayout from '@/Components/layout/AppLayout.vue';
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

const form = ref({
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
    return form.value.service_type || form.value.language || form.value.location || form.value.remote_only || form.value.free_consultation;
});

const applyFilters = () => {
    router.get(route('marketplace.index'), form.value, {
        preserveState: true,
        preserveScroll: true,
    });
};

const clearFilters = () => {
    form.value = {
        search: '',
        service_type: '',
        language: '',
        location: '',
        remote_only: false,
        free_consultation: false,
        sort: 'rating',
    };
    applyFilters();
};

// Debounced search
const debouncedSearch = debounce(() => {
    applyFilters();
}, 300);

watch(() => form.value.search, () => {
    debouncedSearch();
});

watch(() => form.value.sort, () => {
    applyFilters();
});
</script>

<template>
    <Head title="Find Service Providers" />

    <AppLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="section-title">Find Service Providers</h1>
                    <p class="section-subtitle">
                        Connect with trusted professionals who can help you navigate your immigration journey
                    </p>
                </div>
            </div>

            <!-- Search and filters bar -->
            <div class="bg-white rounded-2xl shadow-soft p-4">
                <div class="flex flex-col lg:flex-row gap-4">
                    <!-- Search -->
                    <div class="flex-1 relative">
                        <MagnifyingGlassIcon class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-neutral-400" />
                        <input
                            v-model="form.search"
                            type="text"
                            placeholder="Search by name, service, or keyword..."
                            class="w-full pl-11 pr-4 py-3 text-sm bg-neutral-50 border-0 rounded-xl focus:bg-white focus:ring-2 focus:ring-primary-500/20 transition-all"
                        />
                    </div>

                    <!-- Quick filters -->
                    <div class="flex flex-wrap items-center gap-3">
                        <Select
                            v-model="form.service_type"
                            :options="serviceTypeOptions"
                            placeholder="Service Type"
                            class="w-40"
                            @update:model-value="applyFilters"
                        />
                        
                        <Select
                            v-model="form.sort"
                            :options="sortOptions"
                            class="w-36"
                        />

                        <Button
                            variant="ghost"
                            @click="showFilters = !showFilters"
                            :class="{ 'bg-primary-50 text-primary-600': hasActiveFilters }"
                        >
                            <AdjustmentsHorizontalIcon class="w-5 h-5" />
                            Filters
                            <span v-if="hasActiveFilters" class="w-2 h-2 bg-primary-500 rounded-full"></span>
                        </Button>
                    </div>
                </div>

                <!-- Expanded filters -->
                <transition
                    enter-active-class="transition-all duration-200 ease-out"
                    enter-from-class="opacity-0 -translate-y-2"
                    enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition-all duration-150 ease-in"
                    leave-from-class="opacity-100 translate-y-0"
                    leave-to-class="opacity-0 -translate-y-2"
                >
                    <div v-if="showFilters" class="mt-4 pt-4 border-t border-neutral-100">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
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
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        v-model="form.remote_only"
                                        class="w-4 h-4 rounded border-neutral-300 text-primary-600"
                                        @change="applyFilters"
                                    />
                                    <span class="text-sm text-neutral-700">Remote services only</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        v-model="form.free_consultation"
                                        class="w-4 h-4 rounded border-neutral-300 text-primary-600"
                                        @change="applyFilters"
                                    />
                                    <span class="text-sm text-neutral-700">Free consultation</span>
                                </label>
                            </div>

                            <div class="flex items-end">
                                <Button
                                    v-if="hasActiveFilters"
                                    variant="ghost"
                                    size="sm"
                                    @click="clearFilters"
                                >
                                    <XMarkIcon class="w-4 h-4" />
                                    Clear filters
                                </Button>
                            </div>
                        </div>
                    </div>
                </transition>
            </div>

            <!-- Featured providers -->
            <div v-if="featuredProviders?.length && !hasActiveFilters && !form.search" class="space-y-4">
                <h2 class="text-lg font-semibold text-neutral-900">Featured Providers</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <ProviderCard
                        v-for="provider in featuredProviders"
                        :key="provider.id"
                        :provider="provider"
                    />
                </div>
            </div>

            <!-- Results -->
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-neutral-900">
                        {{ hasActiveFilters || form.search ? 'Results' : 'All Providers' }}
                        <span class="text-neutral-400 font-normal">({{ providers.total }})</span>
                    </h2>
                </div>

                <!-- Provider grid -->
                <div v-if="providers.data.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <ProviderCard
                        v-for="provider in providers.data"
                        :key="provider.id"
                        :provider="provider"
                    />
                </div>

                <!-- Empty state -->
                <div v-else class="empty-state">
                    <MagnifyingGlassIcon class="empty-state-icon" />
                    <h3 class="empty-state-title">No providers found</h3>
                    <p class="empty-state-text">
                        Try adjusting your filters or search terms to find what you're looking for.
                    </p>
                    <Button
                        v-if="hasActiveFilters || form.search"
                        variant="outline"
                        class="mt-4"
                        @click="clearFilters"
                    >
                        Clear all filters
                    </Button>
                </div>

                <!-- Pagination -->
                <div v-if="providers.last_page > 1" class="flex items-center justify-center gap-2 pt-6">
                    <Link
                        v-for="link in providers.links"
                        :key="link.label"
                        :href="link.url"
                        :class="[
                            'px-4 py-2 text-sm rounded-lg transition-colors',
                            link.active
                                ? 'bg-primary-600 text-white'
                                : link.url
                                    ? 'bg-white text-neutral-600 hover:bg-neutral-50 border border-neutral-200'
                                    : 'bg-neutral-100 text-neutral-400 cursor-not-allowed'
                        ]"
                        v-html="link.label"
                        preserve-scroll
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
