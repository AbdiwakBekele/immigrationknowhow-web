<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import ProviderLayout from '@/Layouts/ProviderLayout.vue';
import {
    ArrowLeftIcon,
    BookOpenIcon,
    MusicalNoteIcon,
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
import { ref, computed, onBeforeUnmount, onMounted, watch } from 'vue';
import { renderSafeMarkdown } from '@/utils/markdown';

const props = defineProps({
    item: { type: Object, required: true },
    relatedItems: { type: Array, default: () => [] },
    userAccess: { type: Object, default: null },
    hasAccess: { type: Boolean, default: false },
    requiresPaidAccess: { type: Boolean, default: false },
    libraryPaymentMode: { type: String, default: 'stripe' },
    manualPaymentPending: { type: Boolean, default: false },
    mediaUrls: { type: Object, default: () => ({}) },
    progressUrl: { type: String, default: null },
    summary: { type: String, default: null },
    summaryUrl: { type: String, default: null },
});

const page = usePage();
const isOpening = ref(false);
const isFavorited = ref(props.userAccess?.is_favorite || false);
const activeDetailTab = ref('more');
const landingAudioRef = ref(null);
const summaryText = ref(props.summary || '');
const summaryLoading = ref(false);
const summaryError = ref('');

const savedAudioProgress = computed(() => {
    const progress = props.userAccess?.progress ?? {};
    return progress.audio ?? (progress.position !== undefined ? progress : {});
});
const landingAudioCurrentTime = ref(Number(savedAudioProgress.value?.position) || 0);
const landingAudioDuration = ref(Number(savedAudioProgress.value?.duration) || props.item.duration_seconds || 0);
let landingAudioSaveTimer = null;
let landingAudioLastSaveAt = 0;
let landingAudioWasRestored = false;

const sharePurchaseUrl = computed(() => {
    const path = route('provider.library.show', { item: props.item.slug });
    const isAbsolute = /^https?:\/\//i.test(String(path));
    if (typeof window !== 'undefined' && !isAbsolute) {
        return `${window.location.origin}${path}`;
    }
    return path;
});

const libraryPayUrl = computed(() => route('library.pay', { item: props.item.slug, portal: 'provider' }));
const libraryCartAddUrl = computed(() => route('library.cart.add', { item: props.item.slug }));

const libraryListUrl = computed(() => route('provider.library.index'));
const libraryListLabel = computed(() => 'My Library');

const formatFileSize = (bytes) => {
    if (!bytes) return 'N/A';
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return `${(bytes / Math.pow(1024, i)).toFixed(1)} ${sizes[i]}`;
};

const formatDuration = (seconds) => {
    if (!seconds) return 'N/A';
    const hours = Math.floor(seconds / 3600);
    const mins = Math.floor((seconds % 3600) / 60);
    if (hours > 0) return `${hours}h ${mins}m`;
    return `${mins} min`;
};

const openItem = () => {
    isOpening.value = true;
    router.visit(route('provider.library.read', props.item.slug), {
        onFinish: () => {
            isOpening.value = false;
        },
    });
    setTimeout(() => {
        isOpening.value = false;
    }, 3000);
};

const toggleFavorite = () => {
    router.post(route('library.favorite', props.item.slug), {}, {
        preserveScroll: true,
        onSuccess: () => {
            isFavorited.value = !isFavorited.value;
        },
    });
};

const purchaseItem = () => {
    router.post(route('library.purchase', { item: props.item.slug }), {}, {
        preserveScroll: true,
    });
};

const sharePurchaseLink = async () => {
    const title = `"${props.item.title}" on IKH Library`;
    const url = sharePurchaseUrl.value;

    if (typeof navigator !== 'undefined' && typeof navigator.share === 'function') {
        try {
            await navigator.share({ title, url });
            return;
        } catch {
            // fallthrough to clipboard
        }
    }

    if (typeof navigator !== 'undefined' && navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(url);
        window.alert('Book link copied.');
        return;
    }

    window.prompt('Copy this book link:', url);
};

const progressValue = (progress) => {
    if (!progress) return 0;
    if (progress.percentage) return Math.round(progress.percentage);
    if (progress.page && progress.total_pages) return Math.round((progress.page / progress.total_pages) * 100);
    if (progress.position && progress.duration) return Math.round((progress.position / progress.duration) * 100);
    return 0;
};

const progressPercentage = computed(() => {
    if (!props.userAccess?.progress) return 0;
    const progress = props.userAccess.progress;
    if (props.item.type === 'ebook') {
        return Math.max(progressValue(progress.reading ?? progress), progressValue(progress.audio));
    }
    if (props.item.type === 'audiobook') {
        return progressValue(progress.audio ?? progress);
    }
    return 0;
});

const hasAudioCompanion = computed(() => props.item.type === 'ebook' && props.item.has_audio_companion);
const landingAudioUrl = computed(() => props.mediaUrls?.audio || null);
const canPlayLandingAudio = computed(() => props.hasAccess && Boolean(landingAudioUrl.value));
const landingAudioPercent = computed(() => {
    if (!landingAudioDuration.value) return 0;
    return Math.min(100, Math.max(0, Math.round((landingAudioCurrentTime.value / landingAudioDuration.value) * 100)));
});

const accessActionLabel = computed(() => {
    if (hasAudioCompanion.value) return 'Read or listen now';
    return props.item.type === 'ebook' ? 'Read now' : 'Listen now';
});

const addAccessLabel = computed(() => {
    if (hasAudioCompanion.value) return 'Add to library';
    return props.item.type === 'ebook' ? 'Add to library & read' : 'Add to library & listen';
});

const canShowSummaryTab = computed(() => props.item.type === 'ebook');
const canRequestSummary = computed(() => Boolean(props.summaryUrl) && !summaryText.value);
const renderedSummaryHtml = computed(() => renderSafeMarkdown(summaryText.value));

const csrfToken = () => {
    const rawToken = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];
    return rawToken ? decodeURIComponent(rawToken) : '';
};

