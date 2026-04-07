<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { 
    ArrowLeftIcon,
    ArrowDownTrayIcon,
    BookOpenIcon,
    MusicalNoteIcon,
    ClockIcon,
    DocumentTextIcon,
    HeartIcon,
    ShareIcon,
    StarIcon,
    CheckCircleIcon,
    PlayIcon,
    ChevronRightIcon
} from '@heroicons/vue/24/outline';
import { HeartIcon as HeartSolid, StarIcon as StarSolid } from '@heroicons/vue/24/solid';
import { ref, computed } from 'vue';

const props = defineProps({
    item: { type: Object, required: true },
    relatedItems: { type: Array, default: () => [] },
    userAccess: { type: Object, default: null },
    hasAccess: { type: Boolean, default: false },
});

const isDownloading = ref(false);
const isFavorited = ref(props.userAccess?.is_favorited || false);

const formatFileSize = (bytes) => {
    if (!bytes) return 'N/A';
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return `${(bytes / Math.pow(1024, i)).toFixed(1)} ${sizes[i]}`;
};

const formatDuration = (minutes) => {
    if (!minutes) return 'N/A';
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    if (hours > 0) {
        return `${hours}h ${mins}m`;
    }
    return `${mins} min`;
};

const downloadItem = () => {
    isDownloading.value = true;
    window.location.href = `/library/${props.item.slug}/download`;
    setTimeout(() => {
        isDownloading.value = false;
    }, 3000);
};

const toggleFavorite = () => {
    router.post(`/library/${props.item.slug}/favorite`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            isFavorited.value = !isFavorited.value;
        },
    });
};

const progressPercentage = computed(() => {
    if (!props.userAccess?.progress_data) return 0;
    // For ebooks: page / total_pages, for audiobooks: current_time / duration
    if (props.item.type === 'ebook' && props.userAccess.progress_data.current_page) {
        return Math.round((props.userAccess.progress_data.current_page / (props.item.page_count || 100)) * 100);
    }
    return 0;
});
</script>

