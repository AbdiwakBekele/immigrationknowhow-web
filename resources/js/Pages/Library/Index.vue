<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    BookOpenIcon,
    MusicalNoteIcon,
    MagnifyingGlassIcon,
    HeartIcon,
    DocumentTextIcon,
    Squares2X2Icon,
    ListBulletIcon,
    ClockIcon,
} from '@heroicons/vue/24/outline';
import { HeartIcon as HeartSolid } from '@heroicons/vue/24/solid';
import { ref, computed, watch } from 'vue';

const page = usePage();
const isAuthenticated = computed(() => !!page.props.auth?.user);

const props = defineProps({
    items: Object,
    categories: Array,
    types: {
        type: Array,
        default: () => [],
    },
    authors: {
        type: Array,
        default: () => [],
    },
    regionOptions: {
        type: Array,
        default: () => [],
    },
    filters: Object,
});

/** Empty string from the query string does not trigger `??`, so `<select>` shows blank with no matching option. */
const SORT_VALUES = ['newest', 'oldest', 'title_asc', 'title_desc'];

function normalizeSort(value) {
    const s = typeof value === 'string' ? value.trim() : '';
    return SORT_VALUES.includes(s) ? s : 'newest';
}

const viewMode = ref('grid');

const LOCALE_CODES = ['en', 'fr', 'es'];

function normalizeLocale(value) {
    const v = typeof value === 'string' ? value.trim() : '';
    return LOCALE_CODES.includes(v) ? v : 'en';
}

const locale = ref(normalizeLocale(page.props.locale));

watch(
    () => page.props.locale,
    (l) => {
        locale.value = normalizeLocale(l);
    },
);

const updateLocale = () => {
    router.post(
        route('locale.update'),
        { locale: locale.value },
        {
            preserveScroll: true,
            onSuccess: () => router.reload(),
        },
    );
};

const search = ref(props.filters?.search ?? '');
const selectedCategory = ref(props.filters?.category ?? '');
const selectedRegion = ref(props.filters?.region ?? '');
const selectedAuthor = ref(props.filters?.author ?? '');
const selectedSort = ref(normalizeSort(props.filters?.sort));
const selectedType = ref(props.filters?.type ?? '');

watch(
    () => props.filters,
    (f) => {
        search.value = f?.search ?? '';
        selectedCategory.value = f?.category ?? '';
        selectedRegion.value = f?.region ?? '';
        selectedAuthor.value = f?.author ?? '';
        selectedSort.value = normalizeSort(f?.sort);
        selectedType.value = f?.type ?? '';
    },
    { deep: true },
);

const buildQuery = () => ({
    search: search.value || undefined,
    category: selectedCategory.value || undefined,
    region: selectedRegion.value || undefined,
    author: selectedAuthor.value || undefined,
    sort: selectedSort.value && selectedSort.value !== 'newest' ? selectedSort.value : undefined,
    type: selectedType.value || undefined,
});

const applyFilters = () => {
    router.get(route('library.index'), buildQuery(), {
        preserveState: true,
        preserveScroll: true,
    });
};

const resetFilters = () => {
    search.value = '';
    selectedCategory.value = '';
    selectedRegion.value = '';
    selectedAuthor.value = '';
    selectedSort.value = 'newest';
    selectedType.value = '';
    router.get(route('library.index'), {}, {
        preserveState: true,
        preserveScroll: true,
    });
};

const libraryItems = computed(() => props.items?.data ?? []);

const totalLabel = computed(() => {
    const n = props.items?.total ?? 0;
    if (selectedType.value === 'audiobook') {
        return `${n} audiobook${n === 1 ? '' : 's'} found`;
    }
    if (selectedType.value === 'ebook') {
        return `${n} eBook${n === 1 ? '' : 's'} found`;
    }
    return `${n} item${n === 1 ? '' : 's'} found`;
});