const loadSummary = async () => {
    if (!props.summaryUrl || summaryLoading.value) return;

    summaryLoading.value = true;
    summaryError.value = '';

    const token = csrfToken();
    const headers = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };
    if (token) headers['X-XSRF-TOKEN'] = token;

    try {
        const response = await fetch(props.summaryUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers,
            body: JSON.stringify({}),
        });
        if (!response.ok) throw new Error('Summary request failed');
        const payload = await response.json();
        summaryText.value = payload.summary || '';
        if (!summaryText.value) {
            const pending = String(payload.message || '').toLowerCase().includes('being generated');
            if (!pending) {
                summaryError.value = payload.message || 'Summary not available yet for this ebook.';
            }
        }
    } catch {
        summaryError.value = 'Could not load summary right now. Please try again.';
    } finally {
        summaryLoading.value = false;
    }
};

let summaryPollTimer = null;

const stopSummaryPolling = () => {
    if (summaryPollTimer) {
        clearInterval(summaryPollTimer);
        summaryPollTimer = null;
    }
};

const pollSummaryIfNeeded = () => {
    stopSummaryPolling();
    if (summaryText.value || props.item.type !== 'ebook' || !props.summaryUrl) {
        return;
    }

    void loadSummary();

    let attempts = 0;
    summaryPollTimer = setInterval(() => {
        attempts += 1;
        if (summaryText.value || attempts >= 36) {
            stopSummaryPolling();
            return;
        }

        void loadSummary();
    }, 5000);
};

watch(activeDetailTab, (tab) => {
    if (tab !== 'summary') {
        stopSummaryPolling();
        return;
    }

    pollSummaryIfNeeded();
});

const landingAudioPayload = () => ({
    position: Math.floor(landingAudioCurrentTime.value),
    duration: Math.floor(landingAudioDuration.value),
    percentage: landingAudioPercent.value,
});

const persistLandingAudioProgress = async (progress, keepalive = false) => {
    if (!props.progressUrl) return;

    const token = csrfToken();
    const headers = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };
    if (token) headers['X-XSRF-TOKEN'] = token;

    try {
        await fetch(props.progressUrl, {
            method: 'POST',
            credentials: 'same-origin',
            keepalive,
            headers,
            body: JSON.stringify({ mode: 'audio', progress }),
        });
    } catch {
        // ignore
    }
};

const queueLandingAudioSave = (delay = 900) => {
    if (!canPlayLandingAudio.value) return;
    clearTimeout(landingAudioSaveTimer);
    landingAudioSaveTimer = setTimeout(() => {
        persistLandingAudioProgress(landingAudioPayload());
    }, delay);
};

const flushLandingAudioProgress = () => {
    clearTimeout(landingAudioSaveTimer);
    if (canPlayLandingAudio.value) {
        persistLandingAudioProgress(landingAudioPayload(), true);
    }
};

