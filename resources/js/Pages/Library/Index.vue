<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout.vue';
import EbookShareCampaignBanner from '@/Components/library/EbookShareCampaignBanner.vue';
import {
    AdjustmentsHorizontalIcon,
    BookOpenIcon,
    BookmarkIcon,
    CalendarDaysIcon,
    DocumentTextIcon,
    ListBulletIcon,
    MagnifyingGlassIcon,
    MusicalNoteIcon,
    ShoppingCartIcon,
    Squares2X2Icon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useInertiaInfiniteScroll } from '@/composables/useInertiaInfiniteScroll';

const props = defineProps({
    items: { type: Object, required: true },
    categories: { type: Array, default: () => [] },
    authors: { type: Array, default: () => [] },
    regions: { type: Array, default: () => [] },
    types: { type: Array, default: () => [] },
    groupedItems: { type: Array, default: () => [] },
    forcedType: { type: String, default: null },
    filters: { type: Object, default: () => ({}) },
});

const page = usePage();
const viewMode = ref('grid');
const showFilterModal = ref(false);

const search = ref(props.filters?.search || '');
const selectedCategory = ref(props.filters?.category || '');
const selectedRegion = ref(props.filters?.region || '');
const selectedAuthor = ref(props.filters?.author || '');
const selectedType = ref(props.filters?.type || '');
const selectedAccess = ref(props.filters?.access || '');
const favoritesOnly = ref(props.filters?.favorites === 'true');
const selectedSort = ref(props.filters?.sort || 'newest');
let searchTimer = null;

const user = computed(() => page.props.auth?.user ?? null);
const isAuthenticated = computed(() => Boolean(user.value));
const cartCount = computed(() => Number(page.props.library_cart_count ?? 0) || 0);
const storefrontRoute = computed(() => {
    if (props.forcedType === 'ebook') {
        return 'library.ebooks';
    }

    if (props.forcedType === 'audiobook') {
        return 'library.audiobooks';
    }

    return 'library.index';
});

const buildLibraryQuery = () => {
    const term = String(search.value || '').trim();

    return {
        search: term || undefined,
        category: selectedCategory.value || undefined,
        region: selectedRegion.value || undefined,
        author: selectedAuthor.value || undefined,
        type: props.forcedType ? undefined : (selectedType.value || undefined),
        access: selectedAccess.value || undefined,
        favorites: favoritesOnly.value ? 'true' : undefined,
        sort: selectedSort.value || undefined,
    };
};

const {
    displayedItems,
    total: libraryTotal,
    loadingMore: libraryLoadingMore,
    loadMoreSentinel: libraryLoadMoreSentinel,
    hasMore: libraryHasMore,
} = useInertiaInfiniteScroll(
    () => props.items,
    {
        getUrl: () => route(storefrontRoute.value),
        buildQuery: buildLibraryQuery,
        only: 'items',
    },
);

const visibleItems = computed(() => displayedItems.value);
const resultTotal = computed(() => Number(libraryTotal.value ?? visibleItems.value.length) || 0);
const resultLabel = computed(() => {
    const noun = props.forcedType === 'audiobook' ? 'audiobook' : 'eBook';
    const plural = resultTotal.value === 1 ? noun : `${noun}s`;

    return `${resultTotal.value} ${plural} found`;
});

const hasActiveFilters = computed(() => Boolean(
    search.value
    || selectedCategory.value
    || selectedRegion.value
    || selectedAuthor.value
    || selectedType.value
    || selectedAccess.value
    || favoritesOnly.value
    || (selectedSort.value && selectedSort.value !== 'newest')
));
const activeModalFilterCount = computed(() => {
    let count = 0;
    if (selectedCategory.value) count += 1;
    if (selectedRegion.value) count += 1;
    if (selectedAuthor.value) count += 1;
    if (!props.forcedType && selectedType.value) count += 1;
    if (selectedAccess.value) count += 1;
    if (favoritesOnly.value) count += 1;
    if (selectedSort.value && selectedSort.value !== 'newest') count += 1;
    return count;
});

