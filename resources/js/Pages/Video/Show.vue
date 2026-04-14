<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    ArrowDownTrayIcon,
    BookOpenIcon,
    MusicalNoteIcon,
    VideoCameraIcon,
    ClockIcon,
    DocumentTextIcon,
    HeartIcon,
    ShareIcon,
    CheckCircleIcon,
    PlayIcon,
    ChevronRightIcon,
    LockClosedIcon,
    ShieldCheckIcon,
} from '@heroicons/vue/24/outline';
import { HeartIcon as HeartSolid } from '@heroicons/vue/24/solid';
import { ref, computed } from 'vue';

const props = defineProps({
    item: { type: Object, required: true },
    relatedItems: { type: Array, default: () => [] },
    userAccess: { type: Object, default: null },
    hasAccess: { type: Boolean, default: false },
    stripeSetupNote: { type: String, default: null },
});

const page = usePage();
const isDownloading = ref(false);
const isFavorited = ref(props.userAccess?.is_favorite || false);

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

const purchaseItem = () => {
    router.post(route('library.purchase', props.item.slug), {}, {
        preserveScroll: true,
    });
};

const progressPercentage = computed(() => {
    if (!props.userAccess?.progress) return 0;
    // For ebooks: page / total_pages, for audiobooks: current_time / duration
    if (props.item.type === 'ebook' && props.userAccess.progress.page) {
        return Math.round((props.userAccess.progress.page / (props.item.page_count || 100)) * 100);
    }
    return 0;
});

const typeLabel = computed(() => {
    const t = props.item.type;
    if (t === 'ebook') return 'E-Book';
    if (t === 'audiobook') return 'Audiobook';
    if (t === 'video') return 'Video';
    return 'Digital product';
});

const aboutNoun = computed(() => {
    const t = props.item.type;
    if (t === 'ebook') return 'book';
    if (t === 'video') return 'video';
    return 'audiobook';
});

const unlockPhrase = computed(() => {
    const t = props.item.type;
    if (t === 'ebook') return 'e-book';
    if (t === 'video') return 'video';
    return 'audiobook';
});

const coverTypeIcon = computed(() => {
    const t = props.item.type;
    if (t === 'ebook') return BookOpenIcon;
    if (t === 'video') return VideoCameraIcon;
    return MusicalNoteIcon;
});

function relatedTypeLabel(type) {
    if (type === 'ebook') return 'E-Book';
    if (type === 'video') return 'Video';
    return 'Audio';
}

function relatedCoverIcon(type) {
    if (type === 'ebook') return BookOpenIcon;
    if (type === 'video') return VideoCameraIcon;
    return MusicalNoteIcon;
}
</script>