const restoreLandingAudioPosition = () => {
    if (landingAudioWasRestored || !landingAudioRef.value) return;
    const savedPosition = Number(savedAudioProgress.value?.position) || 0;
    const duration = landingAudioRef.value.duration || landingAudioDuration.value || 0;
    if (savedPosition > 1 && (!duration || savedPosition < duration - 2)) {
        landingAudioRef.value.currentTime = savedPosition;
        landingAudioCurrentTime.value = savedPosition;
    }
    landingAudioWasRestored = true;
};

const handleLandingAudioLoaded = () => {
    landingAudioDuration.value = landingAudioRef.value?.duration || landingAudioDuration.value || 0;
    restoreLandingAudioPosition();
    queueLandingAudioSave(300);
};

const handleLandingAudioTimeUpdate = () => {
    landingAudioCurrentTime.value = landingAudioRef.value?.currentTime || 0;
    landingAudioDuration.value = landingAudioRef.value?.duration || landingAudioDuration.value || 0;
    const now = Date.now();
    if (now - landingAudioLastSaveAt > 5000) {
        landingAudioLastSaveAt = now;
        queueLandingAudioSave();
    }
};

const handleLandingAudioEnded = () => {
    landingAudioCurrentTime.value = landingAudioDuration.value;
    queueLandingAudioSave(0);
};

const handleLandingAudioVisibility = () => {
    if (document.visibilityState === 'hidden') {
        flushLandingAudioProgress();
    }
};

const formatPlaybackTime = (seconds) => {
    const safeSeconds = Math.max(0, Number(seconds) || 0);
    const hours = Math.floor(safeSeconds / 3600);
    const minutes = Math.floor((safeSeconds % 3600) / 60);
    const remainingSeconds = Math.floor(safeSeconds % 60);
    if (hours > 0) {
        return `${hours}:${String(minutes).padStart(2, '0')}:${String(remainingSeconds).padStart(2, '0')}`;
    }
    return `${minutes}:${String(remainingSeconds).padStart(2, '0')}`;
};

onMounted(() => {
    if (canShowSummaryTab.value) activeDetailTab.value = 'summary';
    if (activeDetailTab.value === 'summary') {
        pollSummaryIfNeeded();
    }
    document.addEventListener('visibilitychange', handleLandingAudioVisibility);
    window.addEventListener('pagehide', flushLandingAudioProgress);
});

onBeforeUnmount(() => {
    stopSummaryPolling();
    flushLandingAudioProgress();
    document.removeEventListener('visibilitychange', handleLandingAudioVisibility);
    window.removeEventListener('pagehide', flushLandingAudioProgress);
});
</script>