const applyFilters = () => {
    const term = String(search.value || '').trim();

    router.get(route(storefrontRoute.value), {
        search: term || undefined,
        category: selectedCategory.value || undefined,
        region: selectedRegion.value || undefined,
        author: selectedAuthor.value || undefined,
        type: props.forcedType ? undefined : (selectedType.value || undefined),
        access: selectedAccess.value || undefined,
        favorites: favoritesOnly.value ? 'true' : undefined,
        sort: selectedSort.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const queueSearch = () => {
    if (searchTimer) {
        clearTimeout(searchTimer);
    }

    searchTimer = setTimeout(() => {
        searchTimer = null;
        applyFilters();
    }, 350);
};

watch(search, queueSearch);

onBeforeUnmount(() => {
    if (searchTimer) {
        clearTimeout(searchTimer);
    }
});

const submitFilters = () => {
    showFilterModal.value = false;
    applyFilters();
};

const resetFilters = () => {
    search.value = '';
    selectedCategory.value = '';
    selectedRegion.value = '';
    selectedAuthor.value = '';
    selectedType.value = '';
    selectedAccess.value = '';
    favoritesOnly.value = false;
    selectedSort.value = 'newest';

    router.get(route(storefrontRoute.value), {}, {
        preserveState: true,
        preserveScroll: true,
    });
};

const resetModalFilters = () => {
    selectedCategory.value = '';
    selectedRegion.value = '';
    selectedAuthor.value = '';
    selectedType.value = '';
    selectedAccess.value = '';
    favoritesOnly.value = false;
    selectedSort.value = 'newest';
};

const requiresPayment = (item) => Boolean(item?.is_premium) || Number(item?.price || 0) > 0;

const formatPrice = (item) => {
    const amount = Number(item?.price || 0);
    if (!amount) {
        return 'Free';
    }

    return `${item.currency ?? 'USD'} ${amount.toFixed(2)}`;
};

const stripHtml = (value) => String(value || '').replace(/<[^>]*>/g, '').trim();

const itemDescription = (item) => stripHtml(item?.description) || 'A practical guide for your immigration journey.';

const itemCategory = (item) => item?.category?.name || 'General';

const itemCreator = (item) => item?.author || item?.library_author?.name || 'Unknown Author';

const itemFormat = (item) => {
    if (item?.has_audio_companion) {
        return 'PDF + Audio';
    }

    if (item?.type === 'audiobook') {
        return 'Audio';
    }

    return 'PDF';
};

const itemMeta = (item) => {
    if (item?.duration_formatted) {
        return item.duration_formatted;
    }

    if (item?.file_size_formatted && item.file_size_formatted !== 'N/A') {
        return item.file_size_formatted;
    }

    return itemFormat(item);
};

const itemYear = (item) => {
    if (item?.published_at) {
        const publishedYear = new Date(item.published_at).getFullYear();
        if (!Number.isNaN(publishedYear)) {
            return publishedYear;
        }
    }

    if (item?.publication_year) {
        return item.publication_year;
    }

    if (item?.created_at) {
        const year = new Date(item.created_at).getFullYear();
        if (!Number.isNaN(year)) {
            return year;
        }
    }

    return 'Now';
};

const checkoutUrl = (item) => route('library.pay', { item: item.slug });
const readUrl = (item) => route('library.read', { item: item.slug });
const showUrl = (item) => route('library.show', { item: item.slug });
const freePurchaseUrl = (item) => route('library.purchase', { item: item.slug });

const actionLabel = (item) => {
    if (item?.has_access) {
        return item?.type === 'audiobook' ? 'Continue Listening' : 'Continue Reading';
    }

    if (requiresPayment(item)) {
        return isAuthenticated.value ? 'Add to cart' : 'Buy Now';
    }

    return 'Read Now';
};

const actionHref = (item) => {
    if (item?.has_access) {
        return readUrl(item);
    }

    if (requiresPayment(item)) {
        return checkoutUrl(item);
    }

    return showUrl(item);
};

</script>

<template>
    <Head
        :title="
            props.forcedType === 'audiobook'
                ? 'Audiobooks'
                : props.forcedType === 'ebook'
                  ? 'eBooks'
                  : 'Library'
        "
    />

    <AppLayout>
        <div class="min-h-full space-y-6 text-neutral-950 lg:space-y-8">
            <EbookShareCampaignBanner v-if="isAuthenticated" />
            <section class="rounded-2xl border border-neutral-200 bg-white px-4 py-5 shadow-sm sm:px-6">
                <div class="flex flex-col gap-4 border-b border-neutral-100 pb-5 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-neutral-400">Filters</p>
                        <h1 class="mt-1 text-xl font-semibold text-neutral-950">
                            {{ props.forcedType === 'audiobook' ? 'Audiobook Library' : props.forcedType === 'ebook' ? 'eBook Library' : 'Library' }}
                        </h1>
                        <p class="mt-1 text-sm text-neutral-600">
                            Search and narrow titles by category, author, country, format, and access.
                        </p>
                    </div>

                </div>

                <form class="mt-5" @submit.prevent="applyFilters">
                    <div class="flex flex-col gap-3 sm:flex-row">
                        <div class="relative flex-1">
                            <MagnifyingGlassIcon
                                class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400"
                                aria-hidden="true"
                            />
                            <input
                                id="library-search"
                                v-model="search"
                                type="search"
                                placeholder="Search by title, author, or keyword..."
                                class="h-11 w-full rounded-xl border border-neutral-200 bg-neutral-50 pl-10 pr-3 text-sm text-neutral-900 shadow-sm outline-none transition placeholder:text-neutral-400 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20"
                            />
                        </div>

                        <button
                            type="button"
                            class="inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-neutral-200 bg-white px-4 text-sm font-semibold text-neutral-700 shadow-sm transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700"
                            @click="showFilterModal = true"
                        >
                            <AdjustmentsHorizontalIcon class="h-4 w-4" />
                            Filters
                            <span
                                v-if="activeModalFilterCount > 0"
                                class="inline-flex min-w-5 items-center justify-center rounded-full bg-blue-600 px-1.5 py-0.5 text-[11px] font-bold text-white"
                            >
                                {{ activeModalFilterCount }}
                            </span>
                        </button>
                    </div>

                    <p v-if="activeModalFilterCount > 0" class="mt-3 text-xs font-medium text-neutral-500">
                        {{ activeModalFilterCount }} filter{{ activeModalFilterCount === 1 ? '' : 's' }} selected.
                    </p>
                </form>
            </section>

            <div
                v-if="showFilterModal"
                class="fixed inset-0 z-50 overflow-y-auto"
                role="dialog"
                aria-modal="true"
            >
                <div class="min-h-full px-4 py-6 sm:px-6">
                    <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" @click="showFilterModal = false"></div>

                    <div class="relative mx-auto max-w-3xl">
                        <div class="overflow-hidden rounded-3xl border border-neutral-200 bg-white shadow-2xl">
                            <div class="flex items-start justify-between border-b border-neutral-100 px-5 py-4 sm:px-6">
                                <div>
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-neutral-400">Library Filters</p>
                                    <h2 class="mt-1 text-xl font-semibold text-neutral-950">Refine your results</h2>
                                    <p class="mt-1 text-sm text-neutral-600">
                                        Choose filters, then apply them to update the library results.
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    class="inline-flex h-10 w-10 items-center justify-center rounded-xl text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-800"
                                    aria-label="Close filters"
                                    @click="showFilterModal = false"
                                >
                                    <XMarkIcon class="h-5 w-5" />
                                </button>
                            </div>

                            <form class="space-y-5 px-5 py-5 sm:px-6" @submit.prevent="submitFilters">
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="space-y-1.5">
                                        <label for="category-filter" class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Category</label>
                                        <select
                                            id="category-filter"
                                            v-model="selectedCategory"
                                            class="h-11 w-full rounded-xl border border-neutral-200 bg-neutral-50 px-3 text-sm text-neutral-700 shadow-sm outline-none focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20"
                                        >
                                            <option value="">All Categories</option>
                                            <option v-for="category in categories" :key="category.slug" :value="category.slug">
                                                {{ category.name }}
                                            </option>
                                        </select>
                                    </div>

                                    <div class="space-y-1.5">
                                        <label for="region-filter" class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Country</label>
                                        <select
                                            id="region-filter"
                                            v-model="selectedRegion"
                                            class="h-11 w-full rounded-xl border border-neutral-200 bg-neutral-50 px-3 text-sm text-neutral-700 shadow-sm outline-none focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20"
                                        >
                                            <option value="">All Countries</option>
                                            <option v-for="region in regions" :key="region.value" :value="region.value">
                                                {{ region.label }}
                                            </option>
                                        </select>
                                    </div>

                                    <div class="space-y-1.5">
                                        <label for="author-filter" class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Author</label>
                                        <select
                                            id="author-filter"
                                            v-model="selectedAuthor"
                                            class="h-11 w-full rounded-xl border border-neutral-200 bg-neutral-50 px-3 text-sm text-neutral-700 shadow-sm outline-none focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20"
                                        >
                                            <option value="">All Authors</option>
                                            <option v-for="author in authors" :key="author.slug" :value="author.slug">
                                                {{ author.name }}
                                            </option>
                                        </select>
                                    </div>

                                    <div class="space-y-1.5">
                                        <label :for="props.forcedType ? 'sort-filter' : 'type-filter'" class="text-xs font-semibold uppercase tracking-wide text-neutral-500">
                                            {{ props.forcedType ? 'Sort' : 'Format' }}
                                        </label>
                                        <template v-if="!props.forcedType">
                                            <select
                                                id="type-filter"
                                                v-model="selectedType"
                                                class="h-11 w-full rounded-xl border border-neutral-200 bg-neutral-50 px-3 text-sm text-neutral-700 shadow-sm outline-none focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20"
                                            >
                                                <option value="">All Formats</option>
                                                <option v-for="typeOption in types" :key="typeOption.value" :value="typeOption.value">
                                                    {{ typeOption.label }}
                                                </option>
                                            </select>
                                        </template>
                                        <template v-else>
                                            <select
                                                id="sort-filter"
                                                v-model="selectedSort"
                                                class="h-11 w-full rounded-xl border border-neutral-200 bg-neutral-50 px-3 text-sm text-neutral-700 shadow-sm outline-none focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20"
                                            >
                                                <option value="newest">Newest First</option>
                                                <option value="featured">Featured</option>
                                                <option value="popular">Popular</option>
                                                <option value="best_sellers">Best Sellers</option>
                                                <option value="title">Title</option>
                                                <option value="price_low">Price: Low to High</option>
                                                <option value="price_high">Price: High to Low</option>
                                            </select>
                                        </template>
                                    </div>

                                    <div class="space-y-1.5">
                                        <label for="access-filter" class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Access</label>
                                        <select
                                            id="access-filter"
                                            v-model="selectedAccess"
                                            class="h-11 w-full rounded-xl border border-neutral-200 bg-neutral-50 px-3 text-sm text-neutral-700 shadow-sm outline-none focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20"
                                        >
                                            <option value="">All Items</option>
                                            <option value="free">Free</option>
                                            <option value="paid">Paid</option>
                                        </select>
                                    </div>

                                    <div v-if="!props.forcedType" class="space-y-1.5">
                                        <label for="sort-all-filter" class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Sort</label>
                                        <select
                                            id="sort-all-filter"
                                            v-model="selectedSort"
                                            class="h-11 w-full rounded-xl border border-neutral-200 bg-neutral-50 px-3 text-sm text-neutral-700 shadow-sm outline-none focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20"
                                        >
                                            <option value="newest">Newest First</option>
                                            <option value="featured">Featured</option>
                                            <option value="popular">Popular</option>
                                            <option value="best_sellers">Best Sellers</option>
                                            <option value="title">Title</option>
                                            <option value="price_low">Price: Low to High</option>
                                            <option value="price_high">Price: High to Low</option>
                                        </select>
                                    </div>
                                </div>

                                <div v-if="isAuthenticated" class="rounded-2xl border border-neutral-100 bg-neutral-50/80 p-4">
                                    <button
                                        type="button"
                                        :class="[
                                            'inline-flex h-11 items-center gap-2 rounded-xl border px-4 text-sm font-semibold shadow-sm transition',
                                            favoritesOnly
                                                ? 'border-blue-200 bg-blue-50 text-blue-700'
                                                : 'border-neutral-200 bg-white text-neutral-700 hover:bg-neutral-50',
                                        ]"
                                        @click="favoritesOnly = !favoritesOnly"
                                    >
                                        <BookmarkIcon class="h-4 w-4" />
                                        Favorites only
                                    </button>
                                </div>

                                <div class="flex flex-col gap-3 border-t border-neutral-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                                    <button
                                        type="button"
                                        class="inline-flex h-11 items-center justify-center rounded-xl border border-neutral-200 bg-white px-4 text-sm font-semibold text-neutral-700 shadow-sm transition hover:bg-neutral-50"
                                        @click="resetModalFilters"
                                    >
                                        Clear filters
                                    </button>

                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                                        <p v-if="activeModalFilterCount > 0" class="text-xs font-medium text-neutral-500">
                                            {{ activeModalFilterCount }} filter{{ activeModalFilterCount === 1 ? '' : 's' }} selected
                                        </p>

                                        <button
                                            type="submit"
                                            class="inline-flex h-11 items-center justify-center rounded-xl bg-blue-600 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                                        >
                                            Apply Filters
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <section class="rounded-2xl border border-slate-200/80 bg-white px-4 py-8 shadow-sm sm:px-6 sm:py-10">
                <div
                    v-if="page.props.flash?.success || page.props.flash?.info || page.props.flash?.error"
                    class="mb-6 rounded-lg border px-4 py-3 text-sm"
                    :class="page.props.flash?.error ? 'border-red-200 bg-red-50 text-red-700' : 'border-blue-200 bg-blue-50 text-blue-800'"
                >
                    {{ page.props.flash?.error || page.props.flash?.success || page.props.flash?.info }}
                </div>

                <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm font-medium text-neutral-700">
                        {{ resultLabel }}
                    </p>

                    <div class="flex flex-wrap items-center justify-end gap-2 sm:gap-3">
                        <Link
                            v-if="isAuthenticated"
                            :href="route('library.cart')"
                            class="relative inline-flex items-center gap-2 rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm font-semibold text-neutral-800 shadow-sm transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-800"
                        >
                            <ShoppingCartIcon class="h-4 w-4" />
                            Cart
                            <span
                                v-if="cartCount > 0"
                                class="absolute -right-1.5 -top-1.5 flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-blue-600 px-1 text-[10px] font-bold text-white"
                            >
                                {{ cartCount > 99 ? '99+' : cartCount }}
                            </span>
                        </Link>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                :class="[
                                    'inline-flex h-9 w-9 items-center justify-center rounded-lg border transition',
                                    viewMode === 'grid' ? 'border-blue-600 bg-blue-600 text-white' : 'border-neutral-200 text-neutral-500 hover:bg-neutral-50',
                                ]"
                                aria-label="Grid view"
                                @click="viewMode = 'grid'"
                            >
                                <Squares2X2Icon class="h-4 w-4" />
                            </button>
                            <button
                                type="button"
                                :class="[
                                    'inline-flex h-9 w-9 items-center justify-center rounded-lg border transition',
                                    viewMode === 'list' ? 'border-blue-600 bg-blue-600 text-white' : 'border-neutral-200 text-neutral-500 hover:bg-neutral-50',
                                ]"
                                aria-label="List view"
                                @click="viewMode = 'list'"
                            >
                                <ListBulletIcon class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    v-if="visibleItems.length"
                    :class="[
                        viewMode === 'grid'
                            ? 'grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-5'
                            : 'grid gap-4',
                    ]"
                >
                    <article
                        v-for="item in visibleItems"
                        :key="item.uuid"
                        :class="[
                            'group overflow-hidden border border-neutral-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md',
                            viewMode === 'grid' ? 'flex flex-col rounded-xl' : 'rounded-lg',
                            viewMode === 'list' ? 'grid gap-4 p-4 sm:grid-cols-[10rem_1fr]' : '',
                        ]"
                    >
                        <template v-if="viewMode === 'grid'">
                            <Link
                                :href="item.has_access ? readUrl(item) : showUrl(item)"
                                class="relative block aspect-square overflow-hidden bg-neutral-100"
                            >
                                <img
                                    v-if="item.cover_image_url"
                                    :src="item.cover_image_url"
                                    :alt="item.title"
                                    class="h-full w-full object-cover object-top transition duration-300 group-hover:scale-105"
                                />
                                <div
                                    v-else
                                    class="absolute inset-0 flex h-full w-full items-center justify-center bg-gradient-to-br from-indigo-500 to-indigo-700"
                                >
                                    <BookOpenIcon v-if="item.type === 'ebook'" class="h-14 w-14 text-white/90" />
                                    <MusicalNoteIcon v-else-if="item.type === 'audiobook'" class="h-14 w-14 text-white/90" />
                                    <DocumentTextIcon v-else class="h-14 w-14 text-white/90" />
                                </div>
                                <span class="absolute left-2 top-2 rounded bg-blue-600 px-2 py-0.5 text-[11px] font-semibold text-white">
                                    {{ itemCategory(item) }}
                                </span>
                            </Link>

                            <div class="flex flex-1 flex-col p-3">
                                <Link :href="item.has_access ? readUrl(item) : showUrl(item)" class="block">
                                    <h2 class="line-clamp-2 text-sm font-semibold leading-5 text-neutral-950 transition group-hover:text-blue-700">
                                        {{ item.title }}
                                    </h2>
                                </Link>
                                <p class="mt-1 text-xs text-neutral-500">
                                    {{ itemCreator(item) }}
                                </p>
                                <div class="mt-1.5 flex items-center gap-3 text-[11px] text-neutral-400">
                                    <span>{{ itemFormat(item) }}</span>
                                    <span>{{ itemYear(item) }}</span>
                                </div>

                                <div class="mt-auto flex items-center justify-between gap-2 border-t border-neutral-100 pt-3">
                                    <span class="text-sm font-bold" :class="requiresPayment(item) ? 'text-neutral-900' : 'text-emerald-600'">
                                        {{ formatPrice(item) }}
                                    </span>

                                    <Link
                                        v-if="isAuthenticated && !item.has_access && !requiresPayment(item)"
                                        :href="freePurchaseUrl(item)"
                                        method="post"
                                        as="button"
                                        type="button"
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-blue-700"
                                    >
                                        <BookOpenIcon class="h-3.5 w-3.5" />
                                        {{ actionLabel(item) }}
                                    </Link>
                                    <Link
                                        v-else-if="isAuthenticated && !item.has_access && requiresPayment(item)"
                                        :href="route('library.cart.add', { item: item.slug })"
                                        method="post"
                                        as="button"
                                        type="button"
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-blue-700"
                                    >
                                        <ShoppingCartIcon class="h-3.5 w-3.5" />
                                        Add to cart
                                    </Link>
                                    <a
                                        v-else
                                        :href="actionHref(item)"
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-blue-700"
                                    >
                                        <ShoppingCartIcon v-if="requiresPayment(item) && !item.has_access" class="h-3.5 w-3.5" />
                                        <MusicalNoteIcon v-else-if="item.type === 'audiobook'" class="h-3.5 w-3.5" />
                                        <BookOpenIcon v-else class="h-3.5 w-3.5" />
                                        {{ item.has_access ? (item.type === 'audiobook' ? 'Listen' : 'Read') : actionLabel(item) }}
                                    </a>
                                </div>
                            </div>
                        </template>

                        <template v-else>
                            <Link
                                :href="item.has_access ? readUrl(item) : showUrl(item)"
                                class="relative block h-40 overflow-hidden rounded-lg bg-neutral-100 sm:h-full sm:min-h-[10rem]"
                            >
                                <img
                                    v-if="item.cover_image_url"
                                    :src="item.cover_image_url"
                                    :alt="item.title"
                                    class="h-full w-full object-contain transition duration-300 group-hover:scale-105"
                                />
                                <div v-else class="flex h-full w-full items-center justify-center bg-neutral-100">
                                    <DocumentTextIcon class="h-16 w-16 text-neutral-300" />
                                </div>

                                <span class="absolute left-2 top-2 rounded bg-blue-600 px-2 py-1 text-xs font-semibold text-white">
                                    {{ itemCategory(item) }}
                                </span>

                                <span
                                    v-if="requiresPayment(item)"
                                    class="absolute bottom-2 left-2 rounded bg-white px-2 py-1 text-xs font-semibold text-neutral-800 shadow-sm"
                                >
                                    {{ formatPrice(item) }}
                                </span>
                            </Link>

                            <div class="flex min-w-0 flex-col">
                                <div class="mb-2 flex items-center justify-between gap-3 text-xs font-medium text-neutral-500">
                                    <span>{{ itemFormat(item) }}</span>
                                    <button
                                        type="button"
                                        class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-neutral-200 text-neutral-400 transition hover:border-blue-200 hover:text-blue-700"
                                        aria-label="Save for later"
                                    >
                                        <BookmarkIcon class="h-4 w-4" />
                                    </button>
                                </div>

                                <Link :href="item.has_access ? readUrl(item) : showUrl(item)" class="block">
                                    <h2 class="line-clamp-2 text-base font-semibold leading-6 text-neutral-950 transition group-hover:text-blue-700">
                                        {{ item.title }}
                                    </h2>
                                </Link>

                                <p class="mt-1 text-sm text-neutral-500">
                                    by {{ itemCreator(item) }}
                                </p>

                                <p class="mt-3 line-clamp-2 text-sm leading-6 text-neutral-600">
                                    {{ itemDescription(item) }}
                                </p>

                                <div class="mt-5 flex items-center gap-5 text-xs text-neutral-500">
                                    <span class="inline-flex items-center gap-1.5">
                                        <DocumentTextIcon class="h-4 w-4 text-neutral-400" />
                                        {{ itemMeta(item) }}
                                    </span>
                                    <span class="inline-flex items-center gap-1.5">
                                        <CalendarDaysIcon class="h-4 w-4 text-neutral-400" />
                                        {{ itemYear(item) }}
                                    </span>
                                </div>

                                <div class="mt-5 flex items-center gap-3">
                                    <Link
                                        v-if="isAuthenticated && !item.has_access && !requiresPayment(item)"
                                        :href="freePurchaseUrl(item)"
                                        method="post"
                                        as="button"
                                        type="button"
                                        class="inline-flex min-h-10 flex-1 items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700"
                                    >
                                        <BookOpenIcon class="h-4 w-4" />
                                        {{ actionLabel(item) }}
                                    </Link>
                                    <Link
                                        v-else-if="isAuthenticated && !item.has_access && requiresPayment(item)"
                                        :href="route('library.cart.add', { item: item.slug })"
                                        method="post"
                                        as="button"
                                        type="button"
                                        class="inline-flex min-h-10 flex-1 items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700"
                                    >
                                        <ShoppingCartIcon class="h-4 w-4" />
                                        {{ actionLabel(item) }}
                                    </Link>
                                    <a
                                        v-else
                                        :href="actionHref(item)"
                                        class="inline-flex min-h-10 flex-1 items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700"
                                    >
                                        <ShoppingCartIcon v-if="requiresPayment(item) && !item.has_access" class="h-4 w-4" />
                                        <MusicalNoteIcon v-else-if="item.type === 'audiobook'" class="h-4 w-4" />
                                        <BookOpenIcon v-else class="h-4 w-4" />
                                        {{ actionLabel(item) }}
                                    </a>

                                    <Link
                                        :href="showUrl(item)"
                                        class="inline-flex min-h-10 items-center justify-center rounded-lg border border-neutral-200 px-3 text-sm font-semibold text-neutral-700 transition hover:bg-neutral-50"
                                    >
                                        Details
                                    </Link>
                                </div>
                            </div>
                        </template>
                    </article>
                </div>

                <div v-else class="rounded-lg border border-neutral-200 bg-white px-6 py-14 text-center shadow-sm">
                    <BookOpenIcon class="mx-auto h-12 w-12 text-neutral-300" />
                    <h2 class="mt-4 text-lg font-semibold text-neutral-950">No books found</h2>
                    <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-neutral-600">
                        {{
                            hasActiveFilters
                                ? 'Try another search or clear the filters.'
                                : 'New titles are coming soon.'
                        }}
                    </p>
                    <button
                        v-if="hasActiveFilters"
                        type="button"
                        class="mt-5 rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700"
                        @click="resetFilters"
                    >
                        Clear Filters
                    </button>
                </div>

                <div v-if="visibleItems.length && (libraryHasMore || libraryLoadingMore)" class="mt-10 flex flex-col items-center gap-3">
                    <div
                        ref="libraryLoadMoreSentinel"
                        class="h-1 w-full"
                        aria-hidden="true"
                    />
                    <p v-if="libraryLoadingMore" class="text-sm text-neutral-500">
                        Loading more titles…
                    </p>
                    <p v-else class="text-sm text-neutral-500">
                        Showing {{ visibleItems.length }} of {{ resultTotal }} titles
                    </p>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