<template>
    <Head :title="item.title" />

    <AppLayout>
        <div
            class="relative min-h-[calc(100vh-4rem)] bg-gradient-to-b from-slate-100/90 via-slate-50 to-white pb-12 pt-6 sm:pb-16 sm:pt-8"
        >
            <div
                class="pointer-events-none absolute inset-x-0 top-0 h-72 bg-[radial-gradient(ellipse_80%_60%_at_50%_-20%,rgba(59,149,243,0.18),transparent)]"
                aria-hidden="true"
            />
            <div class="relative mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <!-- Breadcrumb -->
                <nav
                    class="mb-6 flex flex-wrap items-center gap-1.5 text-xs font-medium uppercase tracking-wider text-slate-500 sm:text-sm sm:normal-case sm:tracking-normal"
                >
                    <Link
                        href="/library"
                        class="rounded-md px-1.5 py-0.5 text-slate-600 transition hover:bg-white/80 hover:text-primary-700"
                    >
                        Library
                    </Link>
                    <ChevronRightIcon class="h-3.5 w-3.5 shrink-0 text-slate-400 sm:h-4 sm:w-4" />
                    <Link
                        v-if="item.category"
                        :href="`/library?category=${item.category.slug}`"
                        class="rounded-md px-1.5 py-0.5 text-slate-600 transition hover:bg-white/80 hover:text-primary-700"
                    >
                        {{ item.category.name }}
                    </Link>
                    <ChevronRightIcon v-if="item.category" class="h-3.5 w-3.5 shrink-0 text-slate-400 sm:h-4 sm:w-4" />
                    <span class="max-w-[min(100%,28rem)] truncate text-slate-900">{{ item.title }}</span>
                </nav>

                <div class="grid gap-6 lg:grid-cols-12 lg:gap-8">
                    <!-- Main -->
                    <div class="lg:col-span-8">
                        <article
                            class="overflow-hidden rounded-3xl border border-white/70 bg-white/90 shadow-soft-lg ring-1 ring-slate-200/60 backdrop-blur-sm"
                        >
                            <div class="relative p-5 sm:p-8">
                                <div
                                    class="pointer-events-none absolute -right-20 -top-20 h-56 w-56 rounded-full bg-primary-400/10 blur-3xl"
                                    aria-hidden="true"
                                />
                                <div class="relative flex flex-col gap-6 sm:flex-row sm:gap-8">
                                    <!-- Cover -->
                                    <div class="mx-auto shrink-0 sm:mx-0">
                                        <div
                                            class="relative overflow-hidden rounded-2xl shadow-soft ring-2 ring-white sm:w-[11.5rem]"
                                        >
                                            <div class="relative aspect-[3/4] w-36 bg-slate-100 sm:w-full">
                                                <img
                                                    v-if="item.cover_image_url"
                                                    :src="item.cover_image_url"
                                                    :alt="item.title"
                                                    class="absolute inset-0 h-full w-full object-cover object-top"
                                                />
                                                <div
                                                    v-else
                                                    class="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-primary-500 via-primary-600 to-primary-800"
                                                >
                                                    <component
                                                        :is="coverTypeIcon"
                                                        class="h-12 w-12 text-white/90 drop-shadow-md"
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0 space-y-2">
                                                <span
                                                    class="inline-flex items-center gap-1.5 rounded-full border border-primary-100 bg-primary-50/90 px-3 py-1 text-xs font-semibold text-primary-700 shadow-sm"
                                                >
                                                    <component :is="coverTypeIcon" class="h-3.5 w-3.5" />
                                                    {{ typeLabel }}
                                                </span>
                                                <h1
                                                    class="font-display text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl xl:text-4xl"
                                                >
                                                    {{ item.title }}
                                                </h1>
                                                <p v-if="item.author" class="text-base text-slate-600">
                                                    <span class="text-slate-400">by</span>
                                                    {{ item.author }}
                                                </p>
                                            </div>
                                            <button
                                                type="button"
                                                title="Favorite"
                                                class="group shrink-0 rounded-full border border-slate-200/80 bg-white/90 p-2.5 shadow-sm transition hover:border-primary-200 hover:bg-primary-50/80 hover:shadow-md"
                                                @click="toggleFavorite"
                                            >
                                                <HeartSolid
                                                    v-if="isFavorited"
                                                    class="h-6 w-6 text-rose-500 transition group-hover:scale-105"
                                                />
                                                <HeartIcon
                                                    v-else
                                                    class="h-6 w-6 text-slate-400 transition group-hover:text-rose-400"
                                                />
                                            </button>
                                        </div>

                                        <div class="mt-5 flex flex-wrap gap-2">
                                            <div
                                                v-if="item.page_count"
                                                class="inline-flex items-center gap-1.5 rounded-xl border border-slate-100 bg-slate-50/90 px-3 py-1.5 text-sm text-slate-600"
                                            >
                                                <DocumentTextIcon class="h-4 w-4 text-slate-400" />
                                                {{ item.page_count }} pages
                                            </div>
                                            <div
                                                v-if="item.duration_minutes"
                                                class="inline-flex items-center gap-1.5 rounded-xl border border-slate-100 bg-slate-50/90 px-3 py-1.5 text-sm text-slate-600"
                                            >
                                                <ClockIcon class="h-4 w-4 text-slate-400" />
                                                {{ formatDuration(item.duration_minutes) }}
                                            </div>
                                            <div
                                                v-if="item.file_size"
                                                class="inline-flex items-center gap-1.5 rounded-xl border border-slate-100 bg-slate-50/90 px-3 py-1.5 text-sm text-slate-600"
                                            >
                                                <ArrowDownTrayIcon class="h-4 w-4 text-slate-400" />
                                                {{ formatFileSize(item.file_size) }}
                                            </div>
                                            <div
                                                v-if="item.downloads_count"
                                                class="inline-flex items-center gap-1.5 rounded-xl border border-slate-100 bg-slate-50/90 px-3 py-1.5 text-sm text-slate-600"
                                            >
                                                <CheckCircleIcon class="h-4 w-4 text-emerald-500/80" />
                                                {{ item.downloads_count.toLocaleString() }} downloads
                                            </div>
                                        </div>

                                        <div class="mt-4 flex flex-wrap gap-2">
                                            <Link
                                                v-if="item.category"
                                                :href="`/library?category=${item.category.slug}`"
                                                class="inline-flex items-center rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-700 shadow-sm transition hover:border-primary-200 hover:text-primary-700"
                                            >
                                                {{ item.category.name }}
                                            </Link>
                                            <span
                                                v-if="item.language"
                                                class="inline-flex items-center rounded-full border border-sky-100 bg-sky-50/90 px-3 py-1 text-xs font-semibold text-sky-800"
                                            >
                                                {{ item.language }}
                                            </span>
                                        </div>

                                        <div v-if="userAccess && progressPercentage > 0" class="mt-6">
                                            <div class="mb-2 flex items-center justify-between text-sm font-medium text-slate-600">
                                                <span>Your progress</span>
                                                <span class="tabular-nums text-primary-700">{{ progressPercentage }}%</span>
                                            </div>
                                            <div class="h-2 overflow-hidden rounded-full bg-slate-100 ring-1 ring-slate-200/60">
                                                <div
                                                    class="h-full rounded-full bg-gradient-to-r from-primary-500 to-primary-600 transition-all duration-500"
                                                    :style="{ width: `${progressPercentage}%` }"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="border-t border-slate-100/90 bg-slate-50/40 px-5 py-6 sm:px-8 sm:py-8">
                                <h2 class="font-display text-lg font-bold text-slate-900 sm:text-xl">
                                    About this {{ aboutNoun }}
                                </h2>
                                <div class="prose prose-slate prose-sm mt-3 max-w-none sm:prose-base prose-p:leading-relaxed">
                                    <p class="whitespace-pre-line text-slate-600">
                                        {{ item.description || 'No description available yet.' }}
                                    </p>
                                </div>
                            </div>

                            <div
                                v-if="item.table_of_contents?.length"
                                class="border-t border-slate-100/90 px-5 py-6 sm:px-8 sm:py-8"
                            >
                                <h2 class="font-display text-lg font-bold text-slate-900 sm:text-xl">Table of contents</h2>
                                <ul class="mt-4 space-y-2">
                                    <li
                                        v-for="(chapter, index) in item.table_of_contents"
                                        :key="index"
                                        class="flex items-baseline gap-3 rounded-xl px-3 py-2 text-slate-600 transition hover:bg-white/80"
                                    >
                                        <span
                                            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-xs font-semibold text-slate-500"
                                        >
                                            {{ index + 1 }}
                                        </span>
                                        <span class="text-sm sm:text-base">{{ chapter.title || chapter }}</span>
                                    </li>
                                </ul>
                            </div>
                        </article>
                    </div>

                    <!-- Sidebar -->
                    <div class="lg:col-span-4">
                        <div
                            class="sticky top-24 overflow-hidden rounded-3xl border border-white/80 bg-white/85 shadow-soft-lg ring-1 ring-slate-200/50 backdrop-blur-md"
                        >
                            <div
                                class="border-b border-slate-100/90 bg-gradient-to-br from-primary-600/5 via-white to-sky-50/30 px-6 py-5"
                            >
                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Library</p>
                                <p class="mt-1 font-display text-lg font-bold text-slate-900">Get this title</p>
                            </div>

                            <div class="space-y-4 px-6 py-6">
                                <div
                                    v-if="hasAccess"
                                    class="flex items-center gap-2 rounded-2xl border border-emerald-100 bg-emerald-50/80 px-4 py-3 text-emerald-800"
                                >
                                    <CheckCircleIcon class="h-5 w-5 shrink-0 text-emerald-600" />
                                    <span class="text-sm font-semibold">You have full access</span>
                                </div>

                                <button
                                    v-if="hasAccess"
                                    type="button"
                                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-primary-600 to-primary-700 px-4 py-3.5 text-sm font-semibold text-white shadow-md shadow-primary-600/25 transition hover:from-primary-500 hover:to-primary-600 disabled:opacity-60"
                                    :disabled="isDownloading"
                                    @click="downloadItem"
                                >
                                    <ArrowDownTrayIcon v-if="!isDownloading" class="h-5 w-5" />
                                    <svg
                                        v-else
                                        class="h-5 w-5 animate-spin"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                    >
                                        <circle
                                            class="opacity-25"
                                            cx="12"
                                            cy="12"
                                            r="10"
                                            stroke="currentColor"
                                            stroke-width="4"
                                        />
                                        <path
                                            class="opacity-75"
                                            fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                        />
                                    </svg>
                                    {{ isDownloading ? 'Preparing download…' : 'Download' }}
                                </button>

                                <button
                                    v-if="hasAccess && item.type === 'audiobook'"
                                    type="button"
                                    class="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-800 shadow-sm transition hover:border-primary-200 hover:bg-slate-50"
                                >
                                    <PlayIcon class="h-5 w-5 text-primary-600" />
                                    Listen now
                                </button>

                                <div v-if="!hasAccess && item.is_premium" class="space-y-4">
                                    <p class="text-sm leading-relaxed text-slate-600">
                                        Unlock this {{ unlockPhrase }} with a secure card
                                        checkout powered by Stripe — right on this site.
                                    </p>
                                    <div
                                        v-if="item.price"
                                        class="flex items-end justify-between rounded-2xl border border-slate-100 bg-slate-50/80 px-4 py-3"
                                    >
                                        <div>
                                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Total</p>
                                            <p class="font-display text-2xl font-bold tracking-tight text-slate-900">
                                                {{ item.currency ?? 'USD' }}
                                                <span class="tabular-nums">{{ item.price }}</span>
                                            </p>
                                        </div>
                                        <LockClosedIcon class="h-8 w-8 text-slate-300" aria-hidden="true" />
                                    </div>
                                    <p
                                        v-if="page.props.flash?.error"
                                        class="rounded-xl border border-red-100 bg-red-50 px-3 py-2 text-sm text-red-700"
                                    >
                                        {{ page.props.flash.error }}
                                    </p>
                                    <p
                                        v-if="stripeSetupNote"
                                        class="rounded-xl border border-amber-100 bg-amber-50/90 px-3 py-2 text-sm text-amber-900"
                                    >
                                        {{ stripeSetupNote }}
                                    </p>
                                    <Link
                                        :href="route('library.pay', item.slug)"
                                        class="flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-primary-600 to-primary-700 px-4 py-3.5 text-sm font-semibold text-white shadow-lg shadow-primary-600/30 transition hover:from-primary-500 hover:to-primary-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2"
                                    >
                                        Continue to payment
                                        <span v-if="item.price" class="opacity-95">· {{ item.currency ?? 'USD' }} {{ item.price }}</span>
                                    </Link>
                                    <p class="flex items-start gap-2 text-xs leading-relaxed text-slate-500">
                                        <ShieldCheckIcon class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600/80" />
                                        Apple Pay, Google Pay, and cards show when your browser supports them.
                                    </p>
                                </div>

                                <div v-else-if="!hasAccess" class="space-y-4 text-center">
                                    <p class="text-sm leading-relaxed text-slate-600">
                                        This {{ unlockPhrase }} is free — add it to your
                                        library to download.
                                    </p>
                                    <button
                                        type="button"
                                        class="w-full rounded-xl bg-gradient-to-r from-primary-600 to-primary-700 px-4 py-3.5 text-sm font-semibold text-white shadow-md shadow-primary-600/25 transition hover:from-primary-500 hover:to-primary-600"
                                        @click="purchaseItem"
                                    >
                                        Add to library &amp; download
                                    </button>
                                </div>

                                <button
                                    type="button"
                                    class="flex w-full items-center justify-center gap-2 rounded-xl border border-dashed border-slate-200 py-2.5 text-sm font-medium text-slate-600 transition hover:border-slate-300 hover:bg-slate-50"
                                >
                                    <ShareIcon class="h-4 w-4" />
                                    Share
                                </button>

                                <dl class="space-y-3 border-t border-slate-100 pt-4 text-sm">
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="text-slate-500">Format</dt>
                                        <dd class="font-semibold text-slate-900">
                                            {{ item.file_format?.toUpperCase() || 'PDF' }}
                                        </dd>
                                    </div>
                                    <div v-if="item.isbn" class="flex items-center justify-between gap-3">
                                        <dt class="text-slate-500">ISBN</dt>
                                        <dd class="font-mono text-xs font-semibold text-slate-900">{{ item.isbn }}</dd>
                                    </div>
                                    <div v-if="item.published_at" class="flex items-center justify-between gap-3">
                                        <dt class="text-slate-500">Published</dt>
                                        <dd class="font-semibold text-slate-900">
                                            {{ new Date(item.published_at).getFullYear() }}
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Related -->
                <section v-if="relatedItems.length" class="mt-12 sm:mt-16">
                    <div class="mb-6 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-primary-600">Discover more</p>
                            <h2 class="font-display text-xl font-bold text-slate-900 sm:text-2xl">You might also like</h2>
                        </div>
                        <Link
                            :href="`/library?category=${item.category?.slug}`"
                            class="text-sm font-semibold text-primary-600 transition hover:text-primary-700"
                        >
                            View all in category →
                        </Link>
                    </div>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-5 xl:grid-cols-6">
                        <Link
                            v-for="related in relatedItems"
                            :key="related.id"
                            :href="`/library/${related.slug}`"
                            class="group overflow-hidden rounded-2xl border border-slate-200/80 bg-white/90 shadow-sm ring-1 ring-slate-100/80 transition hover:-translate-y-0.5 hover:border-primary-200/60 hover:shadow-soft-lg"
                        >
                            <div class="relative aspect-[4/5] w-full overflow-hidden bg-slate-100">
                                <img
                                    v-if="related.cover_image_url"
                                    :src="related.cover_image_url"
                                    :alt="related.title"
                                    class="absolute inset-0 h-full w-full object-cover object-top transition duration-500 group-hover:scale-105"
                                />
                                <div
                                    v-else
                                    class="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-primary-500 to-primary-800"
                                >
                                    <component :is="relatedCoverIcon(related.type)" class="h-10 w-10 text-white/90" />
                                </div>
                                <span
                                    class="absolute right-2 top-2 rounded-full bg-white/95 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-700 shadow-sm backdrop-blur-sm"
                                >
                                    {{ relatedTypeLabel(related.type) }}
                                </span>
                            </div>
                            <div class="p-3">
                                <h3
                                    class="line-clamp-2 text-sm font-semibold leading-snug text-slate-900 group-hover:text-primary-700"
                                >
                                    {{ related.title }}
                                </h3>
                                <p v-if="related.author" class="mt-1 truncate text-xs text-slate-500">
                                    {{ related.author }}
                                </p>
                            </div>
                        </Link>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