function stripHtml(html) {
    if (!html) return '';
    return String(html)
        .replace(/<[^>]+>/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

function excerpt(text, len = 140) {
    const t = stripHtml(text);
    if (t.length <= len) return t;
    return `${t.slice(0, len)}…`;
}

const toggleFavorite = (item) => {
    router.post(route('library.favorite', item.slug), {}, {
        preserveScroll: true,
    });
};

const getTypeIcon = (type) => {
    if (type === 'ebook') return BookOpenIcon;
    if (type === 'audiobook') return MusicalNoteIcon;
    return DocumentTextIcon;
};

const getTypeLabel = (type) => {
    const typeOption = props.types?.find((item) => item.value === type);
    return typeOption?.label ?? type;
};

const formatDuration = (seconds) => {
    if (!seconds) return null;
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    if (hours > 0) return `${hours}h ${minutes}m`;
    return `${minutes} min`;
};

const primaryCtaLabel = (item) => {
    if (item.type === 'audiobook') {
        if (item.has_access) return 'Listen';
        return item.is_premium ? 'Purchase' : 'Get access';
    }
    if (item.has_access) {
        if (item.reading_progress_percent > 0) return 'Continue Reading';
        return 'Read Now';
    }
    return item.is_premium ? 'Purchase' : 'Get access';
};

const categoryBadge = (item) => item.category?.name ?? getTypeLabel(item.type);

/** Public URL for card cover (append + raw path fallback for older payloads). */
const libraryItemCoverSrc = (item) => {
    if (item?.cover_image_url) {
        return item.cover_image_url;
    }
    const path = item?.cover_image;
    if (typeof path === 'string' && path.trim() !== '') {
        return `/storage/${path.replace(/^\/+/, '')}`;
    }
    return null;
};

const heroEbookCount = computed(() => props.types?.find((t) => t.value === 'ebook')?.count ?? 0);
const heroAudiobookCount = computed(() => props.types?.find((t) => t.value === 'audiobook')?.count ?? 0);
const heroTotalTitles = computed(() => Number(heroEbookCount.value) + Number(heroAudiobookCount.value));
const heroCategoryCount = computed(() => props.categories?.length ?? 0);
</script>

<template>
    <Head title="Explore Our eBook & Audio Collections" />

    <AppLayout>
        <div
            class="space-y-5 pb-16"
            :class="
                isAuthenticated
                    ? '-mx-4 bg-[#e8edf5] px-4 py-4 sm:-mx-6 sm:px-6 sm:py-5 lg:-mx-8 lg:px-8 lg:py-6'
                    : 'bg-[#e8edf5] px-4 pb-16 pt-2 sm:px-6 lg:px-8'
            "
        >
            <div class="mx-auto max-w-7xl space-y-5">
                <!-- Hero (same gradient / layout as Marketplace + Contracts) -->
                <section
                    class="overflow-hidden rounded-3xl bg-gradient-to-r from-sky-600 via-indigo-600 to-violet-600 px-6 py-8 text-white shadow-xl sm:px-8"
                >
                    <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                        <div class="max-w-2xl">
                            <p class="text-xs font-semibold uppercase tracking-wider text-sky-100">
                                Library
                            </p>
                            <h1 class="mt-2 text-3xl font-display font-bold sm:text-4xl">
                                Explore Our eBook & Audio Collections
                            </h1>
                            <p class="mt-3 text-sm text-sky-50 sm:text-base">
                                E-books, audiobooks, and audio content to support your immigration journey.
                            </p>
                        </div>
                        <div class="grid w-full grid-cols-2 gap-3 sm:grid-cols-4 lg:max-w-3xl">
                            <div class="rounded-xl bg-white/15 px-4 py-3 backdrop-blur-sm">
                                <p class="text-xs text-sky-100">Titles</p>
                                <p class="text-lg font-semibold tabular-nums">{{ heroTotalTitles }}</p>
                            </div>
                            <div class="rounded-xl bg-white/15 px-4 py-3 backdrop-blur-sm">
                                <p class="text-xs text-sky-100">E-books</p>
                                <p class="text-lg font-semibold tabular-nums">{{ heroEbookCount }}</p>
                            </div>
                            <div class="rounded-xl bg-white/15 px-4 py-3 backdrop-blur-sm">
                                <p class="text-xs text-sky-100">Audiobooks</p>
                                <p class="text-lg font-semibold tabular-nums">{{ heroAudiobookCount }}</p>
                            </div>
                            <div class="rounded-xl bg-white/15 px-4 py-3 backdrop-blur-sm">
                                <p class="text-xs text-sky-100">Categories</p>
                                <p class="text-lg font-semibold tabular-nums">{{ heroCategoryCount }}</p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Search & filters (white panel like system cards) -->
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                    <div class="mx-auto max-w-5xl">
                        <!-- Search (icon inside field) -->
                        <div class="relative">
                            <label class="sr-only" for="library-search">Search library</label>
                            <MagnifyingGlassIcon
                                class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400"
                                aria-hidden="true"
                            />
                            <input
                                id="library-search"
                                v-model="search"
                                type="search"
                                placeholder="Search by title, author, or keyword..."
                                class="h-12 w-full rounded-lg border border-slate-200 bg-white pl-11 pr-4 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20"
                                @keydown.enter.prevent="applyFilters"
                            />
                        </div>

                        <!-- One row: filters + Apply -->
                        <div
                            class="mt-5 flex flex-col gap-3 sm:mt-6 sm:flex-row sm:flex-wrap sm:items-end"
                        >
                            <div class="min-w-0 flex-1 sm:min-w-[9.5rem]">
                                <label class="sr-only" for="filter-category">Category</label>
                                <select
                                    id="filter-category"
                                    v-model="selectedCategory"
                                    class="h-11 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20"
                                >
                                    <option value="">All Categories</option>
                                    <option
                                        v-for="category in categories"
                                        :key="category.slug"
                                        :value="category.slug"
                                    >
                                        {{ category.name }}
                                    </option>
                                </select>
                            </div>
                            <div class="min-w-0 flex-1 sm:min-w-[9.5rem]">
                                <label class="sr-only" for="filter-country">Country</label>
                                <select
                                    id="filter-country"
                                    v-model="selectedRegion"
                                    class="h-11 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20"
                                >
                                    <option value="">All Countries</option>
                                    <option
                                        v-for="opt in regionOptions"
                                        :key="opt.value"
                                        :value="opt.value"
                                    >
                                        {{ opt.label }}
                                    </option>
                                </select>
                            </div>
                            <div class="min-w-0 flex-1 sm:min-w-[9.5rem]">
                                <label class="sr-only" for="filter-author">Author</label>
                                <select
                                    id="filter-author"
                                    v-model="selectedAuthor"
                                    class="h-11 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20"
                                >
                                    <option value="">All Authors</option>
                                    <option
                                        v-for="author in authors"
                                        :key="author.slug"
                                        :value="author.slug"
                                    >
                                        {{ author.name }}
                                    </option>
                                </select>
                            </div>
                            <div class="min-w-0 flex-1 sm:min-w-[9.5rem]">
                                <label class="sr-only" for="filter-format">Format</label>
                                <select
                                    id="filter-format"
                                    v-model="selectedType"
                                    class="h-11 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-400"
                                    :disabled="!types.length"
                                >
                                    <option value="">All Formats</option>
                                    <option
                                        v-for="typeOption in types"
                                        :key="typeOption.value"
                                        :value="typeOption.value"
                                    >
                                        {{ typeOption.label }}
                                    </option>
                                </select>
                            </div>
                            <div class="min-w-0 flex-1 sm:min-w-[9.5rem]">
                                <label class="sr-only" for="filter-sort">Sort</label>
                                <select
                                    id="filter-sort"
                                    v-model="selectedSort"
                                    class="h-11 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20"
                                >
                                    <option value="newest">Newest First</option>
                                    <option value="oldest">Oldest First</option>
                                    <option value="title_asc">Title A–Z</option>
                                    <option value="title_desc">Title Z–A</option>
                                </select>
                            </div>
                            <button
                                type="button"
                                class="inline-flex h-11 w-full shrink-0 items-center justify-center rounded-lg bg-primary-600 px-6 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 sm:w-auto sm:min-w-[10.5rem]"
                                @click="applyFilters"
                            >
                                Apply Filters
                            </button>
                        </div>

                        <!-- Reset on its own row -->
                        <div class="mt-4 flex flex-wrap items-center gap-3">
                            <button
                                type="button"
                                class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-200 bg-white px-5 text-sm font-medium text-slate-800 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-300"
                                @click="resetFilters"
                            >
                                Reset
                            </button>
                        </div>

                        <!-- Language below -->
                        <div class="mt-5 flex justify-center">
                            <label class="sr-only" for="library-locale">Language</label>
                            <select
                                id="library-locale"
                                v-model="locale"
                                class="min-w-[11rem] cursor-pointer appearance-none rounded-lg border border-slate-200 bg-white py-2 pl-3 pr-10 text-sm font-medium text-slate-700 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/25"
                                style="background-image: url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 fill=%22none%22 viewBox=%220 0 24 24%22 stroke-width=%221.5%22 stroke=%2364748b%22%3E%3Cpath stroke-linecap=%22round%22 stroke-linejoin=%22round%22 d=%22M19.5 8.25l-7.5 7.5-7.5-7.5%22/%3E%3C/svg%3E'); background-repeat: no-repeat; background-position: right 0.65rem center; background-size: 1rem 1rem"
                                @change="updateLocale"
                            >
                                <option value="en">EN — English</option>
                                <option value="fr">FR — French</option>
                                <option value="es">ES — Spanish</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Results toolbar -->
            <div class="mx-auto max-w-7xl px-0 sm:px-0 lg:px-0">
                <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm font-medium text-slate-600">
                        {{ totalLabel }}
                    </p>
                    <div
                        class="inline-flex rounded-lg border border-slate-200 bg-white p-0.5 shadow-sm"
                        role="group"
                        aria-label="View mode"
                    >
                        <button
                            type="button"
                            :class="[
                                'inline-flex items-center justify-center rounded-md p-2 transition',
                                viewMode === 'grid'
                                    ? 'bg-primary-600 text-white shadow-sm'
                                    : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800',
                            ]"
                            :aria-pressed="viewMode === 'grid'"
                            @click="viewMode = 'grid'"
                        >
                            <Squares2X2Icon class="h-5 w-5" />
                            <span class="sr-only">Grid view</span>
                        </button>
                        <button
                            type="button"
                            :class="[
                                'inline-flex items-center justify-center rounded-md p-2 transition',
                                viewMode === 'list'
                                    ? 'bg-primary-600 text-white shadow-sm'
                                    : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800',
                            ]"
                            :aria-pressed="viewMode === 'list'"
                            @click="viewMode = 'list'"
                        >
                            <ListBulletIcon class="h-5 w-5" />
                            <span class="sr-only">List view</span>
                        </button>
                    </div>
                </div>

                <!-- Grid -->
                <div
                    v-if="libraryItems.length && viewMode === 'grid'"
                    class="grid grid-cols-1 gap-6 md:grid-cols-3"
                >
                    <article
                        v-for="item in libraryItems"
                        :key="item.uuid"
                        class="group flex flex-col overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-soft transition hover:shadow-soft-lg"
                    >
                        <div class="relative aspect-[3/4] w-full overflow-hidden bg-slate-100">
                            <img
                                v-if="libraryItemCoverSrc(item)"
                                :src="libraryItemCoverSrc(item)"
                                :alt="item.title"
                                class="h-full w-full object-cover object-top transition duration-300 group-hover:scale-[1.02]"
                            />
                            <div
                                v-else
                                class="flex h-full w-full items-center justify-center bg-gradient-to-br from-primary-100 to-indigo-100"
                            >
                                <component
                                    :is="getTypeIcon(item.type)"
                                    class="h-14 w-14 text-primary-500"
                                />
                            </div>

                            <div class="absolute left-3 top-3">
                                <span
                                    class="inline-flex rounded-md bg-primary-600 px-2.5 py-1 text-xs font-semibold text-white shadow-sm"
                                >
                                    {{ categoryBadge(item) }}
                                </span>
                            </div>

                            <div
                                v-if="item.has_access && item.reading_progress_percent != null && item.reading_progress_percent > 0"
                                class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/50 to-transparent px-3 pb-2 pt-8"
                            >
                                <div class="flex items-center justify-between text-[11px] font-medium text-white">
                                    <span>{{ item.reading_progress_percent }}% read</span>
                                </div>
                                <div class="mt-1 h-1 overflow-hidden rounded-full bg-white/30">
                                    <div
                                        class="h-full rounded-full bg-primary-400"
                                        :style="{ width: `${Math.min(100, item.reading_progress_percent)}%` }"
                                    />
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-1 flex-col p-4">
                            <Link :href="route('library.show', item.slug)">
                                <h2
                                    class="line-clamp-2 font-display text-lg font-bold text-slate-900 transition group-hover:text-primary-700"
                                >
                                    {{ item.title }}
                                </h2>
                            </Link>
                            <p class="mt-1 text-sm text-slate-500">
                                <span v-if="item.author">by {{ item.author }}</span>
                                <span v-else class="italic text-slate-400">Unknown author</span>
                            </p>
                            <p class="mt-2 line-clamp-3 flex-1 text-sm leading-relaxed text-slate-600">
                                {{ excerpt(item.description) || 'No description available.' }}
                            </p>

                            <div class="mt-3 flex flex-wrap items-center gap-3 text-xs text-slate-500">
                                <span
                                    v-if="item.type === 'ebook' && item.page_count"
                                    class="inline-flex items-center gap-1"
                                >
                                    <DocumentTextIcon class="h-4 w-4 text-slate-400" />
                                    {{ item.page_count }} pages
                                </span>
                                <span
                                    v-else-if="item.type === 'audiobook' && item.duration_seconds"
                                    class="inline-flex items-center gap-1"
                                >
                                    <ClockIcon class="h-4 w-4 text-slate-400" />
                                    {{ formatDuration(item.duration_seconds) }}
                                </span>
                                <span
                                    v-if="item.publication_year"
                                    class="inline-flex items-center gap-1"
                                >
                                    <span class="text-slate-400" aria-hidden="true">·</span>
                                    {{ item.publication_year }}
                                </span>
                            </div>

                            <div class="mt-4 flex gap-2">
                                <Link
                                    :href="route('library.show', item.slug)"
                                    class="inline-flex flex-1 items-center justify-center rounded-lg bg-primary-600 px-4 py-2.5 text-center text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
                                >
                                    {{ primaryCtaLabel(item) }}
                                </Link>
                                <button
                                    type="button"
                                    class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700"
                                    :aria-pressed="item.is_favorite"
                                    @click="toggleFavorite(item)"
                                >
                                    <HeartSolid
                                        v-if="item.is_favorite"
                                        class="h-5 w-5 text-rose-500"
                                    />
                                    <HeartIcon v-else class="h-5 w-5" />
                                    <span class="sr-only">Toggle bookmark</span>
                                </button>
                            </div>
                        </div>
                    </article>
                </div>

                <!-- List -->
                <div v-else-if="libraryItems.length && viewMode === 'list'" class="space-y-4">
                    <article
                        v-for="item in libraryItems"
                        :key="item.uuid"
                        class="flex flex-col gap-4 overflow-hidden rounded-xl border border-slate-200/90 bg-white p-4 shadow-soft transition hover:shadow-soft-lg sm:flex-row"
                    >
                        <div class="relative h-44 w-full shrink-0 overflow-hidden rounded-lg bg-slate-100 sm:h-40 sm:w-36">
                            <img
                                v-if="libraryItemCoverSrc(item)"
                                :src="libraryItemCoverSrc(item)"
                                :alt="item.title"
                                class="h-full w-full object-cover object-top"
                            />
                            <div
                                v-else
                                class="flex h-full w-full items-center justify-center bg-gradient-to-br from-primary-100 to-indigo-100"
                            >
                                <component
                                    :is="getTypeIcon(item.type)"
                                    class="h-12 w-12 text-primary-500"
                                />
                            </div>
                            <div class="absolute left-2 top-2">
                                <span
                                    class="inline-flex rounded-md bg-primary-600 px-2 py-0.5 text-[11px] font-semibold text-white"
                                >
                                    {{ categoryBadge(item) }}
                                </span>
                            </div>
                            <div
                                v-if="item.has_access && item.reading_progress_percent != null && item.reading_progress_percent > 0"
                                class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/45 to-transparent px-2 pb-1.5 pt-6"
                            >
                                <div class="h-1 overflow-hidden rounded-full bg-white/30">
                                    <div
                                        class="h-full rounded-full bg-primary-400"
                                        :style="{ width: `${Math.min(100, item.reading_progress_percent)}%` }"
                                    />
                                </div>
                                <p class="mt-0.5 text-[10px] font-medium text-white">
                                    {{ item.reading_progress_percent }}% read
                                </p>
                            </div>
                        </div>
                        <div class="min-w-0 flex-1">
                            <Link :href="route('library.show', item.slug)">
                                <h2 class="font-display text-lg font-bold text-slate-900 hover:text-primary-700">
                                    {{ item.title }}
                                </h2>
                            </Link>
                            <p class="mt-0.5 text-sm text-slate-500">
                                <span v-if="item.author">by {{ item.author }}</span>
                            </p>
                            <p class="mt-2 line-clamp-2 text-sm text-slate-600">
                                {{ excerpt(item.description, 200) || 'No description available.' }}
                            </p>
                            <div class="mt-3 flex flex-wrap items-center gap-3 text-xs text-slate-500">
                                <span v-if="item.type === 'ebook' && item.page_count" class="inline-flex items-center gap-1">
                                    <DocumentTextIcon class="h-4 w-4" />
                                    {{ item.page_count }} pages
                                </span>
                                <span
                                    v-else-if="item.type === 'audiobook' && item.duration_seconds"
                                    class="inline-flex items-center gap-1"
                                >
                                    <ClockIcon class="h-4 w-4" />
                                    {{ formatDuration(item.duration_seconds) }}
                                </span>
                                <span v-if="item.publication_year">· {{ item.publication_year }}</span>
                            </div>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <Link
                                    :href="route('library.show', item.slug)"
                                    class="inline-flex flex-1 items-center justify-center rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white sm:flex-initial sm:min-w-[10rem]"
                                >
                                    {{ primaryCtaLabel(item) }}
                                </Link>
                                <button
                                    type="button"
                                    class="inline-flex h-11 w-11 items-center justify-center rounded-lg border border-slate-200 bg-white"
                                    @click="toggleFavorite(item)"
                                >
                                    <HeartSolid v-if="item.is_favorite" class="h-5 w-5 text-rose-500" />
                                    <HeartIcon v-else class="h-5 w-5 text-slate-600" />
                                </button>
                            </div>
                        </div>
                    </article>
                </div>

                <div
                    v-else
                    class="rounded-xl border border-slate-200 bg-white px-6 py-16 text-center shadow-soft"
                >
                    <BookOpenIcon class="mx-auto mb-4 h-16 w-16 text-slate-300" />
                    <h3 class="text-lg font-semibold text-slate-900">
                        No items found
                    </h3>
                    <p class="mt-2 text-slate-500">
                        Try adjusting your search or filters.
                    </p>
                    <button
                        type="button"
                        class="mt-6 rounded-lg bg-primary-600 px-6 py-3 text-sm font-semibold text-white hover:bg-primary-700"
                        @click="resetFilters"
                    >
                        Reset filters
                    </button>
                </div>

                <div v-if="items.links && items.last_page > 1" class="mt-10 flex justify-center">
                    <nav class="flex flex-wrap items-center justify-center gap-1">
                        <Link
                            v-for="link in items.links"
                            :key="link.label"
                            :href="link.url"
                            :class="[
                                'min-w-[2.25rem] rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                                link.active
                                    ? 'bg-primary-600 text-white shadow-sm'
                                    : link.url
                                      ? 'text-slate-600 hover:bg-slate-100'
                                      : 'cursor-not-allowed text-slate-300',
                            ]"
                            v-html="link.label"
                        />
                    </nav>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
