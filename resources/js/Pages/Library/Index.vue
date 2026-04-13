<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    BookOpenIcon, 
    MusicalNoteIcon,
    MagnifyingGlassIcon,
    HeartIcon,
    ArrowDownTrayIcon,
    PlayIcon,
    ClockIcon,
    DocumentTextIcon,
} from '@heroicons/vue/24/outline';
import { HeartIcon as HeartSolid, StarIcon as StarSolid } from '@heroicons/vue/24/solid';
import { ref, watch, computed } from 'vue';

const page = usePage();
const isAuthenticated = computed(() => !!page.props.auth?.user);
import { debounce } from 'lodash-es';

const props = defineProps({
    items: Object,
    categories: Array,
    types: {
        type: Array,
        default: () => [],
    },
    groupedItems: {
        type: Array,
        default: () => [],
    },
    filters: Object,
});

const search = ref(props.filters?.search || '');
const selectedCategory = ref(props.filters?.category || '');
const selectedType = ref(props.filters?.type || '');
const showFavorites = ref(props.filters?.favorites === 'true');

const applyFilters = debounce(() => {
    router.get(route('library.index'), {
        search: search.value || undefined,
        category: selectedCategory.value || undefined,
        type: selectedType.value || undefined,
        favorites: showFavorites.value ? 'true' : undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
}, 300);

watch([search, selectedCategory, selectedType, showFavorites], applyFilters);

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
</script>

<template>
    <Head title="Digital Library" />

    <AppLayout>
        <div
            class="mx-auto max-w-7xl space-y-5"
            :class="!isAuthenticated ? 'px-4 pb-10 pt-2 sm:px-6 lg:px-8' : ''"
        >
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm sm:px-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-400">
                            Library
                        </p>
                        <h1 class="text-xl font-display font-bold text-slate-900">
                            Digital Library
                        </h1>
                        <p class="mt-0.5 max-w-2xl text-sm text-slate-500">
                            E-books and audiobooks to support your immigration journey.
                        </p>
                    </div>
                    <span class="hidden rounded-full bg-sky-50 px-2.5 py-1 text-xs font-medium text-sky-700 sm:inline-flex">
                        Browse &amp; download
                    </span>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <template v-for="typeOption in types.slice(0, 4)" :key="typeOption.value">
                    <Link
                        :href="route('library.index', { type: typeOption.value })"
                        class="rounded-xl border border-slate-200 bg-white p-4 text-center shadow-sm transition-shadow hover:shadow-md"
                    >
                        <component
                            :is="getTypeIcon(typeOption.value)"
                            class="mx-auto mb-2 h-7 w-7 text-sky-600"
                        />
                        <p class="text-2xl font-display font-bold text-slate-900">
                            {{ typeOption.count ?? 0 }}
                        </p>
                        <p class="text-xs text-slate-500">{{ typeOption.label }}</p>
                    </Link>
                </template>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
                <div class="flex flex-col gap-3 lg:flex-row">
                    <div class="relative min-w-0 flex-1">
                        <MagnifyingGlassIcon
                            class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                        />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search by title, author..."
                            class="w-full rounded-lg border border-slate-200 bg-white py-2.5 pl-10 pr-3 text-sm text-slate-900 placeholder-slate-400 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20"
                        />
                    </div>

                    <select
                        v-model="selectedCategory"
                        class="rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20"
                    >
                        <option value="">All Categories</option>
                        <option v-for="category in categories" :key="category.slug" :value="category.slug">
                            {{ category.name }}
                        </option>
                    </select>

                    <select
                        v-model="selectedType"
                        class="rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20"
                    >
                        <option value="">All Types</option>
                        <option v-for="typeOption in types" :key="typeOption.value" :value="typeOption.value">
                            {{ typeOption.label }}
                        </option>
                    </select>

                    <button
                        type="button"
                        @click="showFavorites = !showFavorites"
                        :class="[
                            'inline-flex items-center justify-center gap-2 rounded-lg border px-3 py-2.5 text-sm font-medium transition-colors',
                            showFavorites
                                ? 'border-rose-200 bg-rose-50 text-rose-800'
                                : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50',
                        ]"
                    >
                        <component :is="showFavorites ? HeartSolid : HeartIcon" class="h-5 w-5" />
                        Favorites
                    </button>
                </div>
            </div>

            <div v-if="groupedItems.length" class="space-y-8">
                <section v-for="group in groupedItems" :key="group.slug" class="space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                        <h2 class="text-lg font-display font-semibold text-slate-900">
                            {{ group.name }}
                        </h2>
                        <span class="text-sm text-slate-500">{{ group.items.length }} item(s)</span>
                    </div>

                    <div
                        class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6"
                    >
                        <div
                            v-for="item in group.items"
                            :key="item.uuid"
                            class="group overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition-all hover:shadow-md"
                        >
                                <!-- Cover Image (capped height — wide cards stay short) -->
                                <div class="relative h-24 sm:h-28 md:h-32 w-full bg-slate-100 overflow-hidden">
                                    <img
                                        v-if="item.cover_image_url"
                                        :src="item.cover_image_url"
                                        :alt="item.title"
                                        class="absolute inset-0 w-full h-full object-cover object-top group-hover:scale-105 transition-transform duration-300"
                                    />
                                    <div v-else class="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-sky-100 to-indigo-100">
                                        <component :is="getTypeIcon(item.type)" class="h-8 w-8 text-sky-500 sm:h-9 sm:w-9" />
                                    </div>

                                    <!-- Type Badge -->
                                    <div class="absolute left-1.5 top-1.5 sm:left-2 sm:top-2">
                                        <span :class="[
                                            'inline-flex items-center gap-0.5 rounded-md px-1.5 py-0.5 text-[10px] font-medium sm:px-2 sm:py-1 sm:text-xs',
                                            item.type === 'ebook'
                                                ? 'bg-sky-100 text-sky-800'
                                                : 'bg-violet-100 text-violet-800'
                                        ]">
                                            <component :is="getTypeIcon(item.type)" class="w-3.5 h-3.5" />
                                            {{ getTypeLabel(item.type) }}
                                        </span>
                                    </div>

                                    <!-- One-time purchase badge -->
                                    <div v-if="item.is_premium" class="absolute top-1.5 right-1.5 sm:top-2 sm:right-2">
                                        <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 sm:px-2 sm:py-1 bg-amber-100 text-amber-700 rounded-md text-[10px] sm:text-xs font-medium">
                                            <StarSolid class="w-3.5 h-3.5" />
                                            One-time
                                        </span>
                                    </div>

                                    <!-- Favorite Button -->
                                    <button
                                        @click.prevent="toggleFavorite(item)"
                                        class="absolute bottom-1.5 right-1.5 sm:bottom-2 sm:right-2 w-8 h-8 sm:w-9 sm:h-9 bg-white/90 backdrop-blur rounded-full flex items-center justify-center shadow-md opacity-0 group-hover:opacity-100 transition-all hover:bg-white"
                                    >
                                        <component
                                            :is="item.is_favorite ? HeartSolid : HeartIcon"
                                            :class="['h-4 w-4', item.is_favorite ? 'text-rose-500' : 'text-slate-600']"
                                        />
                                    </button>

                                    <!-- Play Button for Audiobooks -->
                                    <Link
                                        v-if="item.type === 'audiobook'"
                                        :href="route('library.show', item.slug)"
                                        class="absolute inset-0 flex items-center justify-center bg-black/30 opacity-0 group-hover:opacity-100 transition-opacity"
                                    >
                                        <div class="w-11 h-11 sm:w-12 sm:h-12 bg-white rounded-full flex items-center justify-center shadow-lg">
                                            <PlayIcon class="ml-0.5 h-5 w-5 text-sky-600 sm:h-6 sm:w-6" />
                                        </div>
                                    </Link>
                                </div>

                                <!-- Content -->
                                <div class="p-2.5 sm:p-3">
                                    <Link :href="route('library.show', item.slug)">
                                        <h3 class="mb-0.5 line-clamp-2 text-sm font-semibold leading-snug text-slate-900 transition-colors group-hover:text-sky-700">
                                            {{ item.title }}
                                        </h3>
                                    </Link>
                                    <p class="text-xs text-slate-500 mb-1.5 line-clamp-1">{{ item.author }}</p>

                                    <div class="flex items-center justify-between text-xs sm:text-sm gap-1">
                                        <div class="flex items-center gap-2 text-slate-400 min-w-0">
                                            <span v-if="item.duration_formatted" class="flex items-center gap-0.5 truncate">
                                                <ClockIcon class="w-3.5 h-3.5 shrink-0" />
                                                {{ item.duration_formatted }}
                                            </span>
                                            <span v-else-if="item.file_size_formatted">
                                                {{ item.file_size_formatted }}
                                            </span>
                                        </div>

                                        <a
                                            v-if="item.has_access"
                                            :href="route('library.download', item.slug)"
                                            class="flex shrink-0 items-center gap-0.5 font-medium text-sky-700 hover:text-sky-800"
                                        >
                                            <ArrowDownTrayIcon class="w-3.5 h-3.5" />
                                            <span class="hidden min-[380px]:inline">Download</span>
                                        </a>
                                        <Link
                                            v-else
                                            :href="route('library.show', item.slug)"
                                            class="flex shrink-0 items-center gap-0.5 font-medium text-sky-700 hover:text-sky-800"
                                        >
                                            <ArrowDownTrayIcon class="w-3.5 h-3.5" />
                                            {{ item.is_premium ? 'Purchase' : 'Get access' }}
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

            <div v-else class="rounded-xl border border-slate-200 bg-white p-12 text-center shadow-sm">
                <BookOpenIcon class="mx-auto mb-4 h-16 w-16 text-slate-300" />
                <h3 class="mb-2 text-lg font-semibold text-slate-900">No items found</h3>
                <p class="mb-6 text-slate-500">
                    {{
                        search || selectedCategory || selectedType || showFavorites
                            ? 'Try adjusting your filters'
                            : 'Check back soon for new content!'
                    }}
                </p>
                <button
                    v-if="search || selectedCategory || selectedType || showFavorites"
                    type="button"
                    @click="
                        search = '';
                        selectedCategory = '';
                        selectedType = '';
                        showFavorites = false;
                    "
                    class="rounded-lg bg-sky-600 px-6 py-3 font-semibold text-white transition-colors hover:bg-sky-700"
                >
                    Clear Filters
                </button>
            </div>

            <div v-if="items.links && items.last_page > 1" class="flex justify-center pt-2">
                <nav class="flex flex-wrap items-center justify-center gap-1">
                    <Link
                        v-for="link in items.links"
                        :key="link.label"
                        :href="link.url"
                        :class="[
                            'min-w-[2.25rem] rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                            link.active
                                ? 'bg-sky-600 text-white shadow-sm'
                                : link.url
                                  ? 'text-slate-600 hover:bg-slate-100'
                                  : 'cursor-not-allowed text-slate-300',
                        ]"
                        v-html="link.label"
                    />
                </nav>
            </div>
        </div>
    </AppLayout>
</template>