<template>
    <Head :title="item.title" />

    <AppLayout>
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Breadcrumb -->
            <nav class="flex items-center gap-2 text-sm text-gray-500 mb-6">
                <Link href="/library" class="hover:text-gray-700">Library</Link>
                <ChevronRightIcon class="h-4 w-4" />
                <Link v-if="item.category" :href="`/library?category=${item.category.slug}`" class="hover:text-gray-700">
                    {{ item.category.name }}
                </Link>
                <ChevronRightIcon v-if="item.category" class="h-4 w-4" />
                <span class="text-gray-900">{{ item.title }}</span>
            </nav>

            <div class="grid lg:grid-cols-3 gap-8">
                <!-- Main Content -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <!-- Item Header -->
                        <div class="p-6 sm:p-8">
                            <div class="flex flex-col sm:flex-row gap-6">
                                <!-- Cover Image -->
                                <div class="flex-shrink-0">
                                    <div class="w-40 sm:w-48 mx-auto sm:mx-0">
                                        <img 
                                            v-if="item.cover_image_url"
                                            :src="item.cover_image_url" 
                                            :alt="item.title"
                                            class="w-full aspect-[3/4] object-cover rounded-xl shadow-lg"
                                        />
                                        <div 
                                            v-else
                                            class="w-full aspect-[3/4] bg-gradient-to-br from-primary-500 to-primary-700 rounded-xl shadow-lg flex items-center justify-center"
                                        >
                                            <BookOpenIcon v-if="item.type === 'ebook'" class="h-16 w-16 text-white/80" />
                                            <MusicalNoteIcon v-else class="h-16 w-16 text-white/80" />
                                        </div>
                                    </div>
                                </div>

                                <!-- Item Details -->
                                <div class="flex-1">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <span class="inline-flex items-center gap-1 text-xs font-medium text-primary-600 bg-primary-50 px-2 py-1 rounded-full mb-2">
                                                <BookOpenIcon v-if="item.type === 'ebook'" class="h-3 w-3" />
                                                <MusicalNoteIcon v-else class="h-3 w-3" />
                                                {{ item.type === 'ebook' ? 'E-Book' : 'Audiobook' }}
                                            </span>
                                            <h1 class="text-2xl sm:text-3xl font-display font-bold text-gray-900">
                                                {{ item.title }}
                                            </h1>
                                            <p v-if="item.author" class="text-gray-600 mt-1">
                                                by {{ item.author }}
                                            </p>
                                        </div>
                                        <button 
                                            @click="toggleFavorite"
                                            class="p-2 rounded-lg hover:bg-gray-100 transition-colors"
                                        >
                                            <HeartSolid v-if="isFavorited" class="h-6 w-6 text-red-500" />
                                            <HeartIcon v-else class="h-6 w-6 text-gray-400" />
                                        </button>
                                    </div>

                                    <!-- Meta Info -->
                                    <div class="flex flex-wrap gap-4 mt-4 text-sm text-gray-500">
                                        <div v-if="item.page_count" class="flex items-center gap-1">
                                            <DocumentTextIcon class="h-4 w-4" />
                                            {{ item.page_count }} pages
                                        </div>
                                        <div v-if="item.duration_minutes" class="flex items-center gap-1">
                                            <ClockIcon class="h-4 w-4" />
                                            {{ formatDuration(item.duration_minutes) }}
                                        </div>
                                        <div v-if="item.file_size" class="flex items-center gap-1">
                                            <ArrowDownTrayIcon class="h-4 w-4" />
                                            {{ formatFileSize(item.file_size) }}
                                        </div>
                                        <div v-if="item.downloads_count" class="flex items-center gap-1">
                                            <CheckCircleIcon class="h-4 w-4" />
                                            {{ item.downloads_count.toLocaleString() }} downloads
                                        </div>
                                    </div>

                                    <!-- Category & Language -->
                                    <div class="flex flex-wrap gap-2 mt-4">
                                        <Link 
                                            v-if="item.category"
                                            :href="`/library?category=${item.category.slug}`"
                                            class="badge badge-secondary"
                                        >
                                            {{ item.category.name }}
                                        </Link>
                                        <span v-if="item.language" class="badge badge-info">
                                            {{ item.language }}
                                        </span>
                                    </div>

                                    <!-- Progress Bar (if user has started) -->
                                    <div v-if="userAccess && progressPercentage > 0" class="mt-6">
                                        <div class="flex items-center justify-between text-sm text-gray-600 mb-1">
                                            <span>Your progress</span>
                                            <span>{{ progressPercentage }}%</span>
                                        </div>
                                        <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                                            <div 
                                                class="h-full bg-primary-600 rounded-full transition-all"
                                                :style="{ width: `${progressPercentage}%` }"
                                            ></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="px-6 sm:px-8 pb-6 sm:pb-8">
                            <h2 class="text-lg font-semibold text-gray-900 mb-3">About this {{ item.type === 'ebook' ? 'book' : 'audiobook' }}</h2>
                            <div class="prose prose-gray max-w-none">
                                <p class="text-gray-600 whitespace-pre-line">{{ item.description || 'No description available.' }}</p>
                            </div>
                        </div>

                        <!-- Table of Contents (if available) -->
                        <div v-if="item.table_of_contents?.length" class="px-6 sm:px-8 pb-6 sm:pb-8 border-t border-gray-100 pt-6">
                            <h2 class="text-lg font-semibold text-gray-900 mb-3">Table of Contents</h2>
                            <ul class="space-y-2">
                                <li 
                                    v-for="(chapter, index) in item.table_of_contents" 
                                    :key="index"
                                    class="flex items-center gap-3 text-gray-600"
                                >
                                    <span class="text-sm text-gray-400 w-6">{{ index + 1 }}</span>
                                    <span>{{ chapter.title || chapter }}</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sticky top-24">
                        <!-- Access Status -->
                        <div v-if="hasAccess" class="flex items-center gap-2 text-green-600 mb-4">
                            <CheckCircleIcon class="h-5 w-5" />
                            <span class="font-medium">You have access</span>
                        </div>

                        <!-- Action Buttons -->
                        <div class="space-y-3">
                            <button 
                                v-if="hasAccess"
                                @click="downloadItem"
                                :disabled="isDownloading"
                                class="w-full btn-primary btn-lg"
                            >
                                <ArrowDownTrayIcon v-if="!isDownloading" class="h-5 w-5 mr-2" />
                                <svg v-else class="animate-spin h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                {{ isDownloading ? 'Downloading...' : 'Download' }}
                            </button>

                            <button 
                                v-if="hasAccess && item.type === 'audiobook'"
                                class="w-full btn-secondary"
                            >
                                <PlayIcon class="h-5 w-5 mr-2" />
                                Listen Now
                            </button>

                            <div v-if="!hasAccess" class="text-center py-4">
                                <p class="text-gray-600 mb-4">Sign up to access this resource</p>
                                <Link href="/register" class="btn-primary btn-lg w-full">
                                    Create Free Account
                                </Link>
                            </div>
                        </div>

                        <!-- Share -->
                        <div class="mt-6 pt-6 border-t border-gray-100">
                            <button class="w-full btn-ghost flex items-center justify-center gap-2">
                                <ShareIcon class="h-5 w-5" />
                                Share
                            </button>
                        </div>

                        <!-- Item Stats -->
                        <div class="mt-6 pt-6 border-t border-gray-100 space-y-3">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-500">Format</span>
                                <span class="font-medium text-gray-900">{{ item.file_format?.toUpperCase() || 'PDF' }}</span>
                            </div>
                            <div v-if="item.isbn" class="flex items-center justify-between text-sm">
                                <span class="text-gray-500">ISBN</span>
                                <span class="font-medium text-gray-900">{{ item.isbn }}</span>
                            </div>
                            <div v-if="item.published_at" class="flex items-center justify-between text-sm">
                                <span class="text-gray-500">Published</span>
                                <span class="font-medium text-gray-900">{{ new Date(item.published_at).getFullYear() }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Related Items -->
            <div v-if="relatedItems.length" class="mt-12">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-display font-bold text-gray-900">You might also like</h2>
                    <Link :href="`/library?category=${item.category?.slug}`" class="text-primary-600 hover:text-primary-700 text-sm font-medium">
                        View all →
                    </Link>
                </div>
                <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <Link 
                        v-for="related in relatedItems" 
                        :key="related.id"
                        :href="`/library/${related.slug}`"
                        class="bg-white rounded-xl border border-gray-100 overflow-hidden hover:shadow-lg transition-shadow group"
                    >
                        <div class="aspect-[3/4] bg-gray-100 relative overflow-hidden">
                            <img 
                                v-if="related.cover_image_url"
                                :src="related.cover_image_url" 
                                :alt="related.title"
                                class="w-full h-full object-cover group-hover:scale-105 transition-transform"
                            />
                            <div 
                                v-else
                                class="w-full h-full bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center"
                            >
                                <BookOpenIcon v-if="related.type === 'ebook'" class="h-12 w-12 text-white/80" />
                                <MusicalNoteIcon v-else class="h-12 w-12 text-white/80" />
                            </div>
                            <span class="absolute top-2 right-2 bg-white/90 backdrop-blur-sm text-xs font-medium px-2 py-1 rounded">
                                {{ related.type === 'ebook' ? 'E-Book' : 'Audio' }}
                            </span>
                        </div>
                        <div class="p-4">
                            <h3 class="font-medium text-gray-900 truncate group-hover:text-primary-600">{{ related.title }}</h3>
                            <p v-if="related.author" class="text-sm text-gray-500 truncate">{{ related.author }}</p>
                        </div>
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