<template>
    <Head :title="item.title" />

    <ProviderLayout>
        <div class="relative pb-12 pt-6 sm:pb-16 sm:pt-8">
            <div class="relative mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <div class="mb-6 flex flex-col gap-3 sm:mb-8 sm:flex-row sm:items-center sm:gap-4">
                    <Link
                        :href="libraryListUrl"
                        class="group inline-flex w-fit shrink-0 items-center gap-2 rounded-2xl border border-slate-200/80 bg-gradient-to-b from-white to-slate-50/90 px-4 py-2.5 text-sm font-semibold text-slate-800 shadow-sm ring-1 ring-slate-200/50 transition hover:-translate-y-px hover:border-primary-200/90 hover:from-primary-50/30 hover:to-white hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/30"
                    >
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-slate-100/90 text-slate-600 ring-1 ring-slate-200/60 transition group-hover:bg-primary-50 group-hover:text-primary-700 group-hover:ring-primary-200/60">
                            <ArrowLeftIcon class="h-4 w-4" aria-hidden="true" />
                        </span>
                        <span>Back to {{ libraryListLabel }}</span>
                    </Link>
                    <nav class="min-w-0 flex-1" aria-label="Breadcrumb">
                        <ol class="flex min-w-0 flex-wrap items-center gap-x-1.5 gap-y-1 rounded-2xl border border-slate-200/60 bg-white/80 px-3 py-2.5 shadow-sm ring-1 ring-slate-200/40 backdrop-blur-sm sm:px-4 sm:py-2.5">
                            <li class="inline-flex min-w-0 items-center">
                                <Link :href="libraryListUrl" class="truncate text-xs font-semibold text-slate-500 transition hover:text-primary-600 sm:text-sm">
                                    {{ libraryListLabel }}
                                </Link>
                            </li>
                            <li class="inline-flex" aria-hidden="true">
                                <ChevronRightIcon class="h-3.5 w-3.5 text-slate-300 sm:h-4 sm:w-4" />
                            </li>
                            <li class="min-w-0 text-xs font-bold text-slate-900 sm:text-sm" aria-current="page">
                                <span class="line-clamp-2 sm:line-clamp-1">{{ item.title }}</span>
                            </li>
                        </ol>
                    </nav>
                </div>

                <div class="grid gap-6 lg:grid-cols-12 lg:gap-8">
                    <div class="lg:col-span-8">
                        <article class="overflow-hidden rounded-3xl border border-white/70 bg-white/90 shadow-soft-lg ring-1 ring-slate-200/60 backdrop-blur-sm">
                            <div class="relative p-5 sm:p-8">
                                <div class="relative flex flex-col gap-6 sm:flex-row sm:gap-8">
                                    <div class="mx-auto shrink-0 sm:mx-0">
                                        <div class="relative overflow-hidden rounded-2xl shadow-soft ring-2 ring-white sm:w-[11.5rem]">
                                            <div class="relative aspect-[3/4] w-36 bg-slate-100 sm:w-full">
                                                <img
                                                    v-if="item.cover_image_url"
                                                    :src="item.cover_image_url"
                                                    :alt="item.title"
                                                    class="absolute inset-0 h-full w-full object-cover object-top"
                                                />
                                                <div v-else class="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-primary-500 via-primary-600 to-primary-800">
                                                    <BookOpenIcon v-if="item.type === 'ebook'" class="h-12 w-12 text-white/90 drop-shadow-md" />
                                                    <MusicalNoteIcon v-else class="h-12 w-12 text-white/90 drop-shadow-md" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0 space-y-2">
                                                <span class="inline-flex items-center gap-1.5 rounded-full border border-primary-100 bg-primary-50/90 px-3 py-1 text-xs font-semibold text-primary-700 shadow-sm">
                                                    <BookOpenIcon v-if="item.type === 'ebook'" class="h-3.5 w-3.5" />
                                                    <MusicalNoteIcon v-else class="h-3.5 w-3.5" />
                                                    {{ item.type === 'ebook' ? 'E-Book' : 'Audiobook' }}
                                                </span>
                                                <h1 class="font-display text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl xl:text-4xl">
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
                                                <HeartSolid v-if="isFavorited" class="h-6 w-6 text-rose-500 transition group-hover:scale-105" />
                                                <HeartIcon v-else class="h-6 w-6 text-slate-400 transition group-hover:text-rose-400" />
                                            </button>
                                        </div>

                                        <div v-if="userAccess && progressPercentage > 0" class="mt-6">
                                            <div class="mb-2 flex items-center justify-between text-sm font-medium text-slate-600">
                                                <span>Your progress</span>
                                                <span class="tabular-nums text-primary-700">{{ progressPercentage }}%</span>
                                            </div>
                                            <div class="h-2 overflow-hidden rounded-full bg-slate-100 ring-1 ring-slate-200/60">
                                                <div class="h-full rounded-full bg-gradient-to-r from-primary-500 to-primary-600 transition-all duration-500" :style="{ width: `${progressPercentage}%` }" />
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-6 grid grid-cols-1 gap-3 md:grid-cols-2">
                                    <div v-if="item.page_count" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-100 bg-slate-50/90 px-3 py-2 text-sm text-slate-600">
                                        <DocumentTextIcon class="h-4 w-4 text-slate-400" />
                                        {{ item.page_count }} pages
                                    </div>
                                    <div v-if="item.duration_seconds" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-100 bg-slate-50/90 px-3 py-2 text-sm text-slate-600">
                                        <ClockIcon class="h-4 w-4 text-slate-400" />
                                        {{ formatDuration(item.duration_seconds) }}
                                    </div>
                                    <div v-if="item.file_size" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-100 bg-slate-50/90 px-3 py-2 text-sm text-slate-600">
                                        <DocumentTextIcon class="h-4 w-4 text-slate-400" />
                                        {{ formatFileSize(item.file_size) }}
                                    </div>
                                </div>
                            </div>

                            <div class="border-t border-slate-100/90 bg-slate-50/40 px-5 py-6 sm:px-8 sm:py-8">
                                <div v-if="canShowSummaryTab || item.description" class="mb-5 inline-flex rounded-xl bg-white p-1 shadow-sm ring-1 ring-slate-200">
                                    <button
                                        v-if="canShowSummaryTab"
                                        type="button"
                                        class="rounded-lg px-4 py-2 text-sm font-semibold transition"
                                        :class="activeDetailTab === 'summary' ? 'bg-primary-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50'"
                                        @click="activeDetailTab = 'summary'"
                                    >
                                        Summary
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-lg px-4 py-2 text-sm font-semibold transition"
                                        :class="activeDetailTab === 'more' ? 'bg-primary-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50'"
                                        @click="activeDetailTab = 'more'"
                                    >
                                        More Info
                                    </button>
                                </div>

                                <div v-if="activeDetailTab === 'more'" class="rounded-xl bg-white px-4 py-4 text-sm ring-1 ring-slate-100">
                                    <h2 class="font-display text-lg font-bold text-slate-900 sm:text-xl">
                                        About this {{ item.type === 'ebook' ? 'book' : 'audiobook' }}
                                    </h2>
                                    <p class="mt-2 whitespace-pre-line leading-relaxed text-slate-600">
                                        {{ item.description || 'No description available yet.' }}
                                    </p>
                                </div>

                                <div v-else class="space-y-3">
                                    <div class="flex items-center justify-between gap-3">
                                        <h2 class="font-display text-lg font-bold text-slate-900 sm:text-xl">AI Summary</h2>
                                        <button
                                            v-if="canRequestSummary"
                                            type="button"
                                            class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                                            :disabled="summaryLoading"
                                            @click="loadSummary"
                                        >
                                            {{ summaryLoading ? 'Loading summary...' : 'Summarize this book' }}
                                        </button>
                                    </div>
                                    <p v-if="summaryError" class="text-sm text-red-600">{{ summaryError }}</p>
                                    <div v-else-if="summaryText" class="prose prose-slate prose-sm max-w-none sm:prose-base" v-html="renderedSummaryHtml" />
                                    <p v-else class="text-sm text-slate-600">
                                        Summary will appear here when ready.
                                    </p>
                                </div>
                            </div>
                        </article>
                    </div>

                    <div class="lg:col-span-4">
                        <div class="sticky top-24 overflow-hidden rounded-3xl border border-white/80 bg-white/85 shadow-soft-lg ring-1 ring-slate-200/50 backdrop-blur-md">
                            <div class="border-b border-slate-100/90 bg-gradient-to-br from-primary-600/5 via-white to-sky-50/30 px-6 py-5">
                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Library</p>
                                <p class="mt-1 font-display text-lg font-bold text-slate-900">Get this title</p>
                            </div>

                            <div class="space-y-4 px-6 py-6">
                                <div v-if="hasAccess" class="flex items-center gap-2 rounded-2xl border border-emerald-100 bg-emerald-50/80 px-4 py-3 text-emerald-800">
                                    <CheckCircleIcon class="h-5 w-5 shrink-0 text-emerald-600" />
                                    <span class="text-sm font-semibold">You have full access</span>
                                </div>

                                <button
                                    v-if="hasAccess"
                                    type="button"
                                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-primary-600 to-primary-700 px-4 py-3.5 text-sm font-semibold text-white shadow-md shadow-primary-600/25 transition hover:from-primary-500 hover:to-primary-600 disabled:opacity-60"
                                    :disabled="isOpening"
                                    @click="openItem"
                                >
                                    <BookOpenIcon v-if="!isOpening && item.type === 'ebook'" class="h-5 w-5" />
                                    <PlayIcon v-else-if="!isOpening" class="h-5 w-5" />
                                    <span v-if="isOpening" class="text-sm">Opening...</span>
                                    <span v-else>{{ accessActionLabel }}</span>
                                </button>

                                <div v-if="canPlayLandingAudio" class="space-y-3 rounded-xl border border-sky-100 bg-sky-50/80 p-4 text-sky-950">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="flex min-w-0 items-start gap-3">
                                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white text-sky-700 shadow-sm ring-1 ring-sky-100">
                                                <MusicalNoteIcon class="h-5 w-5" />
                                            </span>
                                            <div class="min-w-0">
                                                <p class="text-sm font-bold">Listen to audio</p>
                                                <p class="mt-0.5 text-xs leading-relaxed text-sky-800">Your listening position saves automatically.</p>
                                            </div>
                                        </div>
                                        <span class="shrink-0 rounded-full bg-white px-2.5 py-1 text-xs font-bold text-sky-700 shadow-sm ring-1 ring-sky-100">
                                            {{ landingAudioPercent }}%
                                        </span>
                                    </div>

                                    <div class="h-2 overflow-hidden rounded-full bg-white ring-1 ring-sky-100">
                                        <div class="h-full rounded-full bg-sky-500 transition-all duration-300" :style="{ width: `${landingAudioPercent}%` }" />
                                    </div>

                                    <audio
                                        ref="landingAudioRef"
                                        class="w-full"
                                        controls
                                        controlsList="nodownload"
                                        preload="metadata"
                                        :src="landingAudioUrl"
                                        @contextmenu.prevent
                                        @loadedmetadata="handleLandingAudioLoaded"
                                        @timeupdate="handleLandingAudioTimeUpdate"
                                        @pause="queueLandingAudioSave(0)"
                                        @seeked="queueLandingAudioSave(0)"
                                        @ended="handleLandingAudioEnded"
                                    />

                                    <div class="flex items-center justify-between text-xs font-semibold text-sky-800">
                                        <span class="tabular-nums">{{ formatPlaybackTime(landingAudioCurrentTime) }}</span>
                                        <span class="tabular-nums">{{ formatPlaybackTime(landingAudioDuration) }}</span>
                                    </div>
                                </div>

                                <div v-if="!hasAccess && (item.is_premium || requiresPaidAccess)" class="space-y-4">
                                    <p v-if="page.props.flash?.error" class="rounded-xl border border-red-100 bg-red-50 px-3 py-2 text-sm text-red-700">
                                        {{ page.props.flash.error }}
                                    </p>

                                    <div v-if="item.price" class="flex items-end justify-between rounded-2xl border border-slate-100 bg-slate-50/80 px-4 py-3">
                                        <div>
                                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Total</p>
                                            <p class="font-display text-2xl font-bold tracking-tight text-slate-900">
                                                {{ item.currency ?? 'USD' }}
                                                <span class="tabular-nums">{{ item.price }}</span>
                                            </p>
                                        </div>
                                        <LockClosedIcon class="h-8 w-8 text-slate-300" aria-hidden="true" />
                                    </div>

                                    <div v-if="manualPaymentPending" class="rounded-xl border border-amber-200 bg-amber-50/90 px-4 py-3 text-sm text-amber-950">
                                        <p class="font-semibold">Payment verification pending</p>
                                        <p class="mt-1 text-amber-900/90">
                                            We received your payment details. An administrator will confirm and unlock your access — check back soon.
                                        </p>
                                    </div>

                                    <a
                                        v-if="!manualPaymentPending && libraryPaymentMode !== 'unavailable'"
                                        :href="libraryPayUrl"
                                        class="flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-primary-600 to-primary-700 px-4 py-3.5 text-sm font-semibold text-white shadow-lg shadow-primary-600/30 transition hover:from-primary-500 hover:to-primary-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2"
                                    >
                                        Continue to payment
                                        <span v-if="item.price" class="opacity-95">· {{ item.currency ?? 'USD' }} {{ item.price }}</span>
                                    </a>

                                    <Link
                                        v-if="!manualPaymentPending && libraryPaymentMode === 'stripe'"
                                        :href="libraryCartAddUrl"
                                        method="post"
                                        as="button"
                                        type="button"
                                        class="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-800 shadow-sm transition hover:border-primary-200 hover:bg-slate-50"
                                    >
                                        Add to cart
                                    </Link>

                                    <p v-if="libraryPaymentMode === 'stripe'" class="flex items-start gap-2 text-xs leading-relaxed text-slate-500">
                                        <ShieldCheckIcon class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600/80" />
                                        Apple Pay, Google Pay, and cards show when your browser supports them.
                                    </p>
                                </div>

                                <div v-else-if="!hasAccess" class="space-y-4 text-center">
                                    <p class="text-sm leading-relaxed text-slate-600">
                                        This {{ item.type === 'ebook' ? 'e-book' : 'audiobook' }} is free — add it to your library to open it.
                                    </p>
                                    <button
                                        type="button"
                                        class="w-full rounded-xl bg-gradient-to-r from-primary-600 to-primary-700 px-4 py-3.5 text-sm font-semibold text-white shadow-md shadow-primary-600/25 transition hover:from-primary-500 hover:to-primary-600"
                                        @click="purchaseItem"
                                    >
                                        {{ addAccessLabel }}
                                    </button>
                                </div>

                                <button
                                    type="button"
                                    class="flex w-full items-center justify-center gap-2 rounded-xl border border-dashed border-slate-200 py-2.5 text-sm font-medium text-slate-600 transition hover:border-slate-300 hover:bg-slate-50"
                                    @click="sharePurchaseLink"
                                >
                                    <ShareIcon class="h-4 w-4" />
                                    Share
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </ProviderLayout>
</template>

