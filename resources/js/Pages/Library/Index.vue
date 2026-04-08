<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    BookOpenIcon, 
    MusicalNoteIcon,
    MagnifyingGlassIcon,
    FunnelIcon,
    HeartIcon,
    ArrowDownTrayIcon,
    PlayIcon,
    ClockIcon,
    StarIcon,
} from '@heroicons/vue/24/outline';
import { HeartIcon as HeartSolid, StarIcon as StarSolid } from '@heroicons/vue/24/solid';
import { ref, computed, watch } from 'vue';
import { debounce } from 'lodash-es';

const props = defineProps({
    items: Object,
    categories: Array,
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
    return type === 'ebook' ? BookOpenIcon : MusicalNoteIcon;
};

const getTypeLabel = (type) => {
    return type === 'ebook' ? 'E-Book' : 'Audiobook';
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
                <div class="absolute inset-0 bg-[url('/img/pattern.svg')] opacity-5"></div>
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 relative">
                    <h1 class="text-4xl lg:text-5xl font-display font-bold text-white mb-4">
                        Digital Library
                    </h1>
                    <p class="text-xl text-white/80 max-w-2xl">
                        Explore our collection of e-books and audiobooks designed to help you navigate 
                        your immigration journey with confidence.
                    </p>
                </div>
            </div>

            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <!-- Quick Stats -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8 -mt-12 relative z-10">
                    <div class="bg-white rounded-xl p-4 shadow-soft text-center">
                        <BookOpenIcon class="w-8 h-8 text-primary-500 mx-auto mb-2" />
                        <p class="text-2xl font-bold text-slate-900">{{ items.meta?.ebooks_count || '0' }}</p>
                        <p class="text-sm text-slate-500">E-Books</p>
                    </div>
                    <div class="bg-white rounded-xl p-4 shadow-soft text-center">
                        <MusicalNoteIcon class="w-8 h-8 text-secondary-500 mx-auto mb-2" />
                        <p class="text-2xl font-bold text-slate-900">{{ items.meta?.audiobooks_count || '0' }}</p>
                        <p class="text-sm text-slate-500">Audiobooks</p>
                    </div>
                    <Link :href="route('library.index', { type: 'ebook' })" 
                          class="bg-white rounded-xl p-4 shadow-soft text-center hover:shadow-lg transition-shadow">
                        <span class="text-sm font-medium text-primary-600">Browse E-Books →</span>
                    </Link>
                    <Link :href="route('library.index', { type: 'audiobook' })" 
                          class="bg-white rounded-xl p-4 shadow-soft text-center hover:shadow-lg transition-shadow">
                        <span class="text-sm font-medium text-secondary-600">Browse Audiobooks →</span>
                    </Link>
                </div>

                <!-- Filters -->
                <div class="bg-white rounded-2xl shadow-soft p-4 mb-8">
                    <div class="flex flex-col lg:flex-row gap-4">
                        <!-- Search -->
                        <div class="flex-1 relative">
                            <MagnifyingGlassIcon class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400" />
                            <input 
                                v-model="search"
                                type="text"
                                placeholder="Search by title, author..."
                                class="w-full pl-12 pr-4 py-3 bg-slate-50 border-0 rounded-xl text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary-500"
                            />
                        </div>

                        <!-- Category Filter -->
                        <select 
                            v-model="selectedCategory"
                            class="px-4 py-3 bg-slate-50 border-0 rounded-xl text-slate-700 focus:outline-none focus:ring-2 focus:ring-primary-500"
                        >
                            <option value="">All Categories</option>
                            <option v-for="category in categories" :key="category.slug" :value="category.slug">
                                {{ category.name }}
                            </option>
                        </select>

                        <!-- Type Filter -->
                        <select 
                            v-model="selectedType"
                            class="px-4 py-3 bg-slate-50 border-0 rounded-xl text-slate-700 focus:outline-none focus:ring-2 focus:ring-primary-500"
                        >
                            <option value="">All Types</option>
                            <option value="ebook">E-Books</option>
                            <option value="audiobook">Audiobooks</option>
                        </select>

                        <!-- Favorites Toggle -->
                        <button 
                            @click="showFavorites = !showFavorites"
                            :class="[
                                'flex items-center gap-2 px-4 py-3 rounded-xl transition-colors',
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

                <!-- Items Grid -->
                <div v-if="items.data?.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <div 
                        v-for="item in items.data" 
                        :key="item.uuid"
                        class="bg-white rounded-2xl shadow-soft overflow-hidden group hover:shadow-lg transition-all"
                    >
                        <!-- Cover Image -->
                        <div class="relative aspect-[3/4] bg-slate-100 overflow-hidden">
                            <img 
                                v-if="item.cover_image_url"
                                :src="item.cover_image_url" 
                                :alt="item.title"
                                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                            />
                            <div v-else class="w-full h-full flex items-center justify-center bg-gradient-to-br from-primary-100 to-primary-200">
                                <component :is="getTypeIcon(item.type)" class="w-16 h-16 text-primary-400" />
                            </div>

                            <!-- Type Badge -->
                            <div class="absolute top-3 left-3">
                                <span :class="[
                                    'inline-flex items-center gap-1 px-2 py-1 rounded-lg text-xs font-medium',
                                    item.type === 'ebook' 
                                        ? 'bg-primary-100 text-primary-700' 
                                        : 'bg-secondary-100 text-secondary-700'
                                ]">
                                    <component :is="getTypeIcon(item.type)" class="w-3.5 h-3.5" />
                                    {{ getTypeLabel(item.type) }}
                                </span>
                            </div>

                            <!-- Premium Badge -->
                            <div v-if="item.is_premium" class="absolute top-3 right-3">
                                <span class="inline-flex items-center gap-1 px-2 py-1 bg-amber-100 text-amber-700 rounded-lg text-xs font-medium">
                                    <StarSolid class="w-3.5 h-3.5" />
                                    Premium
                                </span>
                            </div>

                            <!-- Favorite Button -->
                            <button 
                                @click.prevent="toggleFavorite(item)"
                                class="absolute bottom-3 right-3 w-10 h-10 bg-white/90 backdrop-blur rounded-full flex items-center justify-center shadow-lg opacity-0 group-hover:opacity-100 transition-all hover:bg-white"
                            >
                                <component 
                                    :is="item.is_favorite ? HeartSolid : HeartIcon" 
                                    :class="['w-5 h-5', item.is_favorite ? 'text-accent-500' : 'text-slate-600']"
                                />
                            </button>

                            <!-- Play Button for Audiobooks -->
                            <Link 
                                v-if="item.type === 'audiobook'"
                                :href="route('library.show', item.slug)"
                                class="absolute inset-0 flex items-center justify-center bg-black/30 opacity-0 group-hover:opacity-100 transition-opacity"
                            >
                                <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center shadow-xl">
                                    <PlayIcon class="w-8 h-8 text-primary-600 ml-1" />
                                </div>
                            </Link>
                        </div>

                        <!-- Content -->
                        <div class="p-4">
                            <Link :href="route('library.show', item.slug)">
                                <h3 class="font-semibold text-slate-900 mb-1 line-clamp-2 group-hover:text-primary-600 transition-colors">
                                    {{ item.title }}
                                </h3>
                            </Link>
                            <p class="text-sm text-slate-500 mb-3">{{ item.author }}</p>

                            <div class="flex items-center justify-between text-sm">
                                <div class="flex items-center gap-3 text-slate-400">
                                    <span v-if="item.duration_formatted" class="flex items-center gap-1">
                                        <ClockIcon class="w-4 h-4" />
                                        {{ item.duration_formatted }}
                                    </span>
                                    <span v-else-if="item.file_size_formatted">
                                        {{ item.file_size_formatted }}
                                    </span>
                                </div>
                                
                                <Link 
                                    :href="route('library.download', item.slug)"
                                    class="flex items-center gap-1 text-primary-600 hover:text-primary-700 font-medium"
                                >
                                    <ArrowDownTrayIcon class="w-4 h-4" />
                                    Download
                                </Link>
                            </div>
                        </div>
                    </div>
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
