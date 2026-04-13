<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
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
import { ref, watch } from 'vue';
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
        <div class="min-h-screen bg-slate-50">
            <!-- Hero Section -->
            <div class="bg-gradient-to-br from-primary-900 via-primary-800 to-primary-700 relative overflow-hidden">
                <div
                    class="absolute inset-0 opacity-5"
                    style="background-image: url('/img/pattern.svg');"
                ></div>
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-12 relative">
                    <h1 class="text-3xl sm:text-4xl lg:text-4xl font-display font-bold text-white mb-2">
                        Digital Library
                    </h1>
                    <p class="text-base sm:text-lg text-white/80 max-w-2xl leading-relaxed">
                        Explore our collection of e-books and audiobooks designed to help you navigate 
                        your immigration journey with confidence.
                    </p>
                </div>
            </div>

            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
                <!-- Quick Stats -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6 -mt-8 sm:-mt-10 relative z-10">
                    <template v-for="typeOption in types.slice(0, 4)" :key="typeOption.value">
                        <Link
                            :href="route('library.index', { type: typeOption.value })"
                            class="bg-white rounded-xl p-3 sm:p-4 shadow-soft text-center hover:shadow-lg transition-shadow"
                        >
                            <component :is="getTypeIcon(typeOption.value)" class="w-7 h-7 sm:w-8 sm:h-8 text-primary-500 mx-auto mb-1.5" />
                            <p class="text-2xl font-bold text-slate-900">{{ typeOption.count ?? 0 }}</p>
                            <p class="text-sm text-slate-500">{{ typeOption.label }}</p>
                        </Link>
                    </template>
                </div>

                <!-- Filters -->
                <div class="bg-white rounded-2xl shadow-soft p-3 sm:p-4 mb-6">
                    <div class="flex flex-col lg:flex-row gap-3">
                        <!-- Search -->
                        <div class="flex-1 relative">
                            <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                            <input 
                                v-model="search"
                                type="text"
                                placeholder="Search by title, author..."
                                class="w-full pl-10 pr-3 py-2.5 bg-slate-50 border-0 rounded-xl text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary-500"
                            />
                        </div>

                        <!-- Category Filter -->
                        <select 
                            v-model="selectedCategory"
                            class="px-3 py-2.5 bg-slate-50 border-0 rounded-xl text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-primary-500"
                        >
                            <option value="">All Categories</option>
                            <option v-for="category in categories" :key="category.slug" :value="category.slug">
                                {{ category.name }}
                            </option>
                        </select>

                        <!-- Type Filter -->
                        <select 
                            v-model="selectedType"
                            class="px-3 py-2.5 bg-slate-50 border-0 rounded-xl text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-primary-500"
                        >
                            <option value="">All Types</option>
                            <option v-for="typeOption in types" :key="typeOption.value" :value="typeOption.value">
                                {{ typeOption.label }}
                            </option>
                        </select>

                        <!-- Favorites Toggle -->
                        <button 
                            @click="showFavorites = !showFavorites"
                            :class="[
                                'flex items-center gap-2 px-3 py-2.5 rounded-xl text-sm transition-colors',
                                showFavorites 
                                    ? 'bg-accent-100 text-accent-700' 
                                    : 'bg-slate-50 text-slate-700 hover:bg-slate-100'
                            ]"
                        >
                            <component :is="showFavorites ? HeartSolid : HeartIcon" class="w-5 h-5" />
                            Favorites
                        </button>
                    </div>
                </div>

                <!-- Category Structured Items -->
                <div v-if="groupedItems.length" class="space-y-10">
                    <section
                        v-for="group in groupedItems"
                        :key="group.slug"
                        class="space-y-4"
                    >
                        <div class="flex items-center justify-between">
                            <h2 class="text-xl font-display font-semibold text-slate-900">
                                {{ group.name }}
                            </h2>
                            <span class="text-sm text-slate-500">{{ group.items.length }} item(s)</span>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3 sm:gap-4">
                            <div
                                v-for="item in group.items"
                                :key="item.uuid"
                                class="bg-white rounded-xl shadow-soft overflow-hidden group hover:shadow-lg transition-all"
                            >
                                <!-- Cover Image (capped height — wide cards stay short) -->
                                <div class="relative h-24 sm:h-28 md:h-32 w-full bg-slate-100 overflow-hidden">
                                    <img
                                        v-if="item.cover_image_url"
                                        :src="item.cover_image_url"
                                        :alt="item.title"
                                        class="absolute inset-0 w-full h-full object-cover object-top group-hover:scale-105 transition-transform duration-300"
                                    />
                                    <div v-else class="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-primary-100 to-primary-200">
                                        <component :is="getTypeIcon(item.type)" class="w-8 h-8 sm:w-9 sm:h-9 text-primary-400" />
                                    </div>

                                    <!-- Type Badge -->
                                    <div class="absolute top-1.5 left-1.5 sm:top-2 sm:left-2">
                                        <span :class="[
                                            'inline-flex items-center gap-0.5 px-1.5 py-0.5 sm:px-2 sm:py-1 rounded-md text-[10px] sm:text-xs font-medium',
                                            item.type === 'ebook'
                                                ? 'bg-primary-100 text-primary-700'
                                                : 'bg-secondary-100 text-secondary-700'
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
                                            :class="['w-4 h-4', item.is_favorite ? 'text-accent-500' : 'text-slate-600']"
                                        />
                                    </button>

                                    <!-- Play Button for Audiobooks -->
                                    <Link
                                        v-if="item.type === 'audiobook'"
                                        :href="route('library.show', item.slug)"
                                        class="absolute inset-0 flex items-center justify-center bg-black/30 opacity-0 group-hover:opacity-100 transition-opacity"
                                    >
                                        <div class="w-11 h-11 sm:w-12 sm:h-12 bg-white rounded-full flex items-center justify-center shadow-lg">
                                            <PlayIcon class="w-5 h-5 sm:w-6 sm:h-6 text-primary-600 ml-0.5" />
                                        </div>
                                    </Link>
                                </div>

                                <!-- Content -->
                                <div class="p-2.5 sm:p-3">
                                    <Link :href="route('library.show', item.slug)">
                                        <h3 class="text-sm font-semibold text-slate-900 mb-0.5 line-clamp-2 group-hover:text-primary-600 transition-colors leading-snug">
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
                                            class="flex items-center gap-0.5 shrink-0 text-primary-600 hover:text-primary-700 font-medium"
                                        >
                                            <ArrowDownTrayIcon class="w-3.5 h-3.5" />
                                            <span class="hidden min-[380px]:inline">Download</span>
                                        </a>
                                        <Link
                                            v-else
                                            :href="route('library.show', item.slug)"
                                            class="flex items-center gap-0.5 shrink-0 text-primary-600 hover:text-primary-700 font-medium"
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

                <!-- Empty State -->
                <div v-else class="bg-white rounded-2xl shadow-soft p-12 text-center">
                    <BookOpenIcon class="w-16 h-16 text-slate-300 mx-auto mb-4" />
                    <h3 class="text-lg font-semibold text-slate-900 mb-2">No items found</h3>
                    <p class="text-slate-500 mb-6">
                        {{ search || selectedCategory || selectedType || showFavorites 
                            ? 'Try adjusting your filters' 
                            : 'Check back soon for new content!' 
                        }}
                    </p>
                    <button 
                        v-if="search || selectedCategory || selectedType || showFavorites"
                        @click="search = ''; selectedCategory = ''; selectedType = ''; showFavorites = false;"
                        class="px-6 py-3 bg-primary-600 hover:bg-primary-500 text-white font-semibold rounded-xl transition-colors"
                    >
                        Clear Filters
                    </button>
                </div>

                <!-- Pagination -->
                <div v-if="items.links && items.last_page > 1" class="mt-8 flex justify-center">
                    <nav class="flex items-center gap-2">
                        <Link
                            v-for="link in items.links"
                            :key="link.label"
                            :href="link.url"
                            :class="[
                                'px-4 py-2 rounded-lg text-sm font-medium transition-colors',
                                link.active 
                                    ? 'bg-primary-600 text-white' 
                                    : link.url 
                                        ? 'text-slate-600 hover:bg-slate-100' 
                                        : 'text-slate-300 cursor-not-allowed'
                            ]"
                            v-html="link.label"
                        />
                    </nav>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
